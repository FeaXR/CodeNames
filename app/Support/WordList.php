<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Word lists live in resources/words/{code}.txt, one word per line.
 * Dropping in a new file is enough to add a language; give it a display
 * name in config/codenames.php.
 */
class WordList
{
    /**
     * Available languages as code => display name.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        $names = config('codenames.languages', []);

        $available = collect(File::glob(self::directory().'/*.txt'))
            ->mapWithKeys(fn (string $path) => [pathinfo($path, PATHINFO_FILENAME) => true]);

        // Configured languages first, in config order, then any extra files.
        return collect($names)
            ->filter(fn (string $name, string $code) => $available->has($code))
            ->union($available->keys()->mapWithKeys(fn (string $code) => [$code => strtoupper($code)]))
            ->all();
    }

    public static function exists(string $language): bool
    {
        return array_key_exists($language, self::languages());
    }

    /**
     * All unique words for a language.
     *
     * @return list<string>
     */
    public static function words(string $language): array
    {
        if (! self::exists($language)) {
            throw new InvalidArgumentException("Unknown word list [{$language}].");
        }

        return collect(file(self::directory()."/{$language}.txt", FILE_IGNORE_NEW_LINES))
            ->map(fn (string $line) => trim($line))
            ->reject(fn (string $line) => $line === '' || str_starts_with($line, '#'))
            ->map(fn (string $word) => mb_strtoupper($word))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pick a number of distinct random words.
     *
     * @return list<string>
     */
    public static function random(string $language, int $count): array
    {
        $words = self::words($language);

        if (count($words) < $count) {
            throw new InvalidArgumentException("Word list [{$language}] needs at least {$count} words.");
        }

        shuffle($words);

        return array_slice($words, 0, $count);
    }

    protected static function directory(): string
    {
        return resource_path('words');
    }
}
