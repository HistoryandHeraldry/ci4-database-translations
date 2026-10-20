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
    protected $description = 'Create a tracked seed and NDJSON data file from application language files.';
    protected $usage = 'translations:generate-seeder [name] [--dry-run]';
    protected $arguments = ['name' => 'Optional descriptive seed name (default: Translations).'];
    protected $options = ['--dry-run' => 'Validate and report without writing files.'];

    public function __construct(LoggerInterface $logger, Commands $commands, private ?TranslationSeedGenerator $generator = null)
    {
        parent::__construct($logger, $commands);
    }

    public function run(array $params): void
    {
        try {
            $result = ($this->generator ?? new TranslationSeedGenerator())->generate(
                isset($params[0]) ? (string) $params[0] : null,
                $this->optionBool('dry-run'),
            );
            CLI::write('Translation seed: ' . $result['seederPath'], 'green');
            CLI::write('NDJSON data: ' . $result['dataPath'], 'green');
            CLI::write('Rows: ' . $result['rowsCount'], 'green');
            if (! $this->optionBool('dry-run')) {
                CLI::write('Run pending tracked seeds with: php spark seed', 'yellow');
            }
        } catch (Throwable $exception) {
            CLI::error($exception->getMessage());
        }
    }

    private function optionBool(string $name): bool
    {
        $value = CLI::getOption($name);
        if ($value === null) {
            return false;
        }
        if ($value === true) {
            return true;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
