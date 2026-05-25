<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Rebuild URL slugs',
    'description' => 'Rebuild URL slugs of the pages table and others',
    'category' => 'module',
    'author' => 'Daniel Abplanalp',
    'author_email' => 'typo3@internetgalerie.ch',
    'author_company' => 'Internetgalerie AG',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
