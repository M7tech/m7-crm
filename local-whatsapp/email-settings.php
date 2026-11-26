<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/smtp.php';
$currentUser = require_admin();
$error = null;
$notice = null;
$settings = smtp_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $host = trim((string) ($_POST['host'] ?? ''));
    $port = filter_var($_POST['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $encryption = (string) ($_POST['encryption'] ?? 'tls');
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $fromEmail = trim((string) ($_POST['from_email'] ?? ''));
    $fromName = trim((string) ($_POST['from_name'] ?? ''));
    if ($host === '' || strlen($host) > 255 || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
        $error = 'Enter a valid SMTP host name.';
    } elseif ($port === false) {
        $error = 'Enter a valid SMTP port.';
    } elseif (!in_array($encryption, ['tls', 'ssl'], true)) {
        $error = 'Choose TLS or SSL encryption.';
    } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid sender email address.';
    } elseif ($fromName === '' || mb_strlen($fromName) > 100) {
        $error = 'Enter a sender name of 100 characters or fewer.';
    } else {
        $savedPassword = $password !== '' ? encrypt_local_secret($password) : setting('smtp_password');
        save_settings([
            'smtp_host' => $host,
            'smtp_port' => (string) $port,
            'smtp_encryption' => $encryption,
            'smtp_username' => $username,
            'smtp_password' => $savedPassword,
            'smtp_from_email' => $fromEmail,
            'smtp_from_name' => $fromName,
        ]);
        log_event((int) $currentUser['id'], 'smtp_settings_updated', 'host='.$host.'; from='.$fromEmail);
        $settings = smtp_settings();
        $notice = 'SMTP settings were saved securely.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email settings</title><link rel="stylesheet" href="auth.css"></head>
<body><main class="auth-shell"><section class="card">
<div class="brand">Sheet sender</div><h1>Email settings</h1><p>Configure the SMTP account used by authorized senders. The password is encrypted in local storage and is never displayed again.</p>
<?php if ($error): ?><div class="error" role="alert"><?= auth_e($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="notice" role="status"><?= auth_e($notice) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>">
<label for="host">SMTP host</label><input id="host" name="host" value="<?= auth_e($settings['host']) ?>" placeholder="smtp.example.com" required>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label for="port">Port</label><input id="port" name="port" type="number" min="1" max="65535" value="<?= auth_e($settings['port']) ?>" required></div><div><label for="encryption">Encryption</label><select id="encryption" name="encryption"><option value="tls" <?= $settings['encryption'] === 'tls' ? 'selected' : '' ?>>TLS (usually 587)</option><option value="ssl" <?= $settings['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (usually 465)</option></select></div></div>
<label for="username">SMTP username</label><input id="username" name="username" value="<?= auth_e($settings['username']) ?>" autocomplete="username">
<label for="password">SMTP password</label><input id="password" name="password" type="password" autocomplete="new-password" placeholder="<?= setting('smtp_password') !== '' ? 'Leave blank to keep the saved password' : 'Required when the server uses authentication' ?>">
<label for="from_email">From email</label><input id="from_email" name="from_email" type="email" value="<?= auth_e($settings['from_email']) ?>" required>
<label for="from_name">From name</label><input id="from_name" name="from_name" value="<?= auth_e($settings['from_name']) ?>" maxlength="100" required>
<div class="actions"><button class="primary" type="submit">Save settings</button><a class="button secondary" href="email.php">Email sender</a></div>
</form></section></main></body></html>
