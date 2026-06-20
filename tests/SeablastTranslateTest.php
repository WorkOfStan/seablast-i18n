<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\I18n\I18nConstant;
use Seablast\I18n\SeablastTranslate;
use Seablast\Seablast\SeablastConfiguration;

final class SeablastTranslateTest extends TestCase
{
    public function testTranslateRejectsUnsupportedExplicitLanguage(): void
    {
        $translator = new SeablastTranslate(
            (new SeablastConfiguration())->setArrayString(I18nConstant::LANGUAGE_LIST, ['en', 'cs'])
        );

        $this->expectException(\Exception::class);
        $translator->translate('Back', 'de');
    }
}
