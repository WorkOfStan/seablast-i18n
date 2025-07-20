<?php

declare(strict_types=1);

namespace Seablast\I18n;

/**
 * @api
 * Strings MUST NOT start with SB to avoid unintended value collision
 */
class I18nConstant
{
    /**
     * @var string string[] supported languages
     */
    public const LANGUAGE_LIST = 'I18nApp:LANGUAGE_LIST';
}
