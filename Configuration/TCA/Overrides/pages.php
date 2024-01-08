<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

// Configure new fields:
$fields = [
    'slug_locked' => [
        'label' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_db.xlf:tx_domain_model_page.slug_locked',
        'exclude' => 0,
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                [
                    'label' => '',
                    'labelChecked' => '',
                    'labelUnchecked' => '',
                ]
            ],
        ],
    ]
];

// Add new fields to pages:
ExtensionManagementUtility::addTCAcolumns('pages', $fields);
$GLOBALS['TCA']['pages']['columns']['slug']['config']['size'] = 100;

ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'title',
    '--linebreak--,slug_locked',
    'after:slug'
);

ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'titleonly',
    '--linebreak--,slug_locked',
    'after:slug'
);
