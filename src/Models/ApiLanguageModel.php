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
    // expire time: days * hours * minutes * seconds
    private const COOKIE_LIFETIME_SECONDS = 30 * 24 * 60 * 60;
    private const COOKIE_SAME_SITE = 'Lax';

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->superglobals = $superglobals;
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
        // Valid JSON input must go through the parent constructor to populate $this->data and run CSRF validation.
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
        $expires = time() + self::COOKIE_LIFETIME_SECONDS;
        $path = $this->languageCookiePath();
        $secure = $this->isLanguageCookieSecure();

        if (PHP_VERSION_ID >= 70300) {
            return setcookie(
                self::COOKIE_LANGUAGE,
                $language,
                [
                    'expires' => $expires,
                    'path' => $path,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => self::COOKIE_SAME_SITE,
                ]
            );
        }

        return setcookie(
            self::COOKIE_LANGUAGE,
            $language,
            $expires,
            $path . '; SameSite=' . self::COOKIE_SAME_SITE,
            '', // the default cookie host
            $secure,
            true
        );
    }

    private function languageCookiePath(): string
    {
        $path = $this->configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH);
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/' || preg_match('/[[:cntrl:]\s;,]/', $path) !== 0) {
            throw new \InvalidArgumentException('Unsafe language cookie path.');
        }

        return $path;
    }

    private function isLanguageCookieSecure(): bool
    {
        // Tracy 2.x documents the property as bool, but uses null before Debugger::enable() resolves auto-detect mode.
        $productionMode = (new \ReflectionProperty(Debugger::class, 'productionMode'))->getValue();
        if ($productionMode === true) {
            return true;
        }
        if ($productionMode === false) {
            return false;
        }

        $remoteAddress = $this->superglobals->server['REMOTE_ADDR'] ?? '';
        if (!is_string($remoteAddress)) {
            return true;
        }

        $developmentIpList = ['::1', '127.0.0.1'];
        if ($this->configuration->exists(SeablastConstant::DEBUG_IP_LIST)) {
            $developmentIpList = array_merge(
                $developmentIpList,
                $this->configuration->getArrayString(SeablastConstant::DEBUG_IP_LIST)
            );
        }

        return !in_array($remoteAddress, $developmentIpList, true);
    }
}
