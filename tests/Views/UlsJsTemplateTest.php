<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Views;

use Latte\Engine;
use PHPUnit\Framework\TestCase;
use Seablast\I18n\I18nConstant;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

final class UlsJsTemplateTest extends TestCase
{
    public function testLanguageListIsSafelySerializedInJavaScriptContext(): void
    {
        $languages = ['en', '</script><script>alert("xss")</script>'];
        $configuration = (new SeablastConfiguration())
            ->setString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL, 'https://example.test')
            ->setInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION, 1)
            ->setArrayString(I18nConstant::LANGUAGE_LIST, $languages);
        $configuration->flag->activate(I18nConstant::FLAG_SHOW_LANGUAGE_SELECTOR);

        $latte = new Engine();
        $output = $latte->renderToString(
            dirname(__DIR__, 2) . '/views/uls.js.latte',
            ['configuration' => $configuration]
        );

        $matches = [];
        if (
            preg_match(
                '/window\.sbI18nLanguageList = (?<json>\[[^\r\n]*\]);<\/script>/',
                $output,
                $matches
            ) !== 1
        ) {
            self::fail('The rendered language-list assignment was not found.');
        }

        $languageListJson = $matches['json'];
        self::assertSame($languages, json_decode($languageListJson, true));
        self::assertStringContainsString('<\/script><script>alert(\"xss\")<\/script>', $languageListJson);
        self::assertStringNotContainsString('</script><script>', $languageListJson);
    }
}
