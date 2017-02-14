<?php
declare(strict_types=1);
namespace Viserio\Component\Translation;

use Psr\Log\LoggerAwareInterface;
use RuntimeException;
use Viserio\Component\Contracts\Log\Traits\LoggerAwareTrait;
use Viserio\Component\Contracts\Parsers\Traits\LoaderAwareTrait;
use Viserio\Component\Contracts\Translation\MessageCatalogue as MessageCatalogueContract;
use Viserio\Component\Contracts\Translation\MessageSelector as MessageSelectorContract;
use Viserio\Component\Contracts\Translation\PluralizationRules as PluralizationRulesContract;
use Viserio\Component\Contracts\Translation\TranslationManager as TranslationManagerContract;
use Viserio\Component\Contracts\Translation\Translator as TranslatorContract;
use Viserio\Component\Support\Traits\NormalizePathAndDirectorySeparatorTrait;
use Viserio\Component\Translation\Traits\ValidateLocaleTrait;

class TranslationManager implements TranslationManagerContract, LoggerAwareInterface
{
    use ValidateLocaleTrait;
    use LoaderAwareTrait;
    use LoggerAwareTrait;
    use NormalizePathAndDirectorySeparatorTrait;

    /**
     * PluralizationRules instance.
     *
     * @var \Viserio\Component\Contracts\Translation\PluralizationRules
     */
    protected $pluralization;

    /**
     * MessageSelector instance.
     *
     * @var \Viserio\Component\Contracts\Translation\MessageSelector
     */
    protected $messageSelector;

    /**
     * A string dictating the default language to translate into. (e.g. 'en').
     *
     * @var string
     */
    protected $locale = 'en';

    /**
     * Default fallback for all languages.
     *
     * @var MessageCatalogueContract
     */
    protected $defaultFallback;

    /**
     * Fallbacks for speziall languages.
     *
     * @var array
     */
    protected $langFallback = [];

    /**
     * All directories to look for a file.
     *
     * @var array
     */
    protected $directories = [];

    /**
     * All added translations.
     *
     * @var array
     */
    protected $translations = [];

    /**
     * Creat new Translation instance.
     *
     * @param \Viserio\Component\Contracts\Translation\PluralizationRules $pluralization
     * @param \Viserio\Component\Contracts\Translation\MessageSelector    $messageSelector
     */
    public function __construct(
        PluralizationRulesContract $pluralization,
        MessageSelectorContract $messageSelector
    ) {
        $this->pluralization = $pluralization;

        $messageSelector->setPluralization($pluralization);
        $this->messageSelector = $messageSelector;
    }

    /**
     * Set directories.
     *
     * @param array $directories
     *
     * @return $this
     */
    public function setDirectories(array $directories): TranslationManager
    {
        foreach ($directories as $directory) {
            $this->addDirectory($directory);
        }

        return $this;
    }

    /**
     * Get directories.
     *
     * @return array
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    /**
     * Add directory.
     *
     * @param string $directory
     *
     * @return $this
     */
    public function addDirectory(string $directory): TranslationManager
    {
        if (! in_array($directory, $this->directories)) {
            $this->directories[] = self::normalizeDirectorySeparator($directory);
        }

        return $this;
    }

    /**
     * Import a language from file.
     *
     * @param string $file
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function import(string $file): TranslationManager
    {
        $loader = $this->getLoader();
        $loader->setDirectories($this->directories);

        $langFile = $loader->load($file);

        if (! isset($langFile['lang'])) {
            throw new RuntimeException(sprintf('File [%s] cant be imported. Key for language is missing.', $file));
        }

        $this->addMessageCatalogue(new MessageCatalogue($langFile['lang'], $langFile));

        return $this;
    }

    /**
     * Add message catalogue.
     *
     * @param \Viserio\Component\Contracts\Translation\MessageCatalogue $messageCatalogue
     *
     * @return $this
     */
    public function addMessageCatalogue(MessageCatalogueContract $messageCatalogue): TranslationManager
    {
        $locale = $messageCatalogue->getLocale();

        if ($fallback = $this->getLanguageFallback($messageCatalogue->getLocale())) {
            $messageCatalogue->addFallbackCatalogue($fallback);
        } elseif ($fallback = $this->defaultFallback) {
            $messageCatalogue->addFallbackCatalogue($fallback);
        }

        $translation = new Translator($messageCatalogue, $this->messageSelector);

        if ($this->logger !== null) {
            $translation->setLogger($this->logger);
        }

        $this->translations[$locale] = $translation;

        return $this;
    }

    /**
     * Set default fallback for all languages.
     *
     * @param \Viserio\Component\Contracts\Translation\MessageCatalogue $fallback
     *
     * @return $this
     */
    public function setDefaultFallback(MessageCatalogueContract $fallback): TranslationManager
    {
        $this->defaultFallback = $fallback;

        return $this;
    }

    /**
     * Get default fallback.
     *
     * @return \Viserio\Component\Contracts\Translation\MessageCatalogue
     */
    public function getDefaultFallback(): MessageCatalogueContract
    {
        return $this->defaultFallback;
    }

    /**
     * Set fallback for a language.
     *
     * @param string                                                    $lang
     * @param \Viserio\Component\Contracts\Translation\MessageCatalogue $fallback
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function setLanguageFallback(string $lang, MessageCatalogueContract $fallback)
    {
        $this->langFallback[$lang] = $fallback;

        return $this;
    }

    /**
     * Get fallback for a language.
     *
     * @param string $lang
     *
     * @return MessageCatalogueContract|null
     */
    public function getLanguageFallback(string $lang)
    {
        if (isset($this->langFallback[$lang])) {
            return $this->langFallback[$lang];
        }
    }

    /**
     * Gets the string dictating the default language.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the string dictating the default language to translate into. (e.g. 'en').
     *
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale(string $locale): TranslationManager
    {
        self::assertValidLocale($locale);

        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns the pluralization instance.
     *
     * @return \Viserio\Component\Translation\PluralizationRules
     */
    public function getPluralization(): PluralizationRulesContract
    {
        return $this->pluralization;
    }

    /**
     * Get a language translator instance.
     *
     * @param string|null $locale
     *
     * @throws \RuntimeException
     *
     * @return \Viserio\Component\Contracts\Translation\Translator
     */
    public function getTranslator(string $locale = null): TranslatorContract
    {
        $lang = $locale ?? $this->locale;

        if (isset($this->translations[$lang])) {
            return $this->translations[$lang];
        }

        throw new RuntimeException(sprintf('Translator for [%s] dont exist.', $lang));
    }
}