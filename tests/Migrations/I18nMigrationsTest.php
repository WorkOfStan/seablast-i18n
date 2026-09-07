<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Migrations;

use Phinx\Config\FeatureFlags;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Phinx\Migration\IrreversibleMigrationException;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\Assert;

final class I18nMigrationsTest extends TestCase
{
    /** @var MysqlAdapter */
    private $mysql;
    /** @var TablePrefixAdapter */
    private $adapter;

    public function testMigrationsAgainstBothHistoricalNullDefaults(): void
    {
        if (getenv('I18N_MIGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set I18N_MIGRATION_TESTS=1 to create and drop an isolated MySQL test database.');
        }
        $root = dirname(__DIR__, 2);
        require_once $root . '/conf/db/migrations/20250116140457_localised_items.php';
        require_once $root . '/conf/db/migrations/20260907000000_enforce_i18n_required_columns_not_null.php';
        require_once $root . '/conf/db/migrations/20260907000001_scope_localised_item_uniqueness_by_type.php';
        $configPath = $root . '/conf/phinx.local.php';
        $config = require is_file($configPath) ? $configPath : $root . '/conf/phinx.dist.php';
        Assert::isArray($config);
        $environments = $config['environments'];
        Assert::isArray($environments);
        $options = $this->stringKeyedArray($environments['testing']);
        Assert::string($options['host']);
        Assert::string($options['user']);
        Assert::string($options['pass']);
        $port = $options['port'] ?? 3306;
        Assert::scalar($port);
        // Never migrate or drop the configured database: use only its connection credentials.
        $database = 'i18n_migration_test_' . bin2hex(random_bytes(6));
        $admin = new \PDO(
            'mysql:host=' . $options['host'] . ';port=' . $port,
            $options['user'],
            $options['pass'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec('CREATE DATABASE `' . $database . '`');
        $oldDefault = FeatureFlags::$columnNullDefault;
        try {
            $options['name'] = $database;
            $options['table_prefix'] = 'probe_';
            $options['table_suffix'] = '_test';
            $options['fetch_mode'] = 'assoc';
            $this->mysql = new MysqlAdapter($options);
            $this->mysql->connect();
            $this->adapter = new TablePrefixAdapter($this->mysql);
            foreach ([true, false] as $nullableDefault) {
                FeatureFlags::$columnNullDefault = $nullableDefault;
                $original = new \LocalisedItems('testing', 20250116140457);
                $original->setAdapter($this->adapter);
                $original->change();
                $this->verifyMigration($nullableDefault);
                foreach (['localised_items', 'localised_item_types', 'translations'] as $table) {
                    $original->table($table)->drop()->save();
                }
            }
        } finally {
            FeatureFlags::$columnNullDefault = $oldDefault;
            $admin->exec('DROP DATABASE `' . $database . '`');
        }
    }

    private function verifyMigration(bool $nullableDefault): void
    {
        $required = new \EnforceI18nRequiredColumnsNotNull('testing', 20260907000000);
        $required->setAdapter($this->adapter);
        $index = new \ScopeLocalisedItemUniquenessByType('testing', 20260907000001);
        $index->setAdapter($this->adapter);
        $this->mysql->execute("INSERT INTO probe_localised_item_types_test (name) VALUES ('page'), ('blog')");
        $this->mysql->execute(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (1, NULL, '', 1)"
        );
        if ($nullableDefault) {
            $this->mysql->execute(
                'INSERT INTO probe_translations_test (language, translation_key, translation_value) '
                . 'VALUES (NULL, NULL, NULL)'
            );
        }
        $before = $this->schema();
        try {
            $required->up();
            $this->fail('Existing NULL must stop migration.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('localised_items.language', $error->getMessage());
            if ($nullableDefault) {
                $this->assertStringContainsString('translations.translation_value', $error->getMessage());
            }
        }
        $this->assertSame($before, $this->schema(), 'NULL preflight must leave the entire schema unchanged.');
        $this->mysql->execute("UPDATE probe_localised_items_test SET language = 'en'");
        $this->mysql->execute(
            "UPDATE probe_translations_test SET language = 'en', translation_key = '', translation_value = ''"
        );
        $required->up();
        $after = $this->schema();
        foreach ($after as $table => $columns) {
            foreach ($columns as $position => $column) {
                $optional = $table === 'localised_items'
                    && in_array($column['Field'], ['parent_id', 'content', 'friendly_url'], true);
                $this->assertSame($optional ? 'YES' : 'NO', $column['Null']);
                // Compare all attributes except nullability (including defaults, comments and auto-increment).
                unset($column['Null'], $before[$table][$position]['Null']);
                $this->assertSame($before[$table][$position], $column);
            }
        }
        $required->up();
        $this->assertSame($after, $this->schema(), 'Reapplying must preserve the required schema.');
        $this->assertRejectedSql(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (2, NULL, '', 1)"
        );
        $this->assertRejectedSql(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (2, 'en', '', 999)"
        );
        $index->up();
        $this->mysql->execute(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (1, 'en', '', 2)"
        );
        $this->assertRejectedSql(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (1, 'en', '', 2)"
        );
        try {
            $index->down();
            $this->fail('Collisions must prevent rollback.');
        } catch (IrreversibleMigrationException $error) {
            $this->assertStringContainsString('duplicates', $error->getMessage());
        }
        $this->assertTrue(
            $index->table('localised_items')->hasIndexByName('idx_localised_item_language_type_unique')
        );
        $this->mysql->execute('UPDATE probe_localised_item_types_test SET id = 3 WHERE id = 2');
        $row = $this->mysql->fetchRow('SELECT item_type_id FROM probe_localised_items_test WHERE item_type_id = 3');
        $this->assertNotFalse($row, 'ON UPDATE CASCADE must survive.');
        $this->mysql->execute('DELETE FROM probe_localised_item_types_test WHERE id = 3');
        $this->assertFalse(
            $this->mysql->fetchRow('SELECT 1 FROM probe_localised_items_test WHERE item_type_id = 3'),
            'ON DELETE CASCADE must survive.'
        );
        $index->down();
        $this->assertFalse(
            $index->table('localised_items')->hasIndexByName('idx_localised_item_language_type_unique')
        );
        $this->mysql->execute("INSERT INTO probe_localised_item_types_test (id, name) VALUES (2, 'blog')");
        $this->assertRejectedSql(
            "INSERT INTO probe_localised_items_test (item_id, language, title, item_type_id) VALUES (1, 'en', '', 2)"
        );
        $beforeDown = $this->schema();
        try {
            $required->down();
            $this->fail('Nullability rollback must be refused.');
        } catch (IrreversibleMigrationException $error) {
            $this->assertStringContainsString('Phinx version', $error->getMessage());
        }
        $this->assertSame($beforeDown, $this->schema());
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function schema(): array
    {
        $schema = [];
        foreach (['translations', 'localised_item_types', 'localised_items'] as $table) {
            $rows = $this->mysql->fetchAll('SHOW FULL COLUMNS FROM `probe_' . $table . '_test`');
            $schema[$table] = $this->normalizeRows($rows);
        }
        return $schema;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows($rows): array
    {
        Assert::isArray($rows);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->stringKeyedArray($row);
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function stringKeyedArray($value): array
    {
        Assert::isArray($value);
        $result = [];
        foreach ($value as $key => $item) {
            Assert::string($key);
            $result[$key] = $item;
        }
        return $result;
    }

    private function assertRejectedSql(string $sql): void
    {
        try {
            $this->mysql->execute($sql);
            $this->fail('Expected a database constraint violation.');
        } catch (\PDOException $error) {
            $this->assertSame('23000', (string) $error->getCode());
        }
    }
}
