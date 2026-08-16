<?php

declare(strict_types=1);

namespace Seablast\I18n\Models;

/**
 * @param int|array{
 *     expires?: int,
 *     path?: string,
 *     domain?: string,
 *     secure?: bool,
 *     httponly?: bool,
 *     samesite?: string
 * } $expires
 */
function setcookie(
    string $name,
    string $value = '',
    $expires = 0,
    string $path = '',
    string $domain = '',
    bool $secure = false,
    bool $httponly = false
): bool {
    $call = [
        'name' => $name,
        'value' => $value,
        'expires' => $expires,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
    ];

    if (is_array($expires)) {
        $call['expires'] = $expires['expires'] ?? 0;
        $call['path'] = $expires['path'] ?? '';
        $call['domain'] = $expires['domain'] ?? '';
        $call['secure'] = $expires['secure'] ?? false;
        $call['httponly'] = $expires['httponly'] ?? false;
        if (array_key_exists('samesite', $expires)) {
            $call['samesite'] = $expires['samesite'];
        }
    }

    \Seablast\I18n\Tests\Models\ApiLanguageModelSetCookieSpy::$calls[] = $call;

    return \Seablast\I18n\Tests\Models\ApiLanguageModelSetCookieSpy::$returnValue;
}

namespace Seablast\I18n\Tests\Models;

use PHPUnit\Framework\TestCase;
use Seablast\I18n\I18nConstant;
use Seablast\I18n\Models\ApiLanguageModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

final class ApiLanguageModelTest extends TestCase
{
    /** @var array<mixed> */
    private $cookies = [];
    /** @var mixed */
    private $productionMode;

    protected function setUp(): void
    {
        $this->cookies = $_COOKIE;
        $_COOKIE = [];
        $this->productionMode = self::getTracyProductionMode();
        self::setTracyProductionMode(Debugger::DETECT);
        ApiLanguageModelSetCookieSpy::reset();
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;
        self::setTracyProductionMode($this->productionMode);
        ApiLanguageModelSetCookieSpy::reset();
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

    public function testLanguageCookieIsNotSecureInTracyDevelopmentMode(): void
    {
        Debugger::$productionMode = Debugger::DEVELOPMENT;
        $model = new ApiLanguageModel(
            $this->configuration(),
            new Superglobals([], [], ['REMOTE_ADDR' => '203.0.113.10'])
        );

        $this->setLanguageCookie($model, 'cs');

        self::assertFalse(ApiLanguageModelSetCookieSpy::$calls[0]['secure']);
    }

    public function testLanguageCookieIsSecureInTracyProductionMode(): void
    {
        Debugger::$productionMode = Debugger::PRODUCTION;
        $model = new ApiLanguageModel($this->configuration(), new Superglobals([], [], ['REMOTE_ADDR' => '127.0.0.1']));

        $this->setLanguageCookie($model, 'cs');

        self::assertTrue(ApiLanguageModelSetCookieSpy::$calls[0]['secure']);
    }

    public function testLanguageCookieFallbackAllowsInsecureDevelopmentIp(): void
    {
        $model = new ApiLanguageModel($this->configuration(), new Superglobals([], [], ['REMOTE_ADDR' => '127.0.0.1']));

        $this->setLanguageCookie($model, 'cs');

        self::assertFalse(ApiLanguageModelSetCookieSpy::$calls[0]['secure']);
    }

    public function testLanguageCookieFallbackKeepsSecureForNonDevelopmentIp(): void
    {
        $model = new ApiLanguageModel(
            $this->configuration(),
            new Superglobals([], [], ['REMOTE_ADDR' => '203.0.113.10'])
        );

        $this->setLanguageCookie($model, 'cs');

        self::assertTrue(ApiLanguageModelSetCookieSpy::$calls[0]['secure']);
    }

    public function testLanguageCookieUsesHttpOnlyAndSameSite(): void
    {
        Debugger::$productionMode = Debugger::PRODUCTION;
        $model = new ApiLanguageModel($this->configuration(), new Superglobals([], [], ['REMOTE_ADDR' => '127.0.0.1']));

        $this->setLanguageCookie($model, 'cs');

        $cookie = ApiLanguageModelSetCookieSpy::$calls[0];
        self::assertTrue($cookie['httponly']);
        if (PHP_VERSION_ID >= 70300) {
            self::assertSame('/', $cookie['path']);
            self::assertSame('Lax', $cookie['samesite']);
        } else {
            self::assertSame('/; SameSite=Lax', $cookie['path']);
        }
    }

    public function testLanguageCookieRejectsUnsafePath(): void
    {
        Debugger::$productionMode = Debugger::PRODUCTION;
        $configuration = $this->configuration()
            ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, '/; Secure');
        $model = new ApiLanguageModel($configuration, new Superglobals([], [], ['REMOTE_ADDR' => '127.0.0.1']));
        $method = new \ReflectionMethod(ApiLanguageModel::class, 'setLanguageCookie');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($model, 'cs');
    }

    private function assertLanguageResponse(stdClass $knowledge, string $language): void
    {
        $rest = $knowledge->rest;

        self::assertSame(200, $knowledge->httpCode);
        self::assertInstanceOf(stdClass::class, $rest);
        self::assertSame($language, $rest->message);
    }

    private function setLanguageCookie(ApiLanguageModel $model, string $language): void
    {
        $method = new \ReflectionMethod(ApiLanguageModel::class, 'setLanguageCookie');
        $method->setAccessible(true);

        self::assertTrue($method->invoke($model, $language));
    }

    /**
     * @return mixed
     */
    private static function getTracyProductionMode()
    {
        return (new \ReflectionProperty(Debugger::class, 'productionMode'))->getValue();
    }

    /**
     * @param mixed $productionMode
     */
    private static function setTracyProductionMode($productionMode): void
    {
        (new \ReflectionProperty(Debugger::class, 'productionMode'))->setValue(null, $productionMode);
    }

    private function configuration(): SeablastConfiguration
    {
        return (new SeablastConfiguration())
            ->setArrayString(I18nConstant::LANGUAGE_LIST, ['en', 'cs'])
            ->setArrayString(SeablastConstant::DEBUG_IP_LIST, [])
            ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, '/');
    }
}
