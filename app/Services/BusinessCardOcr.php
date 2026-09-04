<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BusinessCardOcr
{
    public function __construct(private BusinessCardTextParser $parser) {}

    public function isAvailable(): bool
    {
        return $this->binaryIsAvailable($this->tesseractBinary());
    }

    /** @return array{data: array<string, mixed>, model: string, response_id: null} */
    public function extract(string $image, string $mimeType): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('The local business-card OCR engine is not installed.');
        }

        if ($image === '') {
            throw new RuntimeException('The business-card image is empty.');
        }

        $directory = storage_path('app/private/business-card-work/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0750);
        $inputPath = $directory.'/input.'.$this->extensionFor($mimeType);
        $preparedPath = $directory.'/prepared.png';

        try {
            if (File::put($inputPath, $image) === false) {
                throw new RuntimeException('The business-card image could not be prepared.');
            }

            $ocrPath = $this->prepareImage($inputPath, $preparedPath) ? $preparedPath : $inputPath;
            $text = $this->mergeOcrResults(
                $this->readText($ocrPath, 6),
                $this->readText($ocrPath, 11),
            );

            if (blank($text)) {
                throw new RuntimeException('No readable text was found. Try a sharper image with less glare.');
            }

            return [
                'data' => $this->parser->parse($text),
                'model' => 'tesseract-local:'.$this->languages(),
                'response_id' => null,
            ];
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function prepareImage(string $inputPath, string $outputPath): bool
    {
        $binary = $this->imageMagickBinary();

        if (! $this->binaryIsAvailable($binary)) {
            return false;
        }

        $process = new Process([
            $binary,
            '-limit',
            'memory',
            '128MiB',
            '-limit',
            'map',
            '256MiB',
            '-limit',
            'disk',
            '512MiB',
            $inputPath,
            '-auto-orient',
            '-strip',
            '-resize',
            '2200x2200>',
            '-colorspace',
            'Gray',
            '-contrast-stretch',
            '1%x1%',
            '-sharpen',
            '0x1',
            $outputPath,
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful() && File::isFile($outputPath);
    }

    private function readText(string $imagePath, int $pageSegmentationMode): string
    {
        $process = new Process([
            $this->tesseractBinary(),
            $imagePath,
            'stdout',
            '-l',
            $this->languages(),
            '--oem',
            '1',
            '--psm',
            (string) $pageSegmentationMode,
            '-c',
            'preserve_interword_spaces=1',
        ]);
        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('The local OCR engine could not read this image.');
        }

        return trim($process->getOutput());
    }

    private function mergeOcrResults(string ...$results): string
    {
        $lines = [];

        foreach ($results as $result) {
            foreach (preg_split('/\R/u', $result) ?: [] as $line) {
                $line = trim((string) preg_replace('/[\p{Z}\s]+/u', ' ', $line));

                if ($line !== '') {
                    $lines[$this->comparisonKey($line)] ??= $line;
                }
            }
        }

        return implode(PHP_EOL, array_values($lines));
    }

    private function comparisonKey(string $line): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}@+.]+/u', '', $line));
    }

    private function binaryIsAvailable(string $binary): bool
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function tesseractBinary(): string
    {
        return (string) config('services.business_card_ocr.tesseract_binary', 'tesseract');
    }

    private function imageMagickBinary(): string
    {
        return (string) config('services.business_card_ocr.imagemagick_binary', 'magick');
    }

    private function languages(): string
    {
        return (string) config('services.business_card_ocr.languages', 'eng+ara+lat');
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
