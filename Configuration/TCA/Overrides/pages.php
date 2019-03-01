<?php
if (!defined('TYPO3_MODE')) {
  die ('Access denied.');
}

call_user_func(function () {
    // Configure new fields:
    $fields = array(
		    'slug_locked' => array(
					      'label' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_db.xlf:tx_domain_model_page.slug_locked',
					      'exclude' => 1,
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
    $GLOBALS['TCA']['pages']['palettes']['title']['showitem'] = 'title;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.title_formlabel, --linebreak--, slug, slug_locked, --linebreak--, nav_title;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.nav_title_formlabel, --linebreak--, subtitle;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.subtitle_formlabel';
    $GLOBALS['TCA']['pages']['palettes']['titleonly']['showitem'] = 'title;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.title_formlabel, --linebreak--, slug, slug_locked';
  });
