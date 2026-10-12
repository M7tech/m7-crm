<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/sheet-reader.php';
require __DIR__.'/smtp.php';
$currentUser = require_login();

const EMAIL_MAX_ROWS = 100;
const EMAIL_MAX_ATTACHMENT = 5_000_000;

$error = null;
$notice = null;
$headers = [];
$rows = [];
$sourceName = null;
$sheetPayload = '';
$emailColumn = '';
$nameColumn = '';
$subjectColumn = '';
$messageColumn = '';
$sharedSubject = '';
$sharedMessage = '';
$emails = [];
$emailPayload = '';
$emailSignature = '';
$attachment = active_attachment((int) $currentUser['id']);
cleanup_attachments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'upload') {
            [$headers, $rows] = sheet_read_upload($_FILES['sheet'] ?? [], EMAIL_MAX_ROWS);
            $sourceName = basename((string) ($_FILES['sheet']['name'] ?? 'sheet'));
            $sheetPayload = sheet_encode_payload($headers, $rows, $sourceName);
            $emailColumn = sheet_guess_column($headers, ['email', 'e-mail', 'mail', 'البريد', 'الايميل', 'الإيميل']);
            $nameColumn = sheet_guess_column($headers, ['name', 'full name', 'الاسم', 'اسم']);
            $subjectColumn = sheet_guess_column($headers, ['subject', 'title', 'الموضوع', 'عنوان']);
            $messageColumn = sheet_guess_column($headers, ['message', 'body', 'text', 'رسالة', 'الرسالة']);
            $attachment = store_attachment($_FILES['attachment'] ?? [], (int) $currentUser['id']);
            $notice = count($rows).' rows loaded. Map the email columns and review the batch.';
        } elseif ($action === 'prepare') {
            $sheetPayload = (string) ($_POST['sheet_payload'] ?? '');
            [$headers, $rows, $sourceName] = sheet_decode_payload($sheetPayload, EMAIL_MAX_ROWS);
            $emailColumn = (string) ($_POST['email_column'] ?? '');
            $nameColumn = (string) ($_POST['name_column'] ?? '');
            $subjectColumn = (string) ($_POST['subject_column'] ?? '');
            $messageColumn = (string) ($_POST['message_column'] ?? '');
            $sharedSubject = trim((string) ($_POST['shared_subject'] ?? ''));
            $sharedMessage = trim((string) ($_POST['shared_message'] ?? ''));
            validate_mapping($headers, $emailColumn, $nameColumn, $subjectColumn, $messageColumn, $sharedSubject, $sharedMessage);
            $emails = build_emails($rows, $emailColumn, $nameColumn, $subjectColumn, $messageColumn, $sharedSubject, $sharedMessage);
            $emailPayload = encode_email_payload($emails);
            $emailSignature = hash_hmac('sha256', $emailPayload, csrf_token());
            $valid = count(array_filter($emails, fn (array $email): bool => $email['error'] === null));
            $notice = $valid.' of '.count($emails).' emails are ready for review.';
            log_event((int) $currentUser['id'], 'email_batch_prepared', 'file='.$sourceName.'; ready='.$valid.'; total='.count($emails));
        } elseif ($action === 'send') {
            if (!smtp_is_configured()) {
                throw new RuntimeException('SMTP is not configured. Ask an administrator to open Email settings.');
            }
            $emailPayload = (string) ($_POST['email_payload'] ?? '');
            $signature = (string) ($_POST['email_signature'] ?? '');
            if (!hash_equals(hash_hmac('sha256', $emailPayload, csrf_token()), $signature)) {
                throw new RuntimeException('The reviewed email batch is invalid or expired.');
            }
            $emails = decode_email_payload($emailPayload);
            $attachment = active_attachment((int) $currentUser['id']);
            $client = new SmtpClient(smtp_settings());
            $sent = 0;
            $failed = [];
            try {
                $client->connect();
                foreach ($emails as $index => $email) {
                    if ($email['error'] !== null) {
                        continue;
                    }
                    try {
                        $client->send($email['email'], $email['subject'], $email['message'], $attachment);
                        $sent++;
                    } catch (RuntimeException $exception) {
                        $failed[] = 'Row '.($index + 2).': '.$exception->getMessage();
                        $client->reset();
                    }
                }
            } finally {
                $client->close();
            }
            remove_active_attachment();
            log_event((int) $currentUser['id'], 'email_batch_sent', 'sent='.$sent.'; failed='.count($failed));
            $notice = $sent.' emails sent successfully.'.($failed ? ' '.count($failed).' failed.' : '');
            if ($failed) {
                $error = implode(' ', array_slice($failed, 0, 5));
            }
            $emails = [];
        }
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

