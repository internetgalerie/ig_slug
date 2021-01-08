<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function () {
        if (TYPO3_MODE === 'BE') {
            $extConf = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('ig_slug');

            if (isset($extConf['disableOwnMenuItem']) && $extConf['disableOwnMenuItem']==1) {
                \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
                    'web_info',
                    \Ig\IgSlug\Controller\SlugController::class,
                    null,
                    'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf:mlang_tabs_tab'
                );
            } else {
                \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                    'Ig.IgSlug',
                    'web', // Make module a submodule of 'web'
                    'rebuild', // Submodule key
                    '', // Position
                    [
                        \Ig\IgSlug\Controller\SlugController::class => 'list, update',
                    
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
