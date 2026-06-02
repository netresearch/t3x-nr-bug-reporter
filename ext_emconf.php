<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Bug Reporter',
    'description' => 'Attribute a TYPO3 error to its originating Composer package and open a prefilled issue on that package\'s upstream GitHub tracker. Adds an error-page report action and a proactive backend toolbar item.',
    'category' => 'be',
    'author' => 'Netresearch',
    'author_email' => '',
    'author_company' => 'Netresearch DTT GmbH',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
