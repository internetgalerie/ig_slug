<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

call_user_func(function () {
    // Configure new fields:
    $fields = array(
        'slug_locked' => array(
            'label' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_db.xlf:tx_domain_model_page.slug_locked',
            'exclude' => 0,
            'config' => array(
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                    ]
                ],
            ),
        )
    );

    // Add new fields to pages:
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $fields);
    $GLOBALS['TCA']['pages']['columns']['slug']['config']['size'] = 100;

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'title',
        '--linebreak--,slug_locked',
        'after:slug'
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'titleonly',
        '--linebreak--,slug_locked',
        'after:slug'
    );
});
