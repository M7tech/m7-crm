<?php

namespace Tests\Unit;

use App\Services\ConversationContactExtractor;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationContactExtractorTest extends TestCase
{
    #[DataProvider('messageProvider')]
    public function test_it_suggests_multilingual_contact_details(string $message, string $city, string $category): void
    {
        $profile = app(ConversationContactExtractor::class)->extract(new Collection([$message]), 'Karim Ali');

        $this->assertSame('Karim', $profile['first_name']);
        $this->assertSame('Ali', $profile['last_name']);
        $this->assertSame($city, $profile['city']);
        $this->assertSame($category, $profile['category']);
        $this->assertSame('07701234567', $profile['phone']);
        $this->assertSame('Atlas Trading', $profile['organization_name']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function messageProvider(): array
    {
        return [
            'English' => ['I am an engineer in Erbil. Phone 07701234567. Company: Atlas Trading', 'Erbil', 'engineer'],
            'Arabic' => ['أنا مهندس في أربيل. هاتفي ٠٧٧٠١٢٣٤٥٦٧. شركة: Atlas Trading', 'Erbil', 'engineer'],
            'Sorani' => ['من ئەندازیارم لە هەولێر. ٠٧٧٠١٢٣٤٥٦٧ کۆمپانیا: Atlas Trading', 'Erbil', 'engineer'],
        ];
    }

    public function test_it_accepts_explicit_custom_city_and_category_values(): void
    {
        $profile = app(ConversationContactExtractor::class)->extract(new Collection([
            'City: Halabja'.PHP_EOL.'Type: Interior Designer'.PHP_EOL.'Phone: 07501234567',
        ]), 'Sara Ahmed');

        $this->assertSame('Halabja', $profile['city']);
        $this->assertSame('interior designer', $profile['category']);
        $this->assertSame('07501234567', $profile['phone']);
    }
}
