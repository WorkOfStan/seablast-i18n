<?php

declare(strict_types=1);

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

/**
 * @phpstan-type ColumnOptions array{
 *     null: bool, limit?: int, signed?: bool, default?: int|string, update?: string, comment?: string
 * }
 * @phpstan-type ColumnDefinition array{type: string, options: ColumnOptions}
 */
final class EnforceI18nRequiredColumnsNotNull extends AbstractMigration
{
    public function up(): void
    {
        $definitions = $this->columnDefinitions();
        $invalid = [];
        // Check every table before any DDL: MySQL schema changes cannot be rolled back reliably.
        foreach ($definitions as $tableName => $columns) {
            foreach (array_keys($columns) as $columnName) {
                if (
                    $this->fetchRow(
                        'SELECT 1 FROM ' . $this->quotedTableName($tableName)
                        . ' WHERE `' . $columnName . '` IS NULL LIMIT 1'
                    ) !== false
                ) {
                    $invalid[] = $tableName . '.' . $columnName;
                }
            }
        }
        if ($invalid !== []) {
            throw new \RuntimeException(
                'Cannot enforce NOT NULL; repair existing NULL values in: ' . implode(', ', $invalid) . '.'
            );
        }

        foreach ($definitions as $tableName => $columns) {
            $table = $this->table($tableName);
            $restoreForeignKey = false;
            try {
                // MySQL requires removing the foreign key before changing its column.
                if ($tableName === 'localised_items' && $table->hasForeignKey('item_type_id')) {
                    $table->dropForeignKey('item_type_id')->update();
                    $restoreForeignKey = true;
                }
                foreach ($columns as $columnName => $definition) {
                    $table->changeColumn($columnName, $definition['type'], $definition['options']);
                }
                $table->update();
            } finally {
                if ($restoreForeignKey) {
                    $this->table($tableName)->addForeignKey(
                        'item_type_id',
                        'localised_item_types',
                        'id',
                        ['delete' => 'CASCADE', 'update' => 'CASCADE']
                    )->update();
                }
            }
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Prior column nullability depends on the Phinx version and cannot be safely restored.'
        );
    }

    /** @return array<string, array<string, ColumnDefinition>> */
    private function columnDefinitions(): array
    {
        $created = ['type' => 'timestamp', 'options' => ['null' => false, 'default' => 'CURRENT_TIMESTAMP']];
        $updated = [
            'type' => 'timestamp',
            'options' => ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'],
        ];
        return [
            'translations' => [
                'language' => ['type' => 'string', 'options' => ['limit' => 5, 'null' => false]],
                'translation_key' => ['type' => 'string', 'options' => ['limit' => 255, 'null' => false]],
                'translation_value' => ['type' => 'text', 'options' => ['null' => false]],
            ],
            'localised_item_types' => ['created_at' => $created, 'updated_at' => $updated],
            'localised_items' => [
                'item_id' => ['type' => 'integer', 'options' => ['null' => false]],
                'language' => ['type' => 'string', 'options' => ['limit' => 5, 'null' => false]],
                'title' => ['type' => 'string', 'options' => ['limit' => 255, 'null' => false]],
                'item_type_id' => ['type' => 'integer', 'options' => ['signed' => false, 'null' => false]],
                'active' => [
                    'type' => 'boolean',
                    'options' => ['default' => 1, 'comment' => '0=inactive, 1=active', 'null' => false],
                ],
                'created_at' => $created,
                'updated_at' => $updated,
            ],
        ];
    }

    private function quotedTableName(string $name): string
    {
        $options = $this->getAdapterOptions();
        $prefix = $options['table_prefix'] ?? '';
        $suffix = $options['table_suffix'] ?? '';
        if (!is_string($prefix) || !is_string($suffix)) {
            throw new \RuntimeException('Table prefix and suffix must be strings.');
        }
        return '`' . str_replace('`', '``', $prefix . $name . $suffix) . '`';
    }

    /**
     * @return array<string, mixed>
     */
    private function getAdapterOptions(): array
    {
        return $this->readAdapterOptions($this->getAdapter());
    }

    /**
     * Keep getAdapterOptions calling readAdapterOptions to maintain compatibility PHP >= 8.1
     * and below where $this->getAdapter() returns Phinx\Db\Adapter\AdapterInterface|null
     *
     * @param mixed $adapter
     * @return array<string, mixed>
     */
    private function readAdapterOptions($adapter): array
    {
        if (!$adapter instanceof AdapterInterface) {
            throw new \RuntimeException('Migration adapter is not initialized.');
        }
        return $adapter->getOptions();
    }
}
