<?php
/*
 * 2016 Romain CANON <romain.hydrocanon@gmail.com>
 *
 * This file is part of the TYPO3 Formz project.
 * It is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, either
 * version 3 of the License, or any later version.
 *
 * For the full copyright and license information, see:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Romm\Formz\Core;

use Romm\Formz\Configuration\ConfigurationFactory;
use Romm\Formz\Form\FormObjectFactory;
use Romm\Formz\Utility\TypoScriptUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class containing general functions.
 */
class Core implements SingletonInterface
{

    const EXTENSION_KEY = 'formz';
    const CACHE_IDENTIFIER = 'cache_formz';
    const GENERATED_FILES_PATH = 'typo3temp/Formz/';

    /**
     * @var Core
     */
    protected static $instance;

    /**
     * @var int|null
     */
    private $currentPageUid = -1;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var TypoScriptUtility
     */
    private $typoScriptUtility;

    /**
     * @var ConfigurationFactory
     */
    private $configurationFactory;

    /**
     * @var FormObjectFactory
     */
    private $formObjectFactory;

    /**
     * Contains the actual language key.
     *
     * @var string
     */
    private $languageKey;

    /**
     * @var array
     */
    private $extensionConfiguration;

    /**
     * @var FrontendInterface
     */
    protected $cacheInstance;

    /**
     * @return Core
     */
    public static function get()
    {
        if (null === self::$instance) {
            /** @var ObjectManager $objectManager */
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

            self::$instance = $objectManager->get(self::class);
        }

        return self::$instance;
    }

    /**
     * Translation handler. Does the same job as Extbase translation tools,
     * expect that if the index to the LLL reference is not found, the index is
     * returned (Extbase would have returned an empty string).
     *
     * @param    string $index        The index to the LLL reference.
     * @param    string $extensionKey Key of the extension containing the LLL reference.
     * @param    array  $arguments    Arguments passed over to vsprintf.
     * @return   string               The translated string.
     */
    public function translate($index, $extensionKey = null, $arguments = null)
    {
        $extensionKey = ($extensionKey) ?: self::EXTENSION_KEY;
        $result = LocalizationUtility::translate($index, $extensionKey, $arguments);
        if ($result === '' && $index !== '') {
            $result = $index;
        }

        return $result;
    }

    /**
     * Converts an array to a clean JSON string which can be used by JavaScript.
     *
     * @param array $array
     * @return string
     */
    public function arrayToJavaScriptJson(array $array)
    {
        return json_encode($array, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_TAG);
    }

    /**
     * Returns the current page uid, in a frontend or backend context.
     *
     * Returns null if the uid can't be found (backend module, ajax call, etc.).
     *
     * @return int|null
     */
    public function getCurrentPageUid()
    {
        if (-1 === $this->currentPageUid) {
            /** @var EnvironmentService $environmentService */
            $environmentService = GeneralUtility::makeInstance(EnvironmentService::class);

            $id = ($environmentService->isEnvironmentInFrontendMode())
                ? $this->getPageController()->id
                : GeneralUtility::_GP('id');

            if (false === MathUtility::canBeInterpretedAsInteger($id)
                || intval($id) < 0
            ) {
                $id = null;
            }

            $this->currentPageUid = $id;
        }

        return $this->currentPageUid;
    }

    /**
     * Allows you to set manually the current page uid. Useful when editing a
     * record, for example.
     *
     * @param int $uid The uid of the page.
     */
    public function setCurrentPageUid($uid)
    {
        $this->currentPageUid = intval($uid);
    }

    /**
     * Returns the cache instance for this extension.
     *
     * @return FrontendInterface
     */
    public function getCacheInstance()
    {
        if (null === $this->cacheInstance) {
            /** @var $cacheManager CacheManager */
            $cacheManager = $this->getObjectManager()->get(CacheManager::class);

            if ($cacheManager->hasCache(self::CACHE_IDENTIFIER)) {
                $this->cacheInstance = $cacheManager->getCache(self::CACHE_IDENTIFIER);
            }
        }

        return $this->cacheInstance;
    }

    /**
     * @param FrontendInterface $cacheInstance
     */
    public function setCacheInstance(FrontendInterface $cacheInstance)
    {
        $this->cacheInstance = $cacheInstance;
    }

