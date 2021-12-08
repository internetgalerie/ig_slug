<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function () {
        if (TYPO3_MODE === 'BE') {
            $extConf = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('ig_slug');

            // drop on final TYPO3 11 release
            $typo3VersionNumberInteger = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getNumericTypo3Version());

            $controllerName = $typo3VersionNumberInteger >= 10000000 ? \Ig\IgSlug\Controller\SlugController::class : 'Slug';

            // since TYPO3 11: extensionName without vendor name
            // (see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.0/Breaking-92609-UseControllerClassesWhenRegisteringPluginsmodules.html)
            $extensionName = $typo3VersionNumberInteger >= 11000000 ? 'IgSlug' : 'Ig.IgSlug';

            if (isset($extConf['disableOwnMenuItem']) && $extConf['disableOwnMenuItem']==1) {
                \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
                    'web_info',
                    \Ig\IgSlug\Controller\SlugController::class,
                    null,
                    'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf:mlang_tabs_tab'
                );
            } else {
                \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                    $extensionName,
                    'web', // Make module a submodule of 'web'
                    'rebuild', // Submodule key
                    '', // Position
                    [
                        $controllerName => 'list, update',

                    ],
                    [
                        'access' => 'user,group',
                        'icon'   => 'EXT:ig_slug/Resources/Public/Icons/user_mod_rebuild.svg',
                        'labels' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf',
                    ]
                );
            }
        }
    }
);
