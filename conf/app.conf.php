<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 */

use Seablast\I18n\I18nConstant;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
//    $SBConfig->flag
//        //->activate(SeablastConstant::FLAG_WEB_RUNNING)
//    ;
    $SBConfig
        ->setArrayString(I18nConstant::LANGUAGE_LIST, ['en', 'cs']) // default list of supported languages
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/api/language',
            [
                'model' => '\Seablast\I18n\Models\ApiLanguageModel',
            ]
        )
        // Seablast::SeablastView uses this class for Latte filter translate
        ->setString(SeablastConstant::TRANSLATE_CLASS, '\Seablast\I18n\SeablastTranslate') // since Seablast v0.2.7
    ;
};
