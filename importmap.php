<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'version' => '2.23.0',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
//    '@fullcalendar/core' => [
//        'path' => './node_modules/@fullcalendar/core/index.js',
//    ],
//    '@fullcalendar/daygrid' => [
//        'path' => './node_modules/@fullcalendar/daygrid/index.js',
//    ],
    'preact' => [
        'version' => '10.12.1',
    ],
    'preact/compat' => [
        'version' => '10.12.1',
    ],
    'preact/hooks' => [
        'version' => '10.12.1',
    ],
];
