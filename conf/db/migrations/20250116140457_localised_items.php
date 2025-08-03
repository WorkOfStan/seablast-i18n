<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LocalisedItems extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // There are two localised mechanisms.
        // a) key texts with localisation option (dictionary)
        // b) content tree with language localisation incl. friendly url
        // this is a)
        // Create the translations table
        $table = $this->table('translations', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'integer', ['identity' => true])
            // Language code (e.g., "en", "en_GB") NULL for default language
            ->addColumn('language', 'string', ['limit' => 5])
            ->addColumn('translation_key', 'string', ['limit' => 255])
            ->addColumn('translation_value', 'text')
            ->addIndex(['language', 'translation_key'], ['unique' => true])
            ->create();

        // this is b)
        // Create the item_types table
        $itemTypesTable = $this->table('localised_item_types');
        $itemTypesTable
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false]) // Type name, e.g., "page", "text"
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();

        // Create the localised_items table
        $localisedItemsTable = $this->table('localised_items');
        $localisedItemsTable
            ->addColumn('item_id', 'integer') // Item identifier
            // Language code (e.g., "en", "en_GB") NULL for default language
            ->addColumn('language', 'string', ['limit' => 5, 'null' => true])
            ->addColumn('parent_id', 'integer', ['null' => true]) // Parent item ID
            ->addColumn('title', 'string', ['limit' => 255]) // Title of the item
            ->addColumn('content', 'text', ['null' => true]) // Content of the item
            ->addColumn('friendly_url', 'string', ['limit' => 255, 'null' => true]) // Friendly URL for SEO
            ->addColumn('item_type_id', 'integer', ['signed' => false]) // Foreign key to localised_item_types table
            ->addColumn('active', 'boolean', ['default' => 1, 'comment' => '0=inactive, 1=active']) // Active flag
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP']) // Timestamps
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['item_id', 'language'], ['unique' => true]) // Unique index for item_id + language
            ->addIndex(['parent_id']) // Index for parent_id
            ->addIndex(['active']) // Index for active flag
            // FK to item_types
            ->addForeignKey(
                'item_type_id',
                'localised_item_types',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE']
            )
            ->create();
    }
}