function validate_mapping(array $headers, string $email, string $name, string $subject, string $message, string $sharedSubject, string $sharedMessage): void
{
    if (!in_array($email, $headers, true)) {
        throw new RuntimeException('Choose the column containing email addresses.');
    }
    foreach ([$name, $subject, $message] as $column) {
        if ($column !== '' && !in_array($column, $headers, true)) {
            throw new RuntimeException('Choose only columns from the imported spreadsheet.');
        }
    }
    if ($subject === '' && $sharedSubject === '') {
        throw new RuntimeException('Choose a subject column or enter one shared subject.');
    }
    if ($message === '' && $sharedMessage === '') {
        throw new RuntimeException('Choose a message column or enter one shared message.');
    }
    if (mb_strlen($sharedSubject) > 200 || mb_strlen($sharedMessage) > 10_000) {
        throw new RuntimeException('The shared subject or message is too long.');
    }
}

function build_emails(array $rows, string $emailColumn, string $nameColumn, string $subjectColumn, string $messageColumn, string $sharedSubject, string $sharedMessage): array
{
    $emails = [];
    foreach ($rows as $row) {
        $address = trim((string) ($row[$emailColumn] ?? ''));
        $subject = sheet_render($subjectColumn !== '' ? (string) ($row[$subjectColumn] ?? '') : $sharedSubject, $row);
        $message = sheet_render($messageColumn !== '' ? (string) ($row[$messageColumn] ?? '') : $sharedMessage, $row);
        $error = !filter_var($address, FILTER_VALIDATE_EMAIL) ? 'Invalid email address' : (trim($subject) === '' ? 'Subject is empty' : (trim($message) === '' ? 'Message is empty' : null));
        $emails[] = [
            'email' => $address,
            'name' => $nameColumn !== '' ? trim((string) ($row[$nameColumn] ?? '')) : '',
            'subject' => trim($subject),
            'message' => trim($message),
            'error' => $error,
        ];
    }
    return $emails;
}

