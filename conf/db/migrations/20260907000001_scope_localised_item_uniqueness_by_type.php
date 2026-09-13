<?php

declare(strict_types=1);

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class ScopeLocalisedItemUniquenessByType extends AbstractMigration
{
    private const INDEX_NAME = 'idx_localised_item_language_type_unique';

    public function up(): void
    {
        $this->table('localised_items')
            ->removeIndex(['item_id', 'language'])
            ->addIndex(['item_id', 'language', 'item_type_id'], ['unique' => true, 'name' => self::INDEX_NAME])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('localised_items');
        if (!$table->hasIndexByName(self::INDEX_NAME)) {
            return;
        }
        // New data may reuse an ID and language across types; never remove its protection first.
        if (
            $this->fetchRow(
                'SELECT 1 FROM ' . $this->quotedTableName()
                . ' GROUP BY item_id, language HAVING COUNT(*) > 1 LIMIT 1'
            ) !== false
        ) {
            throw new IrreversibleMigrationException(
                'Cannot restore unique (item_id, language): duplicates exist across item types. '
                . 'Resolve these conflicts manually before retrying rollback.'
            );
        }
        $table->removeIndexByName(self::INDEX_NAME)
            ->addIndex(['item_id', 'language'], ['unique' => true])
            ->update();
    }

    private function quotedTableName(): string
    {
        $options = $this->getAdapterOptions();
        $prefix = $options['table_prefix'] ?? '';
        $suffix = $options['table_suffix'] ?? '';
        if (!is_string($prefix) || !is_string($suffix)) {
            throw new \RuntimeException('Table prefix and suffix must be strings.');
        }
        return '`' . str_replace('`', '``', $prefix . 'localised_items' . $suffix) . '`';
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
