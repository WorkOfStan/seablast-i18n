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
//        ->activate(AuthConstant::FLAG_USE_SOCIAL_LOGIN) // actual social login requires AuthApp:..social.._ID
//        // - AuthApp:GOOGLE_CLIENT_ID
//        // - AuthApp:FACEBOOK_APP_ID
//        ->activate(AuthConstant::FLAG_REMEMBER_ME_COOKIE) // TODO check which default makes more sense
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
    ;
};
