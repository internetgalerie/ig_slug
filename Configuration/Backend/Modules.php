<?php

use Ig\IgSlug\Controller\SlugController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$menuPlacement = (int)(GeneralUtility::makeInstance(
    ExtensionConfiguration::class
)->get('ig_slug')['disableOwnMenuItem'] ?? 0);
                       
if ($menuPlacement == 1) {
    return [
        'web_info_IgSlug' => [
            'parent' => 'web_info',
            'position' => ['after' => 'web_info'],
            'access' => 'user',
            'iconIdentifier' => 'ig-slug-rebuild',
            'path' => '/module/web/info/ig-slug/rebuild',
            'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
            'labels' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf',
            'extensionName' => 'IgSlug',
            'controllerActions' => [
                SlugController::class => [
                    'list',
                    'update',
                ],
            ],
        ],
    ];
}

if ($menuPlacement == 2) {
    return [
        'site_IgSlug' => [
            'parent' => 'link_management',
            'position' => ['after' => 'short_urls'],
            'access' => 'user',
            'iconIdentifier' => 'ig-slug-rebuild',
            'path' => '/module/link-management/ig-slug/rebuild',
            'navigationComponent' => '@typo3/backend/tree/page-tree-element',
            'labels' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf',
            'extensionName' => 'IgSlug',
            'controllerActions' => [
                SlugController::class => [
                    'list',
                    'update',
                ],
            ],
        ],
    ];
}
if ($menuPlacement == 4) {
    return [
        'site_IgSlug' => [
            'parent' => 'site',
            'position' => ['after' => 'link_management'],
            'access' => 'user',
            'iconIdentifier' => 'ig-slug-rebuild',
            'path' => '/module/ig-slug/rebuild',
            'navigationComponent' => '@typo3/backend/tree/page-tree-element',
            'labels' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf',
            'extensionName' => 'IgSlug',
            'controllerActions' => [
                SlugController::class => [
                    'list',
                    'update',
                ],
            ],
        ],
    ];
}
return [
    'web_IgSlug' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'iconIdentifier' => 'ig-slug-rebuild',
        'path' => '/module/web/ig-slug/rebuild',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'labels' => 'LLL:EXT:ig_slug/Resources/Private/Language/locallang_rebuild.xlf',
        'extensionName' => 'IgSlug',
        'controllerActions' => [
            \Ig\IgSlug\Controller\SlugController::class => [
                'list',
                'update',
            ],
        ],
    ],
];