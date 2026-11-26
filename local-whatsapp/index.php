<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
$currentUser = require_login();

const MAX_UPLOAD_BYTES = 5_000_000;
const MAX_ROWS = 500;

$error = null;
$notice = null;
$headers = [];
$rows = [];
$sourceName = null;
$sheetPayload = '';
$phoneColumn = '';
$nameColumn = '';
$messageColumn = '';
$countryCode = '964';
$messageTemplate = '';
$prepared = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload') {
        try {
            [$headers, $rows] = readUploadedSheet($_FILES['sheet'] ?? []);
            $sourceName = basename((string) ($_FILES['sheet']['name'] ?? 'sheet'));
            $sheetPayload = encodeSheetPayload($headers, $rows, $sourceName);
            $phoneColumn = guessColumn($headers, ['phone', 'mobile', 'whatsapp', 'telephone', 'tel', 'رقم', 'هاتف']);
            $nameColumn = guessColumn($headers, ['name', 'full name', 'customer', 'contact', 'الاسم', 'اسم']);
            $messageColumn = guessColumn($headers, ['message', 'text', 'body', 'رسالة', 'الرسالة']);
            $notice = count($rows).' rows loaded. Choose the columns and review the messages.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    } elseif ($action === 'prepare') {
        try {
            $sheetPayload = (string) ($_POST['sheet_payload'] ?? '');
            [$headers, $rows, $sourceName] = decodeSheetPayload($sheetPayload);
            $phoneColumn = (string) ($_POST['phone_column'] ?? '');
            $nameColumn = (string) ($_POST['name_column'] ?? '');
            $messageColumn = (string) ($_POST['message_column'] ?? '');
            $countryCode = preg_replace('/\D+/', '', (string) ($_POST['country_code'] ?? '964')) ?: '964';
            $messageTemplate = trim((string) ($_POST['message_template'] ?? ''));

            if (!in_array($phoneColumn, $headers, true)) {
                $error = 'Choose the column containing phone numbers.';
            } elseif ($nameColumn !== '' && !in_array($nameColumn, $headers, true)) {
                $error = 'Choose a valid name column.';
            } elseif ($messageColumn !== '' && !in_array($messageColumn, $headers, true)) {
                $error = 'Choose a valid message column.';
            } elseif ($messageColumn === '' && $messageTemplate === '') {
                $error = 'Choose a message column or write one shared message.';
            } elseif (mb_strlen($messageTemplate) > 2000) {
                $error = 'The message must be 2,000 characters or fewer.';
            } else {
                foreach ($rows as $index => $row) {
                    $rawPhone = (string) ($row[$phoneColumn] ?? '');
                    $phone = normalizePhone($rawPhone, $countryCode);
                    $messageSource = $messageColumn !== '' ? trim((string) ($row[$messageColumn] ?? '')) : $messageTemplate;
                    $message = renderTemplate($messageSource, $row);
                    $rowError = $phone === null ? 'Check phone number' : ($message === '' ? 'Message is empty' : null);

                    $prepared[] = [
                        'row' => $index + 2,
                        'name' => $nameColumn !== '' ? trim((string) ($row[$nameColumn] ?? '')) : '',
                        'raw_phone' => $rawPhone,
                        'phone' => $phone,
                        'message' => $message,
                        'error' => $rowError,
                        'url' => $rowError === null ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
                    ];
                }

                $validCount = count(array_filter($prepared, fn (array $item): bool => $item['url'] !== null));
                $notice = $validCount.' of '.count($prepared).' rows are ready. Each button opens a prefilled WhatsApp message for your confirmation.';
                log_event((int) $currentUser['id'], 'messages_prepared', 'file='.$sourceName.'; ready='.$validCount.'; total='.count($prepared));
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
            $headers = [];
            $rows = [];
            $sourceName = null;
            $sheetPayload = '';
        }
    }
}

function encodeSheetPayload(array $headers, array $rows, string $sourceName): string
{
    $json = json_encode([$headers, $rows, $sourceName], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return base64_encode(gzcompress($json, 6));
}

function decodeSheetPayload(string $payload): array
{
    if ($payload === '' || strlen($payload) > 8_000_000) {
        throw new RuntimeException('The imported sheet is missing or too large. Load it again.');
    }
    $compressed = base64_decode($payload, true);
    $json = $compressed === false ? false : gzuncompress($compressed, 8_000_000);
    if ($json === false) {
        throw new RuntimeException('The imported sheet could not be read. Load it again.');
    }
    try {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('The imported sheet is invalid. Load it again.');
    }
    if (!is_array($data) || count($data) !== 3 || !is_array($data[0]) || !is_array($data[1]) || !is_string($data[2])) {
        throw new RuntimeException('The imported sheet is invalid. Load it again.');
    }
    [$headers, $rows, $sourceName] = $data;
    if ($headers === [] || $rows === [] || count($rows) > MAX_ROWS) {
        throw new RuntimeException('The imported sheet contains no usable contacts. Load it again.');
    }
    return [$headers, $rows, basename($sourceName)];
}

function readUploadedSheet(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a CSV or XLSX file to import.');
    }

    if (($upload['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The file is too large. The maximum size is 5 MB.');
    }

    $originalName = (string) ($upload['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $path = (string) ($upload['tmp_name'] ?? '');

    if (!is_uploaded_file($path)) {
        throw new RuntimeException('The uploaded file could not be verified.');
    }

    $data = match ($extension) {
        'csv' => readCsv($path),
        'xlsx' => readXlsx($path),
        default => throw new RuntimeException('Unsupported file type. Use .csv or .xlsx.'),
    };

    if (count($data) < 2) {
        throw new RuntimeException('The sheet must contain a header row and at least one contact row.');
    }

    $rawHeaders = array_shift($data);
    $headers = uniqueHeaders($rawHeaders);
    $rows = [];

    foreach (array_slice($data, 0, MAX_ROWS) as $values) {
        $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
        if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }
        $rows[] = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
    }

    if ($rows === []) {
        throw new RuntimeException('No contact rows were found in the sheet.');
    }

    return [$headers, $rows];
}

function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The CSV file could not be read.');
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return [];
    }

    $delimiters = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
    $delimiter = (string) array_search(max($delimiters), $delimiters, true);
    rewind($handle);

    $result = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($result) <= MAX_ROWS) {
        if ($result === [] && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }
        $result[] = $row;
    }
    fclose($handle);

    return $result;
}

function readXlsx(string $path): array
{
    $archive = file_get_contents($path);
    if ($archive === false) {
        throw new RuntimeException('The XLSX workbook could not be opened.');
    }

    $sharedStrings = [];
    $sharedXml = readZipEntry($archive, 'xl/sharedStrings.xml');
    if ($sharedXml !== null) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $item) {
                $sharedStrings[] = xlsxText($item);
            }
        }
    }

    $sheetXml = readZipEntry($archive, 'xl/worksheets/sheet1.xml');
    if ($sheetXml === null) {
        throw new RuntimeException('The first worksheet could not be read.');
    }

    $xml = simplexml_load_string($sheetXml);
    if ($xml === false) {
        throw new RuntimeException('The first worksheet is not valid XML.');
    }

    $result = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $reference = (string) $cell['r'];
            preg_match('/^[A-Z]+/', $reference, $match);
            $columnIndex = excelColumnIndex($match[0] ?? 'A');
            while (count($row) < $columnIndex) {
                $row[] = '';
            }

            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $value = xlsxText($cell->is);
            } else {
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'b') {
                    $value = $value === '1' ? 'Yes' : 'No';
                }
            }
            $row[$columnIndex] = $value;
        }
        ksort($row);
        $result[] = array_values($row);
        if (count($result) > MAX_ROWS) {
            break;
        }
    }

    return $result;
}

