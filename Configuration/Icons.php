<?php

return [
    'ig-slug-rebuild' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => 'EXT:ig_slug/Resources/Public/Icons/' .
            (
                (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14
            ? 'module-ig-slug-v13.svg' : 'module-ig-slug.svg'
            ),
    ],
];

