<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Bug Reporter',
    'description' => 'Attribute an error to its originating Composer package and open a prefilled issue on that package\'s upstream GitHub tracker, from the error page or the backend toolbar.',
    'category' => 'be',
    'author' => 'Netresearch',
    'author_email' => '',
    'author_company' => 'Netresearch DTT GmbH',
    'state' => 'beta',
    'version' => '0.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
