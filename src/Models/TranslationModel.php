<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Contracts\IdentifierGenerator;
use HistoryHeraldry\DatabaseTranslations\Identifiers\IdentifierGeneratorFactory;

/**
 * Optional conventional CI4 model for applications that want direct CRUD access.
 *
 * Runtime translation loading and generated seeders use TranslationRepository;
 * consumers are not required to use or extend this model.
 */
class TranslationModel extends Model
{
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = ['locale', 'namespace', 'key', 'value'];
    protected $validationRules = [
        'locale' => 'required|max_length[10]',
        'namespace' => 'required|max_length[128]',
        'key' => 'required|max_length[255]',
        'value' => 'permit_empty|string',
    ];
    protected $beforeInsert = ['assignIdentifier'];
    protected $beforeInsertBatch = ['assignBatchIdentifiers'];

    private DatabaseTranslations $databaseTranslationsConfig;
    private IdentifierGenerator|null|false $identifierGenerator = false;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?ValidationInterface $validation = null,
        ?DatabaseTranslations $config = null,
    ) {
        $this->databaseTranslationsConfig = $config ?? config('DatabaseTranslations');
        $this->DBGroup = $this->databaseTranslationsConfig->databaseGroup;
        $this->table = $this->databaseTranslationsConfig->table;
        $this->useAutoIncrement = $this->databaseTranslationsConfig->primaryKeyMode
            === DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT;

        parent::__construct($db, $validation);
    }

    /**
     * @param array{data: array<string, mixed>} $event
     * @return array{data: array<string, mixed>}
     */
    protected function assignIdentifier(array $event): array
    {
        if (! $this->useAutoIncrement && ! array_key_exists($this->primaryKey, $event['data'])) {
            $event['data'][$this->primaryKey] = $this->identifierGenerator()->generate();
        }

        return $event;
    }

    /**
     * @param array{data: list<array<string, mixed>>} $event
     * @return array{data: list<array<string, mixed>>}
     */
    protected function assignBatchIdentifiers(array $event): array
    {
        if ($this->useAutoIncrement) {
            return $event;
        }

        $generator = $this->identifierGenerator();
        foreach ($event['data'] as &$row) {
            if (! array_key_exists($this->primaryKey, $row)) {
                $row[$this->primaryKey] = $generator->generate();
            }
        }
        unset($row);

        return $event;
    }

    private function identifierGenerator(): IdentifierGenerator
    {
        if ($this->identifierGenerator === false) {
            $this->identifierGenerator = IdentifierGeneratorFactory::create($this->databaseTranslationsConfig);
        }

        if (! $this->identifierGenerator instanceof IdentifierGenerator) {
            throw new \LogicException('Translation identifier generation is unavailable in auto-increment mode.');
        }

        return $this->identifierGenerator;
    }
}
