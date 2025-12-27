<?php

declare(strict_types=1);

namespace Seablast\I18n\Models;

use Seablast\I18n\SeablastTranslate;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

/**
 * Retrieve items from database
 */
class FetchLocalisedItemsModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;
    /** @var int|null itemId or null */
    private $itemId;
    /** @var int itemTypeId set in the child class */
    protected $itemTypeId;
    /** @var Superglobals */
    private $superglobals;
    /** @var string page title beginning set in the child class */
    protected $titlePrefix = "";
    /** @var string page title ending set in the child class*/
    protected $titleSuffix = "";

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throw \Exception if unimplemented HTTP method call
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->superglobals = $superglobals;
        if ($this->superglobals->server['REQUEST_METHOD'] === 'GET') {
            $this->itemId = (isset($this->superglobals->get['id']) && is_numeric($this->superglobals->get['id'])) ?
                (int) $this->superglobals->get['id'] : null;
        } else {
            throw new \Exception(
                'Wrong HTTP method request: ' . (string) print_r($this->superglobals->server['REQUEST_METHOD'], true)
            );
        }
    }

    /**
     * Collection of items to be displayed.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        $translate = new SeablastTranslate($this->configuration);
        $language = $translate->getLanguage(); // ISO 639-1 = 2-letter language code
        // get Generator
        $itemsGen = $this->fetchItems($language, $this->itemTypeId, $this->itemId);
        // move to the first yield - which run the code till the first `yield`
        $itemsGen->rewind();
        // if it's not valid, no row is produced
        if (!$itemsGen->valid()) {
            // No item available. Either no item at all, or the particular itemId.
            return (object) [
                    'httpCode' => 404,
                    'message' => 'Žádné příspěvky.',
                    'title' => "{$this->titlePrefix}Chyba"
            ];
        }
        $item = $itemsGen->current();
        if (isset($item['title']) && is_string($item['title']) && !empty($item['title'])) {
            $this->titleSuffix = $item['title'];
        }
        return (object) [
            'title' => "{$this->titlePrefix}{$this->titleSuffix}",
            'itemId' => $this->itemId,
            'items' => $itemsGen,
        ];
    }

    /**
     * Yield items one by one from the database.
     *
     * @param  string        $language   ISO 639-1 language code
     * @param  int           $itemTypeId Item type identifier
     * @param  int|null      $itemId     Specific blog item_id, or null for all
     * @return \Generator<int,array<string,mixed>>  Generator yielding each row as an associative array
     * @throws DbmsException
     */
    private function fetchItems(string $language, int $itemTypeId, ?int $itemId = null): \Generator
    {
        Debugger::barDump(
            ['language' => $language, 'itemTypeId' => $itemTypeId, 'itemId' => $itemId],
            'fetchItems arguments'
        );

        if (is_null($itemId)) {
            // Fetch all active items of this type and language, newest first
            $sql = "
                SELECT 
                    item_id, language, parent_id, title, 
                    LEFT(content, 100) AS content, friendly_url, 
                    item_type_id, active, created_at, updated_at
                FROM `{$this->configuration->dbmsTablePrefix()}localised_items`
                WHERE language = ? AND item_type_id = ? AND active = 1
                ORDER BY created_at DESC;
            ";
            $stmt = $this->configuration->mysqli()->prepareStrict($sql);
            $stmt->bind_param('si', $language, $itemTypeId);
        } else {
            // Fetch single active item by its ID and language
            $sql = "
                SELECT 
                    id, item_id, language, parent_id, title, content,
                    friendly_url, item_type_id, active, created_at, updated_at
                FROM `{$this->configuration->dbmsTablePrefix()}localised_items`
                WHERE item_id = ? AND language = ? AND item_type_id = ? AND active = 1;
            ";
            $stmt = $this->configuration->mysqli()->prepareStrict($sql);
            $stmt->bind_param('isi', $itemId, $language, $itemTypeId);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new DbmsException('Failed to get result set');
        }

        // Yield each row; if there are none, this generator simply ends without yielding.
        while ($row = $result->fetch_assoc()) {
            //Debugger::barDump($row, 'yielded row');
            yield $row;
        }

        $stmt->close();
    }
}
