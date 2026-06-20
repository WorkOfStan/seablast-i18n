<?php

declare(strict_types=1);

namespace Seablast\I18n\Tests\Models;

final class ApiLanguageModelSetCookieSpy
{
    /** @var array<int, array<string, mixed>> */
    public static $calls = [];

    /** @var bool */
    public static $returnValue = true;

    public static function reset(): void
    {
        self::$calls = [];
        self::$returnValue = true;
    }
}
