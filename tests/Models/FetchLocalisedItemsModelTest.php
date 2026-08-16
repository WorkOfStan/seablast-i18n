<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Models;

use PHPUnit\Framework\TestCase;
use Seablast\I18n\Models\FetchLocalisedItemsModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;

final class FetchLocalisedItemsModelTest extends TestCase
{
    public function testKnowledgeRejectsNonDecimalItemIdBeforeDatabaseAccess(): void
    {
        $knowledge = $this->modelWithId('1e3')->knowledge();

        $this->assertInvalidItemIdResponse($knowledge);
    }

    public function testKnowledgeRejectsNegativeItemIdBeforeDatabaseAccess(): void
    {
        $knowledge = $this->modelWithId('-1')->knowledge();

        $this->assertInvalidItemIdResponse($knowledge);
    }

    public function testKnowledgeRejectsOverflowItemIdBeforeDatabaseAccess(): void
    {
        $knowledge = $this->modelWithId('2147483648')->knowledge();

        $this->assertInvalidItemIdResponse($knowledge);
    }

    /**
     * @param mixed $id
     */
    private function modelWithId($id): FetchLocalisedItemsModel
    {
        return new FetchLocalisedItemsModelForTest(
            new SeablastConfiguration(),
            new Superglobals(['id' => $id], [], ['REQUEST_METHOD' => 'GET'])
        );
    }

    private function assertInvalidItemIdResponse(stdClass $knowledge): void
    {
        self::assertSame(400, $knowledge->httpCode);
        self::assertSame('Invalid item id.', $knowledge->message);
    }
}

final class FetchLocalisedItemsModelForTest extends FetchLocalisedItemsModel
{
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->itemTypeId = 1;

        parent::__construct($configuration, $superglobals);
    }
}
