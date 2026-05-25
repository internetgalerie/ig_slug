<?php

return
    (\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
    )->get('ig_slug')['disableOwnMenuItem'] ?? false
     ?
     [
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
                 \Ig\IgSlug\Controller\SlugController::class => [
                     'list',
                     'update',
                 ],
             ],
         ],
     ]
     :
     [
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
                 \Ig\IgSlug\Controller\SlugController::class => [
                     'list',
                     'update',
                 ],
             ],
         ],
     ]
    );
