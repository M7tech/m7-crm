<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BusinessCardScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'public_id', 'company_id', 'uploaded_by_id', 'contact_id', 'status', 'disk', 'image_path',
    'original_name', 'mime_type', 'extracted_data', 'provider_model', 'provider_response_id',
    'attempts', 'error', 'processed_at', 'saved_at', 'expires_at',
])]
class BusinessCardScan extends Model
{
    /** @use HasFactory<BusinessCardScanFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
            'saved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return array<string, string> */
    public function suggestedContactData(): array
    {
        $data = is_array($this->extracted_data) ? $this->extracted_data : [];
        $firstName = $this->stringValue($data, 'first_name');
        $lastName = $this->stringValue($data, 'last_name');
        $jobTitle = $this->stringValue($data, 'job_title');
        $email = $this->stringValue($data, 'email');
        $phone = $this->stringValue($data, 'phone');
        $companyName = $this->stringValue($data, 'company_name');
        $website = $this->stringValue($data, 'website');
        $address = $this->stringValue($data, 'address');
        $extractedNotes = $this->stringValue($data, 'notes');
        $notes = collect([
            $extractedNotes,
            $companyName !== '' ? 'Card company: '.$companyName : null,
            $website !== '' ? 'Website: '.$website : null,
            $address !== '' ? 'Address: '.$address : null,
        ])->filter()->implode(PHP_EOL);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => $jobTitle,
            'email' => $email,
            'phone' => $phone,
            'status' => 'active',
            'notes' => Str::limit($notes, 2000, ''),
        ];
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }
}
