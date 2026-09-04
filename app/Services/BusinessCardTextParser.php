<?php

namespace App\Services;

use Illuminate\Support\Str;

class BusinessCardTextParser
{
    /** @return array<string, mixed> */
    public function parse(string $text): array
    {
        $text = $this->normalize($text);
        $lines = array_values(array_filter(
            preg_split('/\R/u', $text) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));
        $email = $this->email($text);
        $phone = $this->phone($text);
        $website = $this->website($text, $email);
        $semanticLines = array_values(array_filter(
            $lines,
            fn (string $line): bool => ! $this->isContactLine($line, $email, $phone, $website),
        ));
        $company = $this->firstMatching($semanticLines, $this->companyPattern());
        $jobTitle = $this->firstMatching($semanticLines, $this->jobTitlePattern());
        $address = $this->firstMatching(
            array_values(array_diff($semanticLines, array_filter([$company, $jobTitle]))),
            $this->addressPattern(),
        );
        $name = $this->name(
            array_values(array_diff($semanticLines, array_filter([$company, $jobTitle, $address]))),
        );
        [$firstName, $lastName] = $this->splitName($name);

        return [
            'first_name' => $this->limit($firstName, 100),
            'last_name' => $this->limit($lastName, 100),
            'job_title' => $this->limit($jobTitle, 120),
            'company_name' => $this->limit($company, 160),
            'email' => $this->limit($email === null ? null : mb_strtolower($email), 255),
            'phone' => $this->limit($phone, 40),
            'website' => $this->limit($website, 255),
            'address' => $this->limit($address, 500),
            'notes' => 'OCR text:'.PHP_EOL.Str::limit($text, 1400, ''),
            'detected_languages' => $this->detectedLanguages($text),
        ];
    }

    private function normalize(string $text): string
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $text = (string) preg_replace('/[\p{Z}\t]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\R */u', PHP_EOL, $text);

        return trim($text);
    }

    private function email(string $text): ?string
    {
        preg_match('/[\p{L}\p{N}.!#$%&\'*+\/=?^_`{|}~-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)+/u', $text, $matches);

        return isset($matches[0]) ? trim($matches[0], '.,;:()[]{}<>') : null;
    }

    private function phone(string $text): ?string
    {
        preg_match_all('/(?<![\p{L}\p{N}])\+?\d[\d ()\-\.]{5,}\d(?![\p{L}\p{N}])/u', $text, $matches);
        $candidates = collect($matches[0] ?? [])->map(function (string $candidate): array {
            $candidate = trim($candidate);
            $digits = (string) preg_replace('/\D/u', '', $candidate);

            return ['value' => $candidate, 'digits' => $digits];
        })->filter(fn (array $candidate): bool => strlen($candidate['digits']) >= 7
            && strlen($candidate['digits']) <= 15)
            ->sortByDesc(fn (array $candidate): string => (str_starts_with($candidate['value'], '+') ? '1' : '0')
                .str_pad((string) strlen($candidate['digits']), 2, '0', STR_PAD_LEFT));

        $bestCandidate = $candidates->first();
        $value = is_array($bestCandidate) ? ($bestCandidate['value'] ?? null) : null;

        return is_string($value) ? $value : null;
    }

    private function website(string $text, ?string $email): ?string
    {
        $searchable = $email === null ? $text : str_replace($email, '', $text);
        preg_match('~(?:https?://|www\.)?[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.(?:com|net|org|io|iq|co|biz|me|info|tech)(?:/[^\s]*)?~iu', $searchable, $matches);

        return isset($matches[0]) ? trim($matches[0], '.,;:()[]{}<>') : null;
    }

    private function isContactLine(string $line, ?string ...$values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && Str::contains($line, $value, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $lines */
    private function firstMatching(array $lines, string $pattern): ?string
    {
        foreach ($lines as $line) {
            if (preg_match($pattern, $line) === 1) {
                return trim($line);
            }
        }

        return null;
    }

    /** @param array<int, string> $lines */
    private function name(array $lines): ?string
    {
        foreach ($lines as $line) {
            $candidate = trim((string) preg_replace($this->honorificPattern(), '', trim($line)));
            $words = preg_split('/\s+/u', $candidate) ?: [];

            if (count($words) >= 2
                && count($words) <= 5
                && preg_match('/\d/u', $candidate) !== 1
                && preg_match($this->companyPattern(), $candidate) !== 1
                && preg_match($this->jobTitlePattern(), $candidate) !== 1
                && preg_match($this->addressPattern(), $candidate) !== 1) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function splitName(?string $name): array
    {
        if ($name === null) {
            return [null, null];
        }

        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $firstName = array_shift($parts);

        return [
            is_string($firstName) ? $firstName : null,
            $parts === [] ? null : implode(' ', $parts),
        ];
    }

    /** @return array<int, string> */
    private function detectedLanguages(string $text): array
    {
        $languages = [];

        if (preg_match('/[ەێۆڕڵڤپچژگک]/u', $text) === 1) {
            $languages[] = 'Kurdish Sorani';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1) {
            $languages[] = 'Arabic script';
        }

        if (preg_match('/[çêîşûÇÊÎŞÛ]/u', $text) === 1) {
            $languages[] = 'Kurdish Kurmanji';
        } elseif (preg_match('/[A-Za-z]/u', $text) === 1) {
            $languages[] = 'English/Latin';
        }

        return array_values(array_unique($languages));
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : Str::limit(trim($value), $length, '');
    }

    private function companyPattern(): string
    {
        return '/\b(company|group|trading|solutions|clinic|hospital|university|factory|llc|ltd|inc)\b|'
            .'شركة|شركه|مؤسسة|مجموعة|عيادة|مستشفى|جامعة|مصنع|کۆمپانیا|كۆمپانيا|گروپ|نەخۆشخانە|زانکۆ/iu';
    }

    private function jobTitlePattern(): string
    {
        return '/\b(manager|director|engineer|sales|marketing|founder|owner|ceo|cfo|cto|doctor|consultant|specialist|architect|accountant)\b|'
            .'rêveber|endezyar|firotan|doktor|'
            .'مدير|مهندس|دكتور|طبيب|مبيعات|تسويق|رئيس|مستشار|بەڕێوەبەر|ئەندازیار|دکتۆر|فرۆشتن|خاوەن/iu';
    }

    private function addressPattern(): string
    {
        return '/\b(address|street|road|building|floor|office|iraq|baghdad|erbil|sulaymaniyah|duhok)\b|'
            .'عراق|بغداد|أربيل|اربيل|السليمانية|دهوك|شارع|طابق|عنوان|کوردستان|هەولێر|سلێمانی|دهۆک|شەقام/iu';
    }

    private function honorificPattern(): string
    {
        return '/^(?:dr\.?|doctor|eng\.?|engineer|mr\.?|mrs\.?|ms\.?|د\.?|دكتور|الدكتور|مهندس|المهندس|دکتۆر)\s+/iu';
    }
}
