<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Services;

use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\SeedTracker\Runner as SeedTrackerRunner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class TranslationSeedGenerator
{
    public function __construct(private ?DatabaseTranslations $config = null)
    {
        $this->config ??= config('DatabaseTranslations');
    }

    /** @return array{seederPath:string,dataPath:string,rowsCount:int,class:string,tracked:bool,filesCleared:int} */
    public function generate(?string $name = null, bool $dryRun = false, bool $keepFiles = false): array
    {
        $class = $this->normalizeName($name ?? 'TranslationsSeeder');
        $tracked = class_exists(SeedTrackerRunner::class);
        $filename = $tracked ? date('Y-m-d-His') . '_' . $class : $class;
        $seedPath = rtrim($this->config->seedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename . '.php';
        $dataDirectory = rtrim($this->config->seedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($this->config->seedDataDirectory, DIRECTORY_SEPARATOR);
        $dataPath = $dataDirectory . DIRECTORY_SEPARATOR . $filename . '.ndjson';
        ['rows' => $rows, 'files' => $sourceFiles] = $this->scanWithSourceFiles();

        if ($rows === []) {
            throw new RuntimeException('No translations found under ' . $this->config->languagePath . '.');
        }

        if (! $dryRun) {
            if (is_file($seedPath) || is_file($dataPath)) {
                throw new RuntimeException('Translation seed already exists: ' . basename($seedPath));
            }
            $this->ensureDirectory(dirname($seedPath));
            $this->ensureDirectory($dataDirectory);
            $ndjson = implode('', array_map(static function (array $row): string {
                $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                return $json . "\n";
            }, $rows));
            $this->writeAtomic($dataPath, $ndjson);
            $relativeDataPath = trim($this->config->seedDataDirectory, DIRECTORY_SEPARATOR) . '/' . basename($dataPath);
            $this->writeAtomic($seedPath, $this->renderSeeder($class, $relativeDataPath, hash('sha256', $ndjson)));
            if (! $keepFiles) {
                $this->clearLanguageFiles($sourceFiles);
            }
        }

        return [
            'seederPath' => $seedPath,
            'dataPath' => $dataPath,
            'rowsCount' => count($rows),
            'class' => $class,
            'tracked' => $tracked,
            'filesCleared' => ! $dryRun && ! $keepFiles ? count($sourceFiles) : 0,
        ];
    }

    /** @return list<array{locale:string,namespace:string,key:string,value:string}> */
    public function scan(): array
    {
        return $this->scanWithSourceFiles()['rows'];
    }

    /** @return array{rows:list<array{locale:string,namespace:string,key:string,value:string}>,files:list<string>} */
    private function scanWithSourceFiles(): array
    {
        $languagePath = rtrim($this->config->languagePath, DIRECTORY_SEPARATOR);
        if (! is_dir($languagePath)) {
            throw new RuntimeException('Language directory not found: ' . $languagePath);
        }

        $rows = [];
        $sourceFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($languagePath, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($languagePath) + 1);
            $segments = explode(DIRECTORY_SEPARATOR, $relative);
            if (count($segments) !== 2) {
                continue;
            }
            [$locale] = $segments;
            $namespace = $file->getBasename('.php');
            $values = (static fn(string $path): mixed => require $path)($file->getPathname());
            if (! is_array($values)) {
                throw new RuntimeException('Language file must return an array: ' . $file->getPathname());
            }
            if ($values !== []) {
                $sourceFiles[] = $file->getPathname();
            }
            foreach ($this->flatten($values) as $key => $value) {
                $rows[] = ['locale' => $locale, 'namespace' => $namespace, 'key' => $key, 'value' => $value];
            }
        }

        usort($rows, static fn(array $left, array $right): int => [$left['locale'], $left['namespace'], $left['key']] <=> [$right['locale'], $right['namespace'], $right['key']]);
        sort($sourceFiles, SORT_STRING);
        return ['rows' => $rows, 'files' => $sourceFiles];
    }

    /**
     * @param array<mixed> $values
     * @return array<string,string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new RuntimeException('Translation keys must be non-empty strings.');
            }
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
            } elseif (is_string($value)) {
                $result[$fullKey] = $value;
            } else {
                throw new RuntimeException('Translation value for ' . $fullKey . ' must be a string or nested array.');
            }
        }
        return $result;
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', ' ', trim($name)) ?? '';
        $name = str_replace(' ', '', ucwords($name));
        if ($name === '' || ctype_digit($name[0])) {
            throw new RuntimeException('Seed name must contain letters and may not begin with a number.');
        }
        return $name;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create directory: ' . $directory);
        }
    }

    private function writeAtomic(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write file: ' . $path);
        }
    }

    /** @param list<string> $files */
    private function clearLanguageFiles(array $files): void
    {
        foreach ($files as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Unable to read language file: ' . $path);
            }

            $this->writeAtomic($path, $this->emptyReturnedArray($contents, $path));
        }
    }

    private function emptyReturnedArray(string $contents, string $path): string
    {
        $tokens = token_get_all($contents);
        $offset = 0;
        $returnStart = null;
        $opening = null;
        $closing = null;
        $depth = 0;
        $style = null;
        $braceDepth = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;

            if ($opening !== null && $depth === 0 && $text === ';') {
                return substr($contents, 0, $returnStart) . 'return [];' . substr($contents, $offset + 1);
            }

            if ($returnStart === null && $id === T_RETURN && $braceDepth === 0) {
                $returnStart = $offset;
            } elseif ($returnStart !== null && $opening === null) {
                if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                    $offset += strlen($text);
                    continue;
                }
                if ($text === '[') {
                    $opening = '[';
                    $closing = ']';
                    $style = 'short';
                    $depth = 1;
                } elseif ($id === T_ARRAY) {
                    $opening = 'array';
                    $closing = ')';
                    $style = 'long';
                } else {
                    $returnStart = null;
                }
            } elseif ($opening === 'array' && $depth === 0 && $text === '(') {
                $depth = 1;
            } elseif ($depth > 0) {
                if ($text === ($style === 'short' ? '[' : '(')) {
                    ++$depth;
                } elseif ($text === $closing) {
                    --$depth;
                }
            }

            if ($text === '{') {
                ++$braceDepth;
            } elseif ($text === '}') {
                --$braceDepth;
            }
            $offset += strlen($text);
        }

        throw new RuntimeException('Unable to safely clear language file; no returned array was found: ' . $path);
    }

    private function renderSeeder(string $class, string $relativeDataPath, string $checksum): string
    {
        $namespace = $this->config->seedNamespace;
        $dataPath = var_export(str_replace('\\', '/', $relativeDataPath), true);
        $expectedChecksum = var_export($checksum, true);

        return <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use CodeIgniter\\Database\\Seeder;
use HistoryHeraldry\\DatabaseTranslations\\Repositories\\TranslationRepository;
use RuntimeException;

final class {$class} extends Seeder
{
    public function run(): void
    {
        \$path = __DIR__ . '/' . {$dataPath};
        \$handle = fopen(\$path, 'rb');
        if (\$handle === false) {
            throw new RuntimeException('Translation seed data not found: ' . \$path);
        }
        \$checksum = hash_file('sha256', \$path);
        if (! is_string(\$checksum) || ! hash_equals({$expectedChecksum}, \$checksum)) {
            fclose(\$handle);
            throw new RuntimeException('Translation seed data checksum mismatch: ' . \$path);
        }

        \$repository = new TranslationRepository(\$this->db);
        \$batch = [];
        try {
            while ((\$line = fgets(\$handle)) !== false) {
                \$row = json_decode(\$line, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array(\$row)) {
                    throw new RuntimeException('Invalid translation seed row.');
                }
                \$batch[] = \$row;
                if (count(\$batch) === 500) {
                    \$repository->upsertBatch(\$batch);
                    \$batch = [];
                }
            }
            if (\$batch !== []) {
                \$repository->upsertBatch(\$batch);
            }
        } finally {
            fclose(\$handle);
        }
    }
}
PHP;
    }
}
