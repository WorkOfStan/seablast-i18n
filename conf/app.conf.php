<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 */

use Seablast\I18n\I18nConstant;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        ->activate(I18nConstant::FLAG_SHOW_LANGUAGE_SELECTOR) // show language switcher if in latte
    ;
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
        // Administration of Seablast I18n tables
        ->setArrayString(
            // ON DELETE NO_ACTION prevents risky deletion of children
            SeablastConstant::ADMIN_TABLE_DELETE_ROW . SeablastConstant::USER_ROLE_EDITOR,
            [
                'localised_items', // note: localised_item_types has ON DELETE CASCADE
            ]
        )
        ->setArrayString(
            SeablastConstant::ADMIN_TABLE_INSERT_ROW . SeablastConstant::USER_ROLE_EDITOR,
            [
                'localised_items',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
            'translations',
            ['id']
        )
        ->setArrayArrayString(
            SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_EDITOR,
            'translations',
            ['language','translation_key','translation_value']
        )
        ->setArrayArrayString(
            SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
            'localised_items',
            [
                'id',
                //'parent_id',
                'friendly_url',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::ADMIN_TABLE_EDIT . SeablastConstant::USER_ROLE_EDITOR,
            'localised_items',
            [
                'item_id','language',
                'item_type_id',
                'active','title','content',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR,
            'localised_item_types',
            ['id', 'name']
        )
    ;
};
