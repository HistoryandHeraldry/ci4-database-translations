<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Language;

use CodeIgniter\Language\Language;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Repositories\TranslationRepository;
use Throwable;

class DatabaseLanguage extends Language
{
    protected TranslationRepository $translations;
    protected DatabaseTranslations $databaseTranslationsConfig;

    public function __construct(string $locale, ?TranslationRepository $translations = null, ?DatabaseTranslations $config = null)
    {
        parent::__construct($locale);
        $this->databaseTranslationsConfig = $config ?? config('DatabaseTranslations');
        $this->translations = $translations ?? new TranslationRepository(config: $this->databaseTranslationsConfig);
    }

    protected function load(string $file, string $locale, bool $return = false)
    {
        if (! array_key_exists($locale, $this->loadedFiles)) {
            $this->loadedFiles[$locale] = [];
        }
        if (in_array($file, $this->loadedFiles[$locale], true)) {
            return $return ? ($this->language[$locale][$file] ?? []) : [];
        }

        $this->language[$locale] ??= [];
        $this->language[$locale][$file] ??= [];
        $fileStrings = $this->requireFile("Language/{$locale}/{$file}.php");
        $databaseStrings = $this->databaseTranslations($locale, $file);
        $merged = $this->databaseTranslationsConfig->filesOverrideDatabase
            ? array_replace_recursive($databaseStrings, $fileStrings)
            : array_replace_recursive($fileStrings, $databaseStrings);

        if ($return) {
            return $merged;
        }

        $this->loadedFiles[$locale][] = $file;
        $this->language[$locale][$file] = $merged;

        return null;
    }

    /** @return array<string,mixed> */
    protected function databaseTranslations(string $locale, string $namespace): array
    {
        try {
            $flat = $this->translations->getForLocaleAndNamespaces($locale, [$namespace]);
        } catch (Throwable $exception) {
            log_message('error', 'Database translations unavailable: {message}', ['message' => $exception->getMessage()]);
            return [];
        }

        $nested = [];
        $prefix = $namespace . '.';
        foreach ($flat as $key => $rawValue) {
            if (str_starts_with($key, $prefix)) {
                $key = substr($key, strlen($prefix));
            }
            $decoded = json_decode($rawValue, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $rawValue;
            $reference =& $nested;
            foreach (explode('.', $key) as $index => $part) {
                if ($index === count(explode('.', $key)) - 1) {
                    $reference[$part] = $value;
                } else {
                    if (! isset($reference[$part]) || ! is_array($reference[$part])) {
                        $reference[$part] = [];
                    }
                    $reference =& $reference[$part];
                }
            }
            unset($reference);
        }

        return $nested;
    }
}
