<?php

declare(strict_types=1);

namespace Seablast\I18n\Models;

use Seablast\I18n\I18nConstant;
use Seablast\Seablast\Apis\GenericRestApiJsonModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

/**
 * API returns the selected language or accepts a language to store in the cookie 'sbLanguage'.
 */
class ApiLanguageModel extends GenericRestApiJsonModel
{
    use \Nette\SmartObject;

    private const COOKIE_LANGUAGE = 'sbLanguage';

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        // Read JSON from standard input if not pre-prepared
        $jsonInput = $this->configuration->exists(SeablastConstant::JSON_INPUT) //
            ? $this->configuration->getString(SeablastConstant::JSON_INPUT) : file_get_contents('php://input');

        // Invoke JSON check only if present, otherwise $this->getLanguageValue() will be triggered in knowledge()
        if (!is_string($jsonInput) || $jsonInput === '') {
            return;
        }

        json_decode($jsonInput);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // TODO: Make this more straightforward. This can happen when language detection runs during email login.
            Debugger::barDump('$jsonInput does not contain a valid JSON. No JSON checks invoked.');
            return;
        }

        if (!array_key_exists('REQUEST_METHOD', $superglobals->server)) {
            // otherwise login by email fails
            $superglobals->server['REQUEST_METHOD'] = 'POST';
        }
        parent::__construct($this->configuration, $superglobals);
    }

    /**
     * Return the knowledge calculated in this model.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        $result = parent::knowledge();
        if ($result->httpCode >= 400) {
            // Error state means that further processing is not desired
            return $result;
        }
        if (!isset($this->data->language)) {
            // return the set or the default language
            return self::response(200, $this->getLanguageValue());
        }

        $language = $this->data->language;
        if (
            !is_string($language)
            || !in_array($language, $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST), true)
        ) {
            return self::response(400, 'Language not supported.');
        }

        return $this->setLanguageCookie($language) //
            ? self::response(200, 'Language set.') : self::response(500, 'Language failed');
    }

    /**
     * Checks existence of a cookie `sbLanguage` and validates it; or returns the default language.
     *
     * @return string
     * @throws \Exception
     */
    private function getLanguageValue(): string
    {
        $languages = $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST);

        // Check if the cookie exists and is not empty
        if (
            !empty($_COOKIE[self::COOKIE_LANGUAGE]) && is_string($_COOKIE[self::COOKIE_LANGUAGE]) //
            && in_array($_COOKIE[self::COOKIE_LANGUAGE], $languages, true)
        ) {
            return $this->useLanguage((string) $_COOKIE[self::COOKIE_LANGUAGE]);
        }

        // If cookie is not set or empty or unsupported, return the first value of the language list
        $result = reset($languages);
        if ($result === false) {
            throw new \Exception('LANGUAGE_LIST is empty');
        }
        return $this->useLanguage($result);
    }

    private function useLanguage(string $language): string
    {
        $this->configuration->setString(I18nConstant::LANGUAGE, $language);
        return $language;
    }

    /**
     * Sets the sbLanguage long-term cookie.
     *
     * @return bool
     */
    private function setLanguageCookie(string $language): bool
    {
        return setcookie(
            self::COOKIE_LANGUAGE,
            $language,
            time() + 30 * 24 * 60 * 60, // expire time: days * hours * minutes * seconds
            $this->configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH),
            '', // the default cookie host
            true,
            true
        );
    }
}
