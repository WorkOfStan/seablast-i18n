<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Models;

use PHPUnit\Framework\TestCase;
use Seablast\I18n\I18nConstant;
use Seablast\I18n\Models\ApiLanguageModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;

final class ApiLanguageModelTest extends TestCase
{
    /** @var array<mixed> */
    private $cookies = [];

    protected function setUp(): void
    {
        $this->cookies = $_COOKIE;
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;
    }

    public function testKnowledgeReturnsDefaultLanguageWithoutJsonInput(): void
    {
        $configuration = $this->configuration();
        $model = new ApiLanguageModel($configuration, new Superglobals([], [], ['REQUEST_METHOD' => 'GET']));

        $knowledge = $model->knowledge();

        $this->assertLanguageResponse($knowledge, 'en');
        self::assertSame('en', $configuration->getString(I18nConstant::LANGUAGE));
    }

    public function testKnowledgeUsesSupportedCookieLanguage(): void
    {
        $_COOKIE['sbLanguage'] = 'cs';
        $configuration = $this->configuration();
        $model = new ApiLanguageModel($configuration, new Superglobals([], [], ['REQUEST_METHOD' => 'GET']));

        $knowledge = $model->knowledge();

        $this->assertLanguageResponse($knowledge, 'cs');
        self::assertSame('cs', $configuration->getString(I18nConstant::LANGUAGE));
    }

    public function testKnowledgeIgnoresUnsupportedCookieLanguage(): void
    {
        $_COOKIE['sbLanguage'] = 'de';
        $configuration = $this->configuration();
        $model = new ApiLanguageModel($configuration, new Superglobals([], [], ['REQUEST_METHOD' => 'GET']));

        $knowledge = $model->knowledge();

        $this->assertLanguageResponse($knowledge, 'en');
        self::assertSame('en', $configuration->getString(I18nConstant::LANGUAGE));
    }

    private function assertLanguageResponse(stdClass $knowledge, string $language): void
    {
        $rest = $knowledge->rest;

        self::assertSame(200, $knowledge->httpCode);
        self::assertInstanceOf(stdClass::class, $rest);
        self::assertSame($language, $rest->message);
    }

    private function configuration(): SeablastConfiguration
    {
        return (new SeablastConfiguration())
            ->setArrayString(I18nConstant::LANGUAGE_LIST, ['en', 'cs']);
    }
}
