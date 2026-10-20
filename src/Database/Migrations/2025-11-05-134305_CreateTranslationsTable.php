<?php declare(strict_types=1);

namespace HistoryHeraldry\DatabaseTranslations\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use HistoryHeraldry\DatabaseTranslations\Config\DatabaseTranslations;
use HistoryHeraldry\DatabaseTranslations\Identifiers\IdentifierGeneratorFactory;
use RuntimeException;

final class CreateTranslationsTable extends Migration
{
    public function up(): void
    {
        $config = config('DatabaseTranslations');
        if (! $config instanceof DatabaseTranslations) {
            throw new RuntimeException('Unable to load DatabaseTranslations configuration.');
        }

        IdentifierGeneratorFactory::create($config);
        if ($config->primaryKeyMode === DatabaseTranslations::PRIMARY_KEY_AUTO_INCREMENT) {
            $isSQLite = $this->db->getPlatform() === 'SQLite3';
            $id = ['type' => $isSQLite ? 'INTEGER' : 'BIGINT', 'auto_increment' => true];
            if (! $isSQLite) {
                $id['unsigned'] = true;
            }
        } else {
            $id = ['type' => 'CHAR', 'constraint' => 36, 'null' => false];
        }

        $timestamps = $this->db->getPlatform() === 'MySQLi'
            ? [
                '`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
                '`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ]
            : [
                'created_at' => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
                'updated_at' => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            ];

        $this->forge->addField([
            'id' => $id,
            'locale' => ['type' => 'CHAR', 'constraint' => 10, 'null' => false, 'default' => 'en'],
            'namespace' => ['type' => 'CHAR', 'constraint' => 128, 'null' => false],
            'key' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'value' => ['type' => 'TEXT', 'null' => true],
            ...$timestamps,
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['locale', 'namespace', 'key'], false, true);
        $this->forge->createTable($config->table, true);
    }

    public function down(): void
    {
        $config = config('DatabaseTranslations');
        $this->forge->dropTable($config instanceof DatabaseTranslations ? $config->table : 'translations', true);
    }
}