function encode_email_payload(array $emails): string
{
    return base64_encode(gzcompress(json_encode($emails, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 6));
}

function decode_email_payload(string $payload): array
{
    if ($payload === '' || strlen($payload) > 8_000_000) {
        throw new RuntimeException('The reviewed email batch is missing or too large.');
    }
    $compressed = base64_decode($payload, true);
    $json = $compressed === false ? false : gzuncompress($compressed, 8_000_000);
    try {
        $emails = $json === false ? null : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $emails = null;
    }
    if (!is_array($emails) || $emails === [] || count($emails) > EMAIL_MAX_ROWS) {
        throw new RuntimeException('The reviewed email batch is invalid.');
    }
    foreach ($emails as $email) {
        if (!is_array($email) || !array_key_exists('email', $email) || !array_key_exists('subject', $email) || !array_key_exists('message', $email) || !array_key_exists('error', $email)) {
            throw new RuntimeException('The reviewed email batch is invalid.');
        }
    }
    return $emails;
}

function store_attachment(array $upload, int $userId): ?array
{
    remove_active_attachment();
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($upload['size'] ?? 0) > EMAIL_MAX_ATTACHMENT) {
        throw new RuntimeException('The attachment could not be uploaded or exceeds 5 MB.');
    }
    $originalName = basename((string) ($upload['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($extension, ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'js', 'vbs', 'ps1', 'sh'], true)) {
        throw new RuntimeException('Executable attachments are not allowed.');
    }
    $directory = local_data_directory().'/attachments';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The secure attachment directory could not be created.');
    }
    $path = $directory.'/'.bin2hex(random_bytes(24)).'.bin';
    if (!move_uploaded_file((string) $upload['tmp_name'], $path)) {
        throw new RuntimeException('The attachment could not be stored securely.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    $attachment = ['path' => $path, 'name' => $originalName, 'mime' => $mime, 'user_id' => $userId, 'created_at' => time()];
    $_SESSION['email_attachment'] = $attachment;
    return $attachment;
}

function active_attachment(int $userId): ?array
{
    $attachment = $_SESSION['email_attachment'] ?? null;
    if (!is_array($attachment) || ($attachment['user_id'] ?? null) !== $userId || time() - (int) ($attachment['created_at'] ?? 0) > 1800 || !is_file((string) ($attachment['path'] ?? ''))) {
        return null;
    }
    return $attachment;
}

function remove_active_attachment(): void
{
    $attachment = $_SESSION['email_attachment'] ?? null;
    if (is_array($attachment) && isset($attachment['path']) && is_file($attachment['path']) && str_starts_with(realpath($attachment['path']) ?: '', realpath(local_data_directory()) ?: '#')) {
        unlink($attachment['path']);
    }
    unset($_SESSION['email_attachment']);
}

function cleanup_attachments(): void
{
    $directory = local_data_directory().'/attachments';
    if (!is_dir($directory)) {
        return;
    }
    foreach (glob($directory.'/*.bin') ?: [] as $path) {
        if (is_file($path) && filemtime($path) < time() - 1800) {
            unlink($path);
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email sender</title><link rel="stylesheet" href="auth.css">
<style>.email-shell{width:min(1120px,calc(100% - 28px));margin:0 auto;padding:38px 0 70px}.steps{display:grid;grid-template-columns:.8fr 1.2fr;gap:20px;align-items:start}.mapping{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.mapping label{margin-top:8px}.email-list{display:grid;gap:10px;margin-top:16px}.email-item{display:grid;grid-template-columns:1fr 1fr 2fr;gap:14px;padding:14px;border:1px solid var(--line);border-radius:14px}.email-item span,.email-item strong{overflow-wrap:anywhere}.email-body{white-space:pre-wrap;max-height:120px;overflow:auto;color:var(--muted);font-size:.88rem}.file-pill{background:#edf5ef;border-radius:12px;padding:10px 12px;margin-top:12px}.invalid{color:var(--red);font-weight:800}@media(max-width:780px){.steps{grid-template-columns:1fr}.mapping{grid-template-columns:1fr}.email-item{grid-template-columns:1fr}}</style></head>
<body><main class="email-shell">
<header class="topbar"><div><div class="brand">Sheet sender</div><h1>Email from Excel</h1><small>Signed in as <?= auth_e($currentUser['name']) ?></small></div><nav><a class="button secondary" href="index.php">WhatsApp</a><?php if ($currentUser['role'] === 'admin'): ?><a class="button secondary" href="email-settings.php">Email settings</a><a class="button secondary" href="users.php">Users</a><?php endif; ?><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><button class="danger" type="submit">Sign out</button></form></nav></header>
<?php if ($error): ?><div class="error" role="alert"><?= auth_e($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="notice" role="status"><?= auth_e($notice) ?></div><?php endif; ?>
<?php if (!smtp_is_configured()): ?><div class="error">Email sending is unavailable until an administrator configures SMTP.<?php if ($currentUser['role'] === 'admin'): ?> <a href="email-settings.php">Open Email settings</a>.<?php endif; ?></div><?php endif; ?>
<div class="steps">
<section class="card"><h2>1. Load spreadsheet</h2><p>Recommended columns: <strong>Email</strong>, <strong>Name</strong>, <strong>Subject</strong>, and <strong>Message</strong>. Maximum <?= EMAIL_MAX_ROWS ?> recipients per batch.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="upload"><label for="sheet">Excel or CSV file</label><input id="sheet" name="sheet" type="file" accept=".xlsx,.csv" required><label for="attachment">Common attachment (optional, maximum 5 MB)</label><input id="attachment" name="attachment" type="file"><div class="actions"><button class="primary" type="submit">Load email list</button></div></form>
<?php if ($attachment): ?><div class="file-pill"><strong>Attachment:</strong> <?= auth_e($attachment['name']) ?></div><?php endif; ?></section>
<section class="card"><h2>2. Map and compose</h2><p>Choose per-row columns, or leave Subject/Message unselected to use shared text with placeholders such as <code>{Name}</code>.</p>
<?php if ($rows): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="prepare"><input type="hidden" name="sheet_payload" value="<?= auth_e($sheetPayload) ?>"><div class="mapping">
<?php foreach ([['email_column','Email column',$emailColumn,true],['name_column','Name column',$nameColumn,false],['subject_column','Subject column',$subjectColumn,false],['message_column','Message column',$messageColumn,false]] as [$field,$label,$selected,$required]): ?><div><label for="<?= $field ?>"><?= $label ?></label><select id="<?= $field ?>" name="<?= $field ?>" <?= $required ? 'required' : '' ?>><option value=""><?= $required ? 'Choose a column' : 'Use shared value / none' ?></option><?php foreach ($headers as $header): ?><option value="<?= auth_e($header) ?>" <?= $selected === $header ? 'selected' : '' ?>><?= auth_e($header) ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
</div><label for="shared_subject">Shared subject</label><input id="shared_subject" name="shared_subject" maxlength="200" value="<?= auth_e($sharedSubject) ?>" placeholder="Used when no Subject column is selected"><label for="shared_message">Shared message</label><textarea id="shared_message" name="shared_message" maxlength="10000" style="width:100%;min-height:150px;border:1px solid var(--line);border-radius:12px;padding:12px;font:inherit" placeholder="Used when no Message column is selected"><?= auth_e($sharedMessage) ?></textarea><div class="actions"><button class="primary" type="submit">Preview emails</button></div></form><?php else: ?><p>Load a spreadsheet to continue.</p><?php endif; ?></section>
</div>
<?php if ($emails): ?><section class="card" style="margin-top:20px"><h2>3. Review and send</h2><p>This action sends real email to every valid address shown below. Check the recipients, subject, message, and attachment first.</p><div class="email-list"><?php foreach ($emails as $index => $email): ?><article class="email-item"><div><strong><?= auth_e($email['name'] ?: 'Row '.($index + 2)) ?></strong><br><span><?= auth_e($email['email']) ?></span><?php if ($email['error']): ?><br><span class="invalid"><?= auth_e($email['error']) ?></span><?php endif; ?></div><div><strong><?= auth_e($email['subject']) ?></strong></div><div class="email-body"><?= auth_e($email['message']) ?></div></article><?php endforeach; ?></div>
<form method="post" onsubmit="return confirm('Send this email batch now?');"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="send"><input type="hidden" name="email_payload" value="<?= auth_e($emailPayload) ?>"><input type="hidden" name="email_signature" value="<?= auth_e($emailSignature) ?>"><div class="actions"><button class="primary" type="submit" <?= smtp_is_configured() ? '' : 'disabled' ?>>Send all valid emails</button></div></form></section><?php endif; ?>
</main></body></html>
