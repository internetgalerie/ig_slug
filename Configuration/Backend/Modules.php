<?php

return
    (\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
    )->get('ig_slug')['disableOwnMenuItem'] ?? false
     ?
     [
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
                 \Ig\IgSlug\Controller\SlugController::class => [
                     'list',
                     'update',
                 ],
             ],
         ],
     ]
     :
     [
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
     ]
    );
