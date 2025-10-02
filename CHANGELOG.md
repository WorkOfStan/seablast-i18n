# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### `Added` for new features

### `Changed` for changes in existing functionality

### `Deprecated` for soon-to-be removed features

### `Removed` for now removed features

### `Fixed` for any bugfixes

### `Security` in case of vulnerabilities

## [0.1.1] - 2025-10-04

- feat: SeablastTranslate::translate() becomes multi-lingual

### Added

- feat: SeablastTranslate::translate($original, OPTIONAL $language) in order to be able to translate for multiple user, e.g. in case of emailing

### Changed

- chore: bumped the versions of GitHub Actions and blast.sh

## [0.1] - 2025-08-03

feat: library to handle language switching and localisation of selected strings

### Added

- `/api/language` accesses `\Seablast\I18n\Models\ApiLanguageModel`
- ULS langugage selector
- localised items migration
- package limited to the tested PHP versions, i.e. "php": ">=7.2 <8.5"
- API `'/api/language'` using `'model' => '\Seablast\I18n\Models\ApiLanguageModel'` returns the selected language or it receives language to be set in the cookie 'sbLanguage'.

[Unreleased]: https://github.com/WorkOfStan/seablast-i18n/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/WorkOfStan/seablast-i18n/compare/v0.1...v0.1.1
[0.1]: https://github.com/WorkOfStan/seablast-i18n/releases/tag/v0.1
