<?php

declare(strict_types=1);

namespace Seablast\I18n;

/**
 * @api
 * Project-specific i18n configuration keys should not start with SB, to avoid unintended collisions.
 * LANGUAGE intentionally aliases Seablast's reserved `SB:LANGUAGE` key because Latte reads it directly.
 */
class I18nConstant
{
    /**
     * @var string flag: Feature flag controlling whether bundled language selector templates render.
     */
    public const FLAG_SHOW_LANGUAGE_SELECTOR = 'I18n:SHOW_LANGUAGE_SELECTOR';
    /**
     * @var string string: Current language key, reserved in Seablast\Seablast.
     */
    public const LANGUAGE = 'SB:LANGUAGE';
    /**
     * @var string string[]: Configuration key for supported language codes (`string[]`).
     */
    public const LANGUAGE_LIST = 'I18nSB:LANGUAGE_LIST';
}
