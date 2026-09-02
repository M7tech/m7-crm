<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewContactImportRequest;
use App\Http\Requests\StoreContactImportRequest;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Services\ContactCsvImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactImportController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', ContactImport::class);

        return view('contacts.import', [
            'imports' => ContactImport::query()->with('importedBy')->latest()->limit(10)->get(),
        ]);
    }

    public function preview(PreviewContactImportRequest $request, ContactCsvImportService $service): View
    {
        $file = $request->file('csv_file');
        abort_unless($file instanceof UploadedFile, 422, 'A CSV file is required.');
        $preview = $service->preview($file);

        if ($preview['summary']['total'] === 0) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV does not contain any data rows.']);
        }

        $token = Str::random(64);
        $import = ContactImport::create([
            'imported_by_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'preview_token_hash' => hash('sha256', $token),
            'total_rows' => $preview['summary']['total'],
            'ready_rows' => $preview['summary']['ready'],
            'duplicate_rows' => $preview['summary']['duplicate'],
            'invalid_rows' => $preview['summary']['invalid'],
        ]);

        Cache::put($this->cacheKey($import, $token), [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'rows' => $preview['rows'],
        ], now()->addMinutes(30));

        return view('contacts.import-preview', [
            'import' => $import,
            'token' => $token,
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
        ]);
    }

    public function store(StoreContactImportRequest $request): RedirectResponse
    {
        $import = ContactImport::query()->findOrFail((int) $request->validated('import_id'));
        $this->authorize('update', $import);
        abort_unless(hash_equals($import->preview_token_hash, hash('sha256', $request->validated('token'))), 404);

        $key = $this->cacheKey($import, $request->validated('token'));
        $preview = Cache::get($key);

        if (! is_array($preview)
            || $preview['tenant_id'] !== $request->user()->tenant_id
            || $preview['user_id'] !== $request->user()->id) {
            throw ValidationException::withMessages(['import' => 'The preview expired. Upload the CSV again.']);
        }

        $strategy = $request->validated('duplicate_strategy');

        DB::transaction(function () use ($import, $preview, $strategy): void {
            $lockedImport = ContactImport::query()->lockForUpdate()->findOrFail($import->id);
            abort_unless($lockedImport->status === 'previewed', 409, 'This import has already been processed.');

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $failures = [];

            foreach ($preview['rows'] as $row) {
                if ($row['status'] === 'invalid') {
                    $skipped++;
                    $failures[] = ['row' => $row['row_number'], 'message' => $row['message']];
                    continue;
                }

                $attributes = collect($row['data'])->only([
                    'company_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'status', 'notes',
                ])->all();

                if ($row['status'] === 'duplicate') {
                    if ($strategy === 'update' && $row['duplicate_contact_id'] !== null) {
                        Contact::query()->findOrFail($row['duplicate_contact_id'])->update($attributes);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                Contact::create($attributes);
                $imported++;
            }

            $lockedImport->update([
                'status' => 'completed',
                'duplicate_strategy' => $strategy,
                'imported_rows' => $imported,
                'updated_rows' => $updated,
                'skipped_rows' => $skipped,
                'failure_summary' => $failures,
                'completed_at' => now(),
            ]);
        });

        Cache::forget($key);

        return to_route('contacts.index')->with('status', 'Contact import completed successfully.');
    }

    private function cacheKey(ContactImport $import, string $token): string
    {
        return 'contact-import:'.$import->id.':'.hash('sha256', $token);
    }
}
