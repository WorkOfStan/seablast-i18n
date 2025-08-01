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
     * latte uses 'SB:LANGUAGE' directly, so it's ok to use it directly also in the PHP code
     */
    public const LANGUAGE = 'SB:LANGUAGE';
    /**
     * @var string string[] supported languages
     */
    public const LANGUAGE_LIST = 'I18nSB:LANGUAGE_LIST';
}
