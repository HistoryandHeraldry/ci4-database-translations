<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use HistoryHeraldry\DatabaseTranslations\Services\TranslationSeedGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

final class GenerateTranslationSeed extends BaseCommand
{
    protected $group = 'Translations';
    protected $name = 'translations:generate-seeder';
    protected $description = 'Create a translation seeder and NDJSON data file from application language files.';
    protected $usage = 'translations:generate-seeder [name] [--dry-run] [--keep-files]';
    protected $arguments = ['name' => 'Optional seeder class name (default: TranslationsSeeder).'];
    protected $options = [
        '--dry-run'   => 'Preview the generated artifacts without writing or clearing files.',
        '--keep-files' => 'Generate the seed artifacts without clearing the source language files.',
    ];

    public function __construct(LoggerInterface $logger, Commands $commands, private ?TranslationSeedGenerator $generator = null)
    {
        parent::__construct($logger, $commands);
    }

    public function run(array $params): void
    {
        try {
            $dryRun = $this->hasOption('dry-run');
            $result = ($this->generator ?? new TranslationSeedGenerator())->generate(
                name: isset($params[0]) ? (string) $params[0] : null,
                dryRun: $dryRun,
                keepFiles: $this->hasOption('keep-files'),
            );
            CLI::write('Translation seed: ' . $result['seederPath'], 'green');
            CLI::write('NDJSON data: ' . $result['dataPath'], 'green');
            CLI::write('Rows: ' . $result['rowsCount'], 'green');
            if ($dryRun) {
                CLI::write('Dry run: no files were written or cleared.', 'yellow');
            } else {
                CLI::write('Language files cleared: ' . $result['filesCleared'], 'green');
                CLI::write(
                    $result['tracked']
                        ? 'Run pending tracked seeds with: php spark seed'
                        : 'Run this seeder with: php spark db:seed ' . $result['class'],
                    'yellow',
                );
            }
        } catch (Throwable $exception) {
            CLI::error($exception->getMessage());
        }
    }

    private function hasOption(string $name): bool
    {
        return CLI::getOption($name) !== null;
    }
}
