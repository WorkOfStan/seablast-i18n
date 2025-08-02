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
 * API receives language to be set in a cookie
 */
class ApiLanguageModel extends GenericRestApiJsonModel
{
    use \Nette\SmartObject;

    /** @var string Cookie domain. */
    private $cookieDomain;
    /** @var string Path for cookies. */
    private $cookiePath;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        // todo tato metoda opakuje parent::construct pro případ GET? refactor somehow?
        $this->configuration = $configuration;
        // Read JSON from standard input if not pre-prepared
        $jsonInput = $this->configuration->exists(SeablastConstant::JSON_INPUT) //
            ? $this->configuration->getString(SeablastConstant::JSON_INPUT) : file_get_contents('php://input');
        Debugger::barDump($jsonInput, 'JSON input from php://input or SeablastConstant::JSON_INPUT');
        // Invoke JSON check only if present, otherwise $this->getLanguageValue() will be triggered in knowledge()
        if (is_string($jsonInput) && !empty($jsonInput)) {
            $decoded = json_decode($jsonInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // TODO explore to make more straightforward. This happens when lang check is invoked when email login
                Debugger::barDump('$jsonInput does not contain a valid JSON. No JSON checks invoked.');
            } else {
                if (!array_key_exists('REQUEST_METHOD', $superglobals->server)) {
                    // otherwise login by email fails
                    $superglobals->server['REQUEST_METHOD'] = 'POST';
                }
                parent::__construct($this->configuration, $superglobals);
            }
        }
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
        // check lang vs lang list
        if (
            !is_string($this->data->language) ||
            !in_array($this->data->language, $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST))
        ) {
            return self::response(400, 'Language not supported.');
        }

        $this->cookiePath = '/'; // todo limit - jako sb/auth:IM
        $this->cookieDomain = ''; // todo extract jako sb/auth:IM
        return $this->setLanguageCookie() //
            ? self::response(200, 'Language set.') : self::response(500, 'Language failed');
    }

    private function getLanguageValue(): string
    {
        // Check if the cookie exists and is not empty
        if (
            !empty($_COOKIE['sbLanguage']) && is_string($_COOKIE['sbLanguage']) //
            && in_array($_COOKIE['sbLanguage'], $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST))
        ) {
            $this->configuration->setString(I18nConstant::LANGUAGE, (string) $_COOKIE['sbLanguage']);
            return (string) $_COOKIE['sbLanguage'];
        }

        // If cookie is not set or empty or unsupported, return the first value of the language list
        $langList = $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST);
        $result = reset($langList);
        if ($result === false) {
            throw new \Exception('LANGUAGE_LIST is empty');
        }
        $this->configuration->setString(I18nConstant::LANGUAGE, $result);
        return $result;
    }

    private function setLanguageCookie(): bool
    {
        if (isset($this->data->language) && is_string($this->data->language)) {
            // TODO perhaps use the same method for setcookie 'sbRememberMe' in Sb/Auth::IM to use the same params
            // TODO check whether the default limitations of path and time fits
            return setcookie(
                'sbLanguage',
                $this->data->language /*,
                time() + 30 * 24 * 60 * 60, // expire time: days * hours * minutes * seconds
                $this->cookiePath,
                $this->cookieDomain,
                true,
                true */
            );
        }
        return false;
    }
}
