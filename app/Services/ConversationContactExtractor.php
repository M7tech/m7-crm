<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationContactExtractor
{
    public function __construct(private readonly BusinessCardTextParser $businessCardTextParser) {}

    /** @param Collection<int, string> $messages @return array<string, string|null> */
    public function extract(Collection $messages, ?string $participantName): array
    {
        $text = $this->normalize($messages->filter()->implode(PHP_EOL));
        $contactData = $this->businessCardTextParser->parse($text);
        [$firstName, $lastName] = $this->splitName($participantName);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $contactData['email'] ?? null,
            'phone' => $contactData['phone'] ?? null,
            'organization_name' => $this->organization($text),
            'city' => $this->city($text),
            'category' => $this->category($text),
        ];
    }

    private function normalize(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /** @return array{0: string, 1: string|null} */
    private function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['Facebook contact', null];
        }

        $parts = preg_split('/\s+/u', $name, 2) ?: [];

        return [Str::limit($parts[0] ?? $name, 100, ''), isset($parts[1]) ? Str::limit($parts[1], 100, '') : null];
    }

    private function organization(string $text): ?string
    {
        return $this->labeledValue(
            $text,
            'company(?: name)?|business|organization|شركة|شركه|کۆمپانیا|كۆمپانيا',
            160,
        );
    }

    private function city(string $text): ?string
    {
        $labeledCity = $this->labeledValue($text, 'city|town|المدينة|مدينة|شار|شارستان', 100);
        if ($labeledCity !== null) {
            return $labeledCity;
        }

        $cities = [
            'Baghdad' => '/\bBaghdad\b|بغداد/u',
            'Erbil' => '/\bErbil\b|أربيل|اربيل|هەولێر/u',
            'Sulaymaniyah' => '/\bSulaymaniyah\b|السليمانية|سلیمانی|سلێمانی/u',
            'Duhok' => '/\bDuhok\b|دهوك|دهۆک/u',
            'Kirkuk' => '/\bKirkuk\b|كركوك|کەرکووک/u',
            'Basra' => '/\bBasra\b|البصرة|بەسرە/u',
            'Mosul' => '/\bMosul\b|الموصل|مووسڵ/u',
            'Najaf' => '/\bNajaf\b|النجف/u',
            'Karbala' => '/\bKarbala\b|كربلاء|کەربەلا/u',
        ];

        foreach ($cities as $city => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $city;
            }
        }

        return null;
    }

    private function category(string $text): ?string
    {
        $labeledCategory = $this->labeledValue(
            $text,
            'customer type|contact type|category|profession|occupation|type|نوع العميل|الفئة|المهنة|جۆری کڕیار|پۆل|پیشە',
            100,
        );
        if ($labeledCategory !== null) {
            return mb_strtolower($labeledCategory);
        }

        $categories = [
            'plumber' => '/\bplumber\b|سباك|سبّاك|لوله\s*کش/u',
            'engineer' => '/\bengineer\b|مهندس|ئەندازیار/u',
            'trader' => '/\btrader\b|\bdealer\b|تاجر|بازرگان/u',
            'contractor' => '/\bcontractor\b|مقاول|پیمانکار/u',
            'architect' => '/\barchitect\b|معماري|معمار/u',
            'client' => '/\bclient\b|\bcustomer\b|عميل|زبون|کڕیار/u',
        ];

        foreach ($categories as $category => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $category;
            }
        }

        return null;
    }

    private function labeledValue(string $text, string $labels, int $limit): ?string
    {
        preg_match(
            '/(?<![\p{L}\p{N}_])(?:'.$labels.')(?![\p{L}\p{N}_])\s*[:\-–]\s*([^\r\n,;،؛.!?؟]{2,'.$limit.'})/iu',
            $text,
            $matches,
        );

        return isset($matches[1]) ? Str::limit(trim($matches[1]), $limit, '') : null;
    }
}
