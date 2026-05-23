<?php

declare(strict_types=1);

namespace Seablast\I18n;

use Seablast\I18n\Models\ApiLanguageModel;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;

class SeablastTranslate
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;
    /** @var array<string,bool> */
    private $loadedLanguages = [];
    /** @var array<string,array<string,string>> */
    private $translations = [];

    /**
     * @param SeablastConfiguration $configuration
     */
    public function __construct(SeablastConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Lazy language getter.
     * Invoke ApiLanguageModel only if the language is not defined yet.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function getLanguage(): string
    {
        if (!$this->configuration->exists(I18nConstant::LANGUAGE)) {
            $lang = new ApiLanguageModel($this->configuration, new Superglobals());
            $langKnowledge = $lang->knowledge();
            if ($langKnowledge->httpCode !== 200) {
                throw new \RuntimeException('Language settings failed.');
            }
        }

        try {
            return $this->configuration->getString(I18nConstant::LANGUAGE);
        } catch (SeablastConfigurationException $e) {
            throw new \RuntimeException('Language settings failed.', 0, $e);
        }
    }

    /**
     * Populates $this->translations dictionary.
     *
     * @param string $language
     * @return void
     * @throws DbmsException
     */
    private function retrieveTranslations(string $language): void
    {
        $stmt = $this->configuration->mysqli()->prepareStrict(
            'SELECT translation_key, translation_value FROM `' . $this->configuration->dbmsTablePrefix() //
            . 'translations` WHERE language = ?'
        );
        $stmt->bind_param('s', $language);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new DbmsException('Stmt get_result failed');
        }

        $this->translations[$language] = [];
        while ($row = $result->fetch_assoc()) {
            $this->translations[$language][(string) $row['translation_key']] = (string) $row['translation_value'];
        }

        $stmt->close();
        $this->loadedLanguages[$language] = true;
    }

    /**
     * Returns the dictionary translation of a string.
     *
     * @param string $original
     * @param string|null $language [OPTIONAL] If null, the current user's language is used.
     * @return string
     */
    public function translate(string $original, ?string $language = null): string
    {
        if (is_null($language)) {
            $language = $this->getLanguage();
        } elseif (!in_array($language, $this->configuration->getArrayString(I18nConstant::LANGUAGE_LIST), true)) {
            throw new \Exception("`{$language}` is not among expected languages");
        }

        if (!isset($this->loadedLanguages[$language])) {
            $this->retrieveTranslations($language);
        }

        return $this->translations[$language][$original] ?? $original;
    }
}
