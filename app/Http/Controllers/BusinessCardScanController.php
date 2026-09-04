<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBusinessCardContactRequest;
use App\Http\Requests\StoreBusinessCardScanRequest;
use App\Jobs\ProcessBusinessCardScan;
use App\Models\BusinessCardScan;
use App\Models\Company;
use App\Models\Contact;
use App\Services\BusinessCardOcr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BusinessCardScanController extends Controller
{
    public function create(BusinessCardOcr $ocr): View
    {
        $this->authorize('create', BusinessCardScan::class);

        return view('contacts.business-cards.create', [
            'scannerAvailable' => $ocr->isAvailable(),
            'scans' => BusinessCardScan::query()
                ->with(['contact:id,first_name,last_name'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(StoreBusinessCardScanRequest $request, BusinessCardOcr $ocr): RedirectResponse
    {
        if (! $ocr->isAvailable()) {
            throw ValidationException::withMessages([
                'card_image' => 'The local business-card scanner is unavailable. Redeploy the application image.',
            ]);
        }

        $file = $request->file('card_image');
        abort_unless($file instanceof UploadedFile, 422, 'A business-card image is required.');
        $mimeType = (string) $file->getMimeType();
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $directory = 'business-card-scans/'.$request->user()->tenant_id;
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'card_image' => 'The private image could not be stored. Please try again.',
            ]);
        }

        try {
            $scan = DB::transaction(fn (): BusinessCardScan => BusinessCardScan::create([
                'public_id' => (string) Str::uuid(),
                'uploaded_by_id' => $request->user()->id,
                'status' => 'queued',
                'disk' => 'local',
                'image_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $mimeType,
                'expires_at' => now()->addDay(),
            ]));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        ProcessBusinessCardScan::dispatch($scan->id, $scan->tenant_id);

        return to_route('contacts.business-cards.show', $scan)
            ->with('status', 'Business card uploaded. Contact details are being extracted.');
    }

    public function show(string $businessCardScan): View
    {
        $businessCardScan = $this->scan($businessCardScan);
        $this->authorize('view', $businessCardScan);
        $businessCardScan->load(['contact', 'company']);
        $companies = Company::query()->orderBy('name')->get(['id', 'name']);
        $cardCompany = trim((string) data_get($businessCardScan->extracted_data, 'company_name'));
        $matchedCompany = $cardCompany === '' ? null : $companies->first(
            fn (Company $company): bool => Str::lower(trim($company->name)) === Str::lower($cardCompany),
        );

        return view('contacts.business-cards.show', [
            'scan' => $businessCardScan,
            'companies' => $companies,
            'suggested' => $businessCardScan->suggestedContactData(),
            'suggestedCompanyId' => $businessCardScan->company_id ?? $matchedCompany?->id,
        ]);
    }

    public function image(string $businessCardScan): StreamedResponse
    {
        $businessCardScan = $this->scan($businessCardScan);
        $this->authorize('view', $businessCardScan);
        abort_if(blank($businessCardScan->image_path), 404);
        abort_unless(Storage::disk($businessCardScan->disk)->exists($businessCardScan->image_path), 404);

        return Storage::disk($businessCardScan->disk)->response(
            $businessCardScan->image_path,
            null,
            ['Content-Type' => $businessCardScan->mime_type, 'Cache-Control' => 'private, no-store'],
        );
    }

    public function save(SaveBusinessCardContactRequest $request, string $businessCardScan): RedirectResponse
    {
        $businessCardScan = $this->scan($businessCardScan);
        $this->authorize('update', $businessCardScan);
        $contact = DB::transaction(function () use ($request, $businessCardScan): Contact {
            $scan = BusinessCardScan::query()->lockForUpdate()->findOrFail($businessCardScan->id);
            abort_unless($scan->status === 'completed', 409, 'This scan has already been saved or is not ready.');

            $contact = Contact::create($request->validated());
            $scan->update([
                'company_id' => $contact->company_id,
                'contact_id' => $contact->id,
                'status' => 'saved',
                'extracted_data' => null,
                'saved_at' => now(),
                'error' => null,
            ]);

            return $contact;
        });

        if (filled($businessCardScan->image_path)) {
            $storage = Storage::disk($businessCardScan->disk);

            if (! $storage->exists($businessCardScan->image_path)
                || $storage->delete($businessCardScan->image_path)) {
                $businessCardScan->update(['image_path' => null]);
            } else {
                $businessCardScan->update(['expires_at' => now()]);
            }
        }

        return to_route('contacts.show', $contact)
            ->with('status', 'Contact saved from the business card.');
    }

    public function retry(string $businessCardScan): RedirectResponse
    {
        $businessCardScan = $this->scan($businessCardScan);
        $this->authorize('update', $businessCardScan);
        abort_unless($businessCardScan->status === 'failed', 409, 'Only a failed scan can be retried.');

        $businessCardScan->update(['status' => 'queued', 'error' => null]);
        ProcessBusinessCardScan::dispatch($businessCardScan->id, $businessCardScan->tenant_id);

        return to_route('contacts.business-cards.show', $businessCardScan)
            ->with('status', 'The business card was queued again.');
    }

    public function destroy(string $businessCardScan): RedirectResponse
    {
        $businessCardScan = $this->scan($businessCardScan);
        $this->authorize('delete', $businessCardScan);

        if (filled($businessCardScan->image_path)) {
            Storage::disk($businessCardScan->disk)->delete($businessCardScan->image_path);
        }

        $businessCardScan->delete();

        return to_route('contacts.business-cards.create')
            ->with('status', 'Business-card scan deleted.');
    }

    private function scan(string $publicId): BusinessCardScan
    {
        return BusinessCardScan::query()->where('public_id', $publicId)->firstOrFail();
    }
}
