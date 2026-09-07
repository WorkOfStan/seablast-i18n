<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Models;

use Seablast\I18n\Models\FetchLocalisedItemsModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;

final class FetchLocalisedItemsModelForTest extends FetchLocalisedItemsModel
{
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->itemTypeId = 1;

        parent::__construct($configuration, $superglobals);
    }
}