    /**
     * Generic cache identifier creation for usages in the extension.
     *
     * @param string $string
     * @param string $formClassName
     * @param int    $maxLength
     * @return string
     */
    public function getCacheIdentifier($string, $formClassName, $maxLength = 55)
    {
        $explodedClassName = explode('\\', $formClassName);

        $identifier = strtolower(
            $string .
            end($explodedClassName) .
            '-' .
            sha1($formClassName)
        );

        return substr($identifier, 0, $maxLength);
    }

    /**
     * Return the extension configuration.
     *
     * @param string $configurationName If null, returns the whole configuration. Otherwise, returns the asked configuration.
     * @return array
     */
    public function getExtensionConfiguration($configurationName = null)
    {
        if (null === $this->extensionConfiguration) {
            $this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTENSION_KEY]);
            if (false === $this->extensionConfiguration) {
                $this->extensionConfiguration = [];
            }
        }

        $result = null;
        if (null === $configurationName) {
            $result = $this->extensionConfiguration;
        } elseif (ArrayUtility::isValidPath($this->extensionConfiguration, $configurationName, '.')) {
            $result = ArrayUtility::getValueByPath($this->extensionConfiguration, $configurationName, '.');
        }

        return $result;
    }

    /**
     * Function called when clearing TYPO3 caches. It will remove the temporary
     * asset files created by Formz.
     *
     * @param array $parameters
     */
    public function clearCacheCommand($parameters)
    {
        if (false === in_array($parameters['cacheCmd'], ['all', 'system'])) {
            return;
        }

        $files = glob(GeneralUtility::getFileAbsFileName(self::GENERATED_FILES_PATH . '*'));

        if (false === $files) {
            return;
        }

        foreach ($files as $assetCacheFile) {
            unlink($assetCacheFile);
        }
    }

    /**
     * Returns the current language key.
     *
     * @return string
     */
    public function getLanguageKey()
    {
        if (null === $this->languageKey) {
            $this->languageKey = 'default';

            /** @var EnvironmentService $environmentService */
            $environmentService = GeneralUtility::makeInstance(EnvironmentService::class);

            if ($environmentService->isEnvironmentInFrontendMode()) {
                $pageController = $this->getPageController();

                if (isset($pageController->config['config']['language'])) {
                    $this->languageKey = $pageController->config['config']['language'];
                }
            } else {
                $backendUser = $this->getBackendUser();

                if (strlen($backendUser->uc['lang']) > 0) {
                    $this->languageKey = $backendUser->uc['lang'];
                }
            }
        }

        return $this->languageKey;
    }

    /**
     * Will check if the TypoScript was indeed included, as it contains required
     * configuration to make the forms work properly.
     *
     * @return bool
     */
    public function isTypoScriptIncluded()
    {
        return (null !== $this->getTypoScriptUtility()->getExtensionConfigurationFromPath('settings.typoScriptIncluded'));
    }

    /**
     * @return bool
     */
    public function isInDebugMode()
    {
        return (bool)$this->getExtensionConfiguration('debugMode');
    }

    /**
     * @return ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return TypoScriptUtility
     */
    public function getTypoScriptUtility()
    {
        return $this->typoScriptUtility;
    }

    /**
     * @param TypoScriptUtility $typoScriptUtility
     */
    public function injectTypoScriptUtility(TypoScriptUtility $typoScriptUtility)
    {
        $this->typoScriptUtility = $typoScriptUtility;
    }

    /**
     * @return ConfigurationFactory
     */
    public function getConfigurationFactory()
    {
        return $this->configurationFactory;
    }

    /**
     * @param ConfigurationFactory $configurationFactory
     */
    public function injectConfigurationFactory(ConfigurationFactory $configurationFactory)
    {
        $this->configurationFactory = $configurationFactory;
    }

    /**
     * @return FormObjectFactory
     */
    public function getFormObjectFactory()
    {
        return $this->formObjectFactory;
    }

    /**
     * @param FormObjectFactory $formObjectFactory
     */
    public function injectFormObjectFactory(FormObjectFactory $formObjectFactory)
    {
        $this->formObjectFactory = $formObjectFactory;
    }

    /**
     * Returns the extension key.
     *
     * @return string
     */
    public function getExtensionKey()
    {
        return self::EXTENSION_KEY;
    }

    /**
     * @return TypoScriptFrontendController
     */
    public function getPageController()
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @return BackendUserAuthentication
     */
    public function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
