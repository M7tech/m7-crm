<?php

declare(strict_types=1);

function sheet_read_upload(array $upload, int $maxRows = 500): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a CSV or XLSX file to import.');
    }
    if (($upload['size'] ?? 0) > 5_000_000) {
        throw new RuntimeException('The spreadsheet is too large. The maximum size is 5 MB.');
    }
    $path = (string) ($upload['tmp_name'] ?? '');
    if (!is_uploaded_file($path)) {
        throw new RuntimeException('The uploaded spreadsheet could not be verified.');
    }
    $extension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $data = match ($extension) {
        'csv' => sheet_read_csv($path, $maxRows),
        'xlsx' => sheet_read_xlsx($path, $maxRows),
        default => throw new RuntimeException('Unsupported spreadsheet. Use .csv or .xlsx.'),
    };
    if (count($data) < 2) {
        throw new RuntimeException('The spreadsheet needs a header row and at least one data row.');
    }
    $headers = sheet_unique_headers(array_shift($data));
    $rows = [];
    foreach (array_slice($data, 0, $maxRows) as $values) {
        $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
        if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }
        $rows[] = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
    }
    if ($rows === []) {
        throw new RuntimeException('No data rows were found in the spreadsheet.');
    }
    return [$headers, $rows];
}

function sheet_read_csv(string $path, int $maxRows): array
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
    $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
    $delimiter = (string) array_search(max($counts), $counts, true);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($rows) <= $maxRows) {
        if ($rows === [] && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function sheet_read_xlsx(string $path, int $maxRows): array
{
    $archive = file_get_contents($path);
    if ($archive === false) {
        throw new RuntimeException('The XLSX workbook could not be opened.');
    }
    $strings = [];
    $shared = sheet_zip_entry($archive, 'xl/sharedStrings.xml');
    if ($shared !== null && ($xml = simplexml_load_string($shared)) !== false) {
        foreach ($xml->si as $item) {
            $strings[] = sheet_xlsx_text($item);
        }
    }
    $sheet = sheet_zip_entry($archive, 'xl/worksheets/sheet1.xml');
    if ($sheet === null || ($xml = simplexml_load_string($sheet)) === false) {
        throw new RuntimeException('The first worksheet could not be read.');
    }
    $result = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
            $column = sheet_column_index($match[0] ?? 'A');
            while (count($row) < $column) {
                $row[] = '';
            }
            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $value = sheet_xlsx_text($cell->is);
            } else {
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $strings[(int) $value] ?? '';
                } elseif ($type === 'b') {
                    $value = $value === '1' ? 'Yes' : 'No';
                }
            }
            $row[$column] = $value;
        }
        ksort($row);
        $result[] = array_values($row);
        if (count($result) > $maxRows) {
            break;
        }
    }
    return $result;
}

function sheet_zip_entry(string $archive, string $wanted): ?string
{
    $tailStart = max(0, strlen($archive) - 65_557);
    $endRelative = strrpos(substr($archive, $tailStart), "\x50\x4b\x05\x06");
    if ($endRelative === false) {
        throw new RuntimeException('The XLSX file is not a valid ZIP archive.');
    }
    $end = substr($archive, $tailStart + $endRelative, 22);
    $position = unpack('V', substr($end, 16, 4))[1];
    $limit = min(strlen($archive), $position + unpack('V', substr($end, 12, 4))[1]);
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
        if ($name === $wanted) {
            if ($uncompressedSize > 10_000_000) {
                throw new RuntimeException('The XLSX worksheet is too large to process safely.');
            }
            $local = substr($archive, $localOffset, 30);
            $dataOffset = $localOffset + 30 + unpack('v', substr($local, 26, 2))[1] + unpack('v', substr($local, 28, 2))[1];
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

function sheet_xlsx_text(SimpleXMLElement $node): string
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

function sheet_column_index(string $letters): int
{
    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }
    return max(0, $index - 1);
}

function sheet_unique_headers(array $raw): array
{
    $headers = [];
    $seen = [];
    foreach ($raw as $index => $header) {
        $base = trim((string) $header) ?: 'Column '.($index + 1);
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

function sheet_guess_column(array $headers, array $needles): string
{
    foreach ($headers as $header) {
        foreach ($needles as $needle) {
            if (str_contains(mb_strtolower($header), mb_strtolower($needle))) {
                return $header;
            }
        }
    }
    return '';
}

function sheet_encode_payload(array $headers, array $rows, string $name): string
{
    return base64_encode(gzcompress(json_encode([$headers, $rows, basename($name)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 6));
}

function sheet_decode_payload(string $payload, int $maxRows = 500): array
{
    if ($payload === '' || strlen($payload) > 8_000_000) {
        throw new RuntimeException('The imported spreadsheet is missing or too large.');
    }
    $compressed = base64_decode($payload, true);
    $json = $compressed === false ? false : gzuncompress($compressed, 8_000_000);
    if ($json === false) {
        throw new RuntimeException('The imported spreadsheet could not be read.');
    }
    try {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('The imported spreadsheet is invalid.');
    }
    if (!is_array($data) || count($data) !== 3 || !is_array($data[0]) || !is_array($data[1]) || count($data[1]) > $maxRows) {
        throw new RuntimeException('The imported spreadsheet is invalid.');
    }
    return [$data[0], $data[1], basename((string) $data[2])];
}

function sheet_render(string $template, array $row): string
{
    $replace = [];
    foreach ($row as $column => $value) {
        $replace['{'.$column.'}'] = (string) $value;
        $replace['{'.mb_strtolower($column).'}'] = (string) $value;
    }
    return strtr($template, $replace);
}
