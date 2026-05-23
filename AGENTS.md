# Agent Notes

## Project Purpose

`seablast/i18n` is a Composer library for Seablast for PHP applications. It provides:

- `Seablast\I18n\SeablastTranslate`, used by Seablast as the Latte `translate` filter.
- `Seablast\I18n\Models\ApiLanguageModel`, the `/api/language` JSON endpoint for reading or setting the selected language.
- Optional Latte templates in `views/uls.*.latte` for a Universal Language Selector UI.
- Phinx migrations for dictionary translations and localised content items.

## Integration Notes

- Load `conf/app.conf.php` from the consuming Seablast app so the language endpoint, translator class, default language list, and `I18n:SHOW_LANGUAGE_SELECTOR` flag are registered.
- Configure supported languages with `I18nConstant::LANGUAGE_LIST`; the first configured language is the default.
- The current migration stores language codes in `string(5)` columns. Add a follow-up migration before using longer BCP 47 tags such as `ku-latn`.
- Add `vendor/seablast/i18n/conf/db/migrations` to the app's Phinx migration paths when the dictionary or localised item tables are needed.
- Include `views/uls.css.latte`, `views/uls.menu.latte`, and `views/uls.js.latte` from the app layout when the bundled selector should be available.
- Host applications own web-server hardening such as directory-listing protection and vendor access rules.

## Development Notes

- Keep changes focused and update `CHANGELOG.md` in English for user-visible changes.
- Do not remove comments unless the comment is a TODO that the change actually resolves; improve unclear comments in English.
- Do not run `.sh` helper scripts directly from PowerShell on Windows. Use Git Bash explicitly, for example `& "C:\Program Files\Git\bin\bash.exe" -lc "./blast.sh phpstan"`.
- When running Composer on Windows, use `$env:COMPOSER_CACHE_DIR = "$PWD\.composer-cache"` and `php "C:\ProgramData\ComposerSetup\bin\composer.phar" install`.
- Do not inspect or recurse into generated/cache directories such as `vendor`, `.tmp`, or build artifacts unless the task explicitly requires it.

## Useful Files

- `README.md`: consumer-facing setup and usage.
- `composer.json`: package metadata, PHP version range, and PSR-4 autoloading.
- `src/SeablastTranslate.php`: dictionary lookup and lazy language initialization.
- `src/Models/ApiLanguageModel.php`: language read/set endpoint and `sbLanguage` cookie handling.
- `src/Models/FetchLocalisedItemsModel.php`: GET-only model for active localised items by type.
- `views/uls.*.latte`: optional language selector assets and markup.
- `conf/db/migrations/20250116140457_localised_items.php`: database schema for translations and localised items.
