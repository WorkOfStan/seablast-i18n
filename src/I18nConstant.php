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
     * @var string string selected language `SB:LANGUAGE` reserved in Seablast\Seablast
     */
    public const LANGUAGE = 'SB:LANGUAGE';
    /**
     * @var string string[] supported languages
     */
    public const LANGUAGE_LIST = 'I18nSB:LANGUAGE_LIST';
}