function readZipEntry(string $archive, string $wantedName): ?string
{
    $endSignature = "\x50\x4b\x05\x06";
    $endOffset = strrpos(substr($archive, -65_557), $endSignature);
    if ($endOffset === false) {
        throw new RuntimeException('The XLSX file is not a valid ZIP archive.');
    }
    $endOffset += max(0, strlen($archive) - 65_557);
    $end = substr($archive, $endOffset, 22);
    $centralOffset = unpack('V', substr($end, 16, 4))[1];
    $centralSize = unpack('V', substr($end, 12, 4))[1];
    $position = $centralOffset;
    $limit = min(strlen($archive), $centralOffset + $centralSize);

    while ($position + 46 <= $limit && substr($archive, $position, 4) === "\x50\x4b\x01\x02") {
        $header = substr($archive, $position, 46);
        $method = unpack('v', substr($header, 10, 2))[1];
        $compressedSize = unpack('V', substr($header, 20, 4))[1];
        $uncompressedSize = unpack('V', substr($header, 24, 4))[1];
        $nameLength = unpack('v', substr($header, 28, 2))[1];
        $extraLength = unpack('v', substr($header, 30, 2))[1];
        $commentLength = unpack('v', substr($header, 32, 2))[1];
        $localOffset = unpack('V', substr($header, 42, 4))[1];
        $name = substr($archive, $position + 46, $nameLength);

        if ($name === $wantedName) {
            if ($uncompressedSize > 10_000_000 || $localOffset + 30 > strlen($archive)) {
                throw new RuntimeException('The XLSX worksheet is too large to process safely.');
            }
            $localHeader = substr($archive, $localOffset, 30);
            if (substr($localHeader, 0, 4) !== "\x50\x4b\x03\x04") {
                throw new RuntimeException('The XLSX worksheet has an invalid ZIP entry.');
            }
            $localNameLength = unpack('v', substr($localHeader, 26, 2))[1];
            $localExtraLength = unpack('v', substr($localHeader, 28, 2))[1];
            $dataOffset = $localOffset + 30 + $localNameLength + $localExtraLength;
            $compressed = substr($archive, $dataOffset, $compressedSize);
            $content = match ($method) {
                0 => $compressed,
                8 => gzinflate($compressed),
                default => throw new RuntimeException('The XLSX compression method is not supported.'),
            };
            if ($content === false || strlen($content) !== $uncompressedSize) {
                throw new RuntimeException('The XLSX worksheet could not be decompressed.');
            }
            return $content;
        }

        $position += 46 + $nameLength + $extraLength + $commentLength;
    }

    return null;
}

function xlsxText(SimpleXMLElement $node): string
{
    $text = '';
    $iterator = new RecursiveIteratorIterator(new SimpleXMLIterator($node->asXML() ?: '<root/>'));
    foreach ($iterator as $name => $value) {
        if ($name === 't') {
            $text .= (string) $value;
        }
    }
    return $text;
}

function excelColumnIndex(string $letters): int
{
    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }
    return max(0, $index - 1);
}

function uniqueHeaders(array $rawHeaders): array
{
    $headers = [];
    $seen = [];
    foreach ($rawHeaders as $index => $rawHeader) {
        $base = trim((string) $rawHeader) ?: 'Column '.($index + 1);
        $candidate = $base;
        $suffix = 2;
        while (isset($seen[mb_strtolower($candidate)])) {
            $candidate = $base.' '.$suffix++;
        }
        $seen[mb_strtolower($candidate)] = true;
        $headers[] = $candidate;
    }
    return $headers;
}

function guessColumn(array $headers, array $needles): string
{
    foreach ($headers as $header) {
        $lower = mb_strtolower($header);
        foreach ($needles as $needle) {
            if (str_contains($lower, mb_strtolower($needle))) {
                return $header;
            }
        }
    }
    return '';
}

function normalizePhone(string $rawPhone, string $countryCode): ?string
{
    $phone = preg_replace('/[^0-9+]/', '', trim($rawPhone));
    if ($phone === '' || $phone === null) {
        return null;
    }

    if (str_starts_with($phone, '+')) {
        $phone = substr($phone, 1);
    } elseif (str_starts_with($phone, '00')) {
        $phone = substr($phone, 2);
    } elseif (str_starts_with($phone, '0')) {
        $phone = $countryCode.ltrim($phone, '0');
    } elseif (strlen($phone) <= 10) {
        $phone = $countryCode.$phone;
    }

    return preg_match('/^[1-9][0-9]{7,14}$/', $phone) === 1 ? $phone : null;
}

