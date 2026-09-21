<?php
/**
 * Afrostrength contractors — settings.
 *
 * Copy to config.php, fill it in, and set the permissions to 600.
 * NEVER commit config.php. NEVER put a real password in this example.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        // Its OWN database, separate from the academy's. A contractor and a
        // learner are different data subjects on different lawful bases, and
        // a bug in a job board must not be able to read somebody's marks.
        'name' => 'yourcp_contractors',
        'user' => 'yourcp_contractors',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'site' => [
        // The main site. Not a subdomain: this IS afrostrength.com, and the
        // academy is the subdomain beside it at afrotech.afrostrength.com.
        'url' => 'https://afrostrength.com',
        // Empty, because this sits at the document root. Set it only if the
        // whole thing is ever moved into a subfolder.
        'base_path' => '',
        'phone' => '+234 810 019 1456',
        'phone_href' => 'tel:+2348100191456',
        'office_email' => 'reachus@afrostrength.com',
    ],

    'rate_limits' => [
        'join' => ['max' => 4, 'window' => 900],
        'post' => ['max' => 5, 'window' => 900],
    ],

    // A different secret from anything the academy uses.
    'ops_token' => '',
    'debug' => false,
];
