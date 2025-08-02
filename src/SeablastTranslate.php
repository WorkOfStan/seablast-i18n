<?php

declare(strict_types=1);

namespace Seablast\I18n;

use Seablast\I18n\Models\ApiLanguageModel;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Tracy\Debugger;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

class SeablastTranslate
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;
    /** @var array<string> */
    private $translations = [];

    /**
     * @param SeablastConfiguration $configuration
     */
    public function __construct(SeablastConfiguration $configuration)
    {
        $this->configuration = $configuration; //for getString(SB:LANGUAGE) and database
        // @phpstan-ignore notIdentical.alwaysFalse
        if (I18nConstant::LANGUAGE !== 'SB:LANGUAGE') {
            Debugger::barDump(
                'Latte uses `SB:LANGUAGE` directly, so it MUST be equal to I18nConstant::LANGUAGE in configuration',
                'SB:LANGUAGE ERROR'
            );
            Debugger::log(
                'Latte uses `SB:LANGUAGE` directly, so it MUST be equal to I18nConstant::LANGUAGE in configuration',
                ILogger::ERROR
            );
        }
    }

    /**
     * Lazy language getter.
     * Invoke ApiLanguageModel ONLY IF `SB:LANGUAGE` is not defined.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function getLanguage(): string
    {
        if (!$this->configuration->exists('SB:LANGUAGE')) {
            $lang = new ApiLanguageModel($this->configuration, new \Seablast\Seablast\Superglobals());
            $langKnowledge = $lang->knowledge();
            Debugger::barDump($langKnowledge, 'lang knowledge');
            Assert::eq($langKnowledge->httpCode, 200, 'Language settings failed.');
            Debugger::barDump($this->configuration->getString('SB:LANGUAGE'), 'Lazy init of language');
            // re-check whether SB:LANGUAGE was actually set
            // @phpstan-ignore booleanNot.alwaysTrue
            if (!$this->configuration->exists('SB:LANGUAGE')) {
                throw new \RuntimeException('missing language'); // Note: there might not be a default language.
            }
        }
        return $this->configuration->getString('SB:LANGUAGE');
    }

    /**
     * Populates $this->translations dictionary.
     */
    private function retrieveTranslations(): void
    {
        //Debugger::barDump('retrieveTranslations called');
        // get translations from db to array according to the SB:LANGUAGE
        $stmt = $this->configuration->mysqli()->prepareStrict(
            'SELECT translation_key, translation_value FROM `' . $this->configuration->dbmsTablePrefix() //
            . 'translations` WHERE language = ?'
        );
        $stmt->bind_param('s', $this->getLanguage());
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            // Database Tracy BarPanel is displayed in try-catch in SeablastView
            throw new DbmsException('Stmt get_result failed');
        }

        while ($row = $result->fetch_assoc()) {
            $this->translations[$row['translation_key']] = (string) $row['translation_value'];
        }

        $stmt->close();
    }

    public function translate(string $original): string
    {
        // Lazy init
        if (empty($this->translations)) {
            $this->retrieveTranslations();
        }
        // $original => translated according to getString(SB:LANGUAGE)
        return $this->translations[$original] ?? $original;
    }
}