function renderTemplate(string $template, array $row): string
{
    $replace = [];
    foreach ($row as $column => $value) {
        $replace['{'.$column.'}'] = (string) $value;
        $replace['{'.mb_strtolower($column).'}'] = (string) $value;
    }
    return strtr($template, $replace);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhatsApp sender · Sheet Sender</title>
    <style>
        :root { color-scheme: light; --ink:#15251f; --muted:#627169; --paper:#f5f7f2; --card:#fff; --line:#dce4dc; --green:#176b4d; --green2:#25d366; --red:#a33838; --amber:#8d6500; }
        * { box-sizing:border-box; }
        body { margin:0; background:radial-gradient(circle at top left,#dff7e8 0,transparent 34rem),var(--paper); color:var(--ink); font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .shell { width:min(1120px,calc(100% - 32px)); margin:0 auto; padding:54px 0 80px; }
        .account-bar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:28px; padding:11px 13px 11px 16px; border:1px solid var(--line); background:rgba(255,255,255,.78); border-radius:15px; }
        .account-bar nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .account-bar form { margin:0; }
        .account-bar button,.account-bar a { padding:8px 11px; font-size:.82rem; }
        header { display:flex; align-items:flex-start; justify-content:space-between; gap:32px; margin-bottom:30px; }
        .eyebrow { color:var(--green); font-weight:800; font-size:.78rem; text-transform:uppercase; letter-spacing:.12em; }
        h1 { font-size:clamp(2.2rem,6vw,4.6rem); line-height:.94; letter-spacing:-.055em; margin:.35rem 0 1rem; max-width:750px; }
        header p { color:var(--muted); font-size:1.05rem; max-width:680px; line-height:1.7; margin:0; }
        .privacy { flex:0 0 230px; background:#163c2e; color:#eafff4; padding:18px; border-radius:18px; font-size:.88rem; line-height:1.55; box-shadow:0 20px 50px rgba(19,63,45,.16); }
        .grid { display:grid; grid-template-columns:minmax(0,.82fr) minmax(0,1.18fr); gap:20px; align-items:start; }
        .card { background:rgba(255,255,255,.92); border:1px solid rgba(214,224,215,.9); border-radius:22px; padding:24px; box-shadow:0 14px 40px rgba(36,64,49,.07); }
        .card h2 { margin:0 0 6px; font-size:1.25rem; }
        .hint { color:var(--muted); margin:0 0 20px; line-height:1.55; font-size:.93rem; }
        label { display:block; font-weight:750; font-size:.88rem; margin:16px 0 7px; }
        input,select,textarea { width:100%; border:1px solid var(--line); background:#fbfcfa; color:var(--ink); border-radius:12px; padding:12px 13px; font:inherit; outline:none; }
        input:focus,select:focus,textarea:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(23,107,77,.12); }
        textarea { min-height:142px; resize:vertical; line-height:1.5; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .row.three { grid-template-columns:repeat(3,1fr); }
        button,.button { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:0; border-radius:12px; padding:12px 17px; font-weight:800; text-decoration:none; cursor:pointer; }
        button.primary,.button.primary { background:var(--green); color:white; box-shadow:0 9px 20px rgba(23,107,77,.18); }
        button.ghost { background:#edf2ed; color:var(--ink); }
        button.link { background:none; color:var(--red); padding:8px 0; }
        .actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-top:18px; }
        .status { border-radius:13px; padding:12px 14px; margin:0 0 20px; font-size:.92rem; line-height:1.5; }
        .status.error { background:#fff0f0; color:var(--red); border:1px solid #f1cece; }
        .status.notice { background:#edfaf3; color:#155f43; border:1px solid #ccebd9; }
        .file-tag { display:flex; justify-content:space-between; gap:15px; align-items:center; background:#f0f5ef; border-radius:14px; padding:12px 14px; margin-bottom:18px; }
        code { color:var(--green); background:#edf5ef; border-radius:5px; padding:2px 5px; }
        .results { grid-column:1/-1; margin-top:2px; }
        .results-head { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-bottom:16px; }
        .message-list { display:grid; gap:12px; }
        .message { display:grid; grid-template-columns:48px minmax(150px,.48fr) minmax(260px,1fr) auto; gap:14px; align-items:center; border:1px solid var(--line); background:#fff; border-radius:17px; padding:15px; }
        .number { width:40px; height:40px; display:grid; place-items:center; border-radius:12px; background:#f1f5f0; color:var(--muted); font-weight:800; }
        .contact strong,.contact span { display:block; overflow-wrap:anywhere; }
        .contact span { color:var(--muted); font-size:.83rem; margin-top:3px; }
        .copy { white-space:pre-wrap; color:#3d4a43; font-size:.9rem; line-height:1.45; max-height:100px; overflow:auto; }
        .invalid { color:var(--red); font-weight:750; font-size:.86rem; }
        .send { background:var(--green2); color:#082d1d; white-space:nowrap; }
        footer { color:var(--muted); font-size:.82rem; margin-top:22px; text-align:center; }
        @media (max-width:900px) { .row.three { grid-template-columns:1fr 1fr; } }
        @media (max-width:780px) { header { display:block; } .privacy { margin-top:20px; } .grid { grid-template-columns:1fr; } .message { grid-template-columns:42px 1fr; } .copy,.message .button,.invalid { grid-column:2; } .account-bar { align-items:flex-start; flex-direction:column; } }
        @media (max-width:520px) { .shell { width:min(100% - 20px,1120px); padding-top:30px; } .row,.row.three { grid-template-columns:1fr; } .card { padding:18px; } }
    </style>
</head>
<body>
<main class="shell">
    <div class="account-bar">
        <span><strong><?= e($currentUser['name']) ?></strong> · <?= e(ucfirst($currentUser['role'])) ?></span>
        <nav>
            <a class="button ghost" href="email.php">Email sender</a>
            <?php if ($currentUser['role'] === 'admin'): ?><a class="button ghost" href="users.php">Manage users</a><?php endif; ?>
            <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="ghost" type="submit">Sign out</button></form>
        </nav>
    </div>
    <header>
        <div>
            <div class="eyebrow">Local PHP utility</div>
            <h1>From spreadsheet to conversation.</h1>
            <p>Load contacts from Excel, personalize each message, and open WhatsApp with the text ready. You stay in control of every send.</p>
        </div>
        <div class="privacy"><strong>Private by design</strong><br>Files are processed only for the current session. Nothing is sent to a database or third-party API.</div>
    </header>

    <?php if ($error): ?><div class="status error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="status notice" role="status"><?= e($notice) ?></div><?php endif; ?>

    <div class="grid">
        <section class="card">
            <h2>1. Load your sheet</h2>
            <p class="hint">Use the first row for the headings <code>Phone</code>, <code>Name</code>, and <code>Message</code>. Up to <?= MAX_ROWS ?> contacts.</p>
            <?php if ($sourceName): ?>
                <div class="file-tag"><span><strong><?= e($sourceName) ?></strong><br><small><?= count($rows) ?> contact rows</small></span></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload">
                <label for="sheet">Excel or CSV file</label>
                <input id="sheet" name="sheet" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <div class="actions"><button class="primary" type="submit">Load contacts</button></div>
            </form>
            <?php if ($rows): ?>
                <div class="actions"><a class="button link" href="./">Clear imported data</a></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>2. Map your columns</h2>
            <p class="hint">Choose the Phone, Name, and Message columns. Each row can have its own message.</p>
            <?php if (!$rows): ?>
                <p class="hint">Load a sheet to choose its columns and prepare messages.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="prepare">
                    <input type="hidden" name="sheet_payload" value="<?= e($sheetPayload) ?>">
                    <div class="row three">
                        <div>
                            <label for="phone_column">Phone column</label>
                            <select id="phone_column" name="phone_column" required>
                                <option value="">Choose a column</option>
                                <?php foreach ($headers as $header): ?><option value="<?= e($header) ?>" <?= $phoneColumn === $header ? 'selected' : '' ?>><?= e($header) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="name_column">Name column (optional)</label>
                            <select id="name_column" name="name_column">
                                <option value="">None</option>
                                <?php foreach ($headers as $header): ?><option value="<?= e($header) ?>" <?= $nameColumn === $header ? 'selected' : '' ?>><?= e($header) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="message_column">Message column</label>
                            <select id="message_column" name="message_column">
                                <option value="">Use one shared message</option>
                                <?php foreach ($headers as $header): ?><option value="<?= e($header) ?>" <?= $messageColumn === $header ? 'selected' : '' ?>><?= e($header) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <label for="country_code">Default country code</label>
                    <input id="country_code" name="country_code" inputmode="numeric" value="<?= e($countryCode) ?>" maxlength="4" required>
                    <label for="message_template">Shared message (only if no Message column is selected)</label>
                    <textarea id="message_template" name="message_template" maxlength="2000" placeholder="Hello {Name}, type the message you want to send."><?= e($messageTemplate) ?></textarea>
                    <p class="hint">Shared messages can use any sheet heading inside braces, for example <code>{Name}</code>.</p>
                    <div class="actions"><button class="primary" type="submit">Prepare messages</button></div>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($prepared): ?>
            <section class="card results">
                <div class="results-head"><div><h2>3. Review and send</h2><p class="hint">WhatsApp opens in a new tab. Check the recipient and press Send there.</p></div></div>
                <div class="message-list">
                    <?php foreach ($prepared as $index => $item): ?>
                        <article class="message">
                            <div class="number"><?= $index + 1 ?></div>
                            <div class="contact"><strong><?= e($item['name'] ?: 'Sheet row '.$item['row']) ?></strong><span><?= e($item['raw_phone']) ?><?= $item['phone'] ? ' · +'.e($item['phone']) : '' ?></span></div>
                            <div class="copy"><?= e($item['message']) ?></div>
                            <?php if ($item['url']): ?>
                                <a class="button send" href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer">Open WhatsApp</a>
                            <?php else: ?>
                                <span class="invalid"><?= e($item['error']) ?></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <footer>No WhatsApp password, token, or API connection is required. Use only with recipients who have agreed to receive your messages.</footer>
</main>
</body>
</html>
