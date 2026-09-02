<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactCsvImportService
{
    public const MAX_ROWS = 500;

    /** @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function preview(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV file could not be read.']);
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            $headers = array_map($this->normalizeHeader(...), $header ?: []);
            $required = ['company', 'first_name'];

            if (array_diff($required, $headers) !== []) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV must contain company and first_name columns.',
                ]);
            }

            $allowed = ['company', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'status', 'notes'];
            $headers = array_map(fn (string $header): string => in_array($header, $allowed, true) ? $header : '', $headers);
            $companies = Company::query()->orderBy('name')->get()->groupBy(fn (Company $company) => Str::lower(trim($company->name)));
            $existing = Contact::query()->get(['id', 'email', 'phone']);
            $emails = $existing->filter(fn (Contact $contact) => filled($contact->email))->keyBy(fn (Contact $contact) => Str::lower(trim((string) $contact->email)));
            $phones = $existing->filter(fn (Contact $contact) => filled($contact->phone))->keyBy(fn (Contact $contact) => $this->phoneKey((string) $contact->phone));
            $seen = [];
            $rows = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($rowNumber > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['csv_file' => 'A CSV import is limited to '.self::MAX_ROWS.' data rows.']);
                }

                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $values = array_pad($values, count($headers), null);
                $data = [];
                foreach ($headers as $index => $name) {
                    if ($name !== '') {
                        $data[$name] = trim((string) ($values[$index] ?? '')) ?: null;
                    }
                }

                $data['email'] = filled($data['email'] ?? null) ? Str::lower((string) $data['email']) : null;
                $data['status'] = Str::lower((string) ($data['status'] ?? 'active')) ?: 'active';
                $companyMatches = $companies->get(Str::lower((string) ($data['company'] ?? '')), collect());
                $validator = Validator::make($data, [
                    'company' => ['required', 'string', 'max:160'],
                    'first_name' => ['required', 'string', 'max:100'],
                    'last_name' => ['nullable', 'string', 'max:100'],
                    'job_title' => ['nullable', 'string', 'max:120'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:40'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                    'notes' => ['nullable', 'string', 'max:2000'],
                ]);
                $errors = $validator->errors()->all();

                if ($companyMatches->count() !== 1) {
                    $errors[] = $companyMatches->isEmpty() ? 'Company was not found in this workspace.' : 'Company name is ambiguous.';
                }

                $data['company_id'] = $companyMatches->first()?->id;
                $duplicate = $data['email'] ? $emails->get($data['email']) : null;
                $duplicate ??= filled($data['phone'] ?? null) ? $phones->get($this->phoneKey((string) $data['phone'])) : null;
                $identity = $data['email'] ? 'email:'.$data['email'] : (filled($data['phone'] ?? null) ? 'phone:'.$this->phoneKey((string) $data['phone']) : null);

                if ($identity !== null && isset($seen[$identity])) {
                    $errors[] = 'Duplicate row in this CSV file.';
                }
                if ($identity !== null) {
                    $seen[$identity] = true;
                }

                $status = $errors !== [] ? 'invalid' : ($duplicate ? 'duplicate' : 'ready');
                $rows[] = [
                    'row_number' => $rowNumber,
                    'data' => $data,
                    'status' => $status,
                    'message' => $errors !== [] ? implode(' ', $errors) : ($duplicate ? 'Matches an existing contact.' : 'Ready to import.'),
                    'duplicate_contact_id' => $duplicate?->id,
                ];
            }
        } finally {
            fclose($handle);
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'ready' => count(array_filter($rows, fn (array $row) => $row['status'] === 'ready')),
                'duplicate' => count(array_filter($rows, fn (array $row) => $row['status'] === 'duplicate')),
                'invalid' => count(array_filter($rows, fn (array $row) => $row['status'] === 'invalid')),
            ],
        ];
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->replace("\xEF\xBB\xBF", '')->trim()->lower()->replace([' ', '-'], '_')->toString();
    }

    private function phoneKey(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone) ?: $phone;
    }
}
