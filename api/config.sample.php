<?php
/*
 * Master Minds — server settings.
 *
 * 1. Copy this file and name the copy  mm-config.php
 * 2. Put it in the folder ABOVE public_html (e.g. /domains/yourdomain.com/mm-config.php)
 *    so it is never reachable from the web and never overwritten by deploys.
 *    (If you can't, name it config.php and put it in public_html/api/ instead.)
 * 3. Fill in the values below from hPanel → Databases → Management.
 */
return [
    'db_host' => 'localhost',            // Hostinger: usually 'localhost'
    'db_name' => 'u123456789_masterminds',
    'db_user' => 'u123456789_admin',
    'db_pass' => 'your-database-password',

    // Any long random text. Used to anonymise visitor IP addresses for spam protection.
    'secret'  => 'change-this-to-any-long-random-text',

    // Optional: get an email for every new enquiry. Leave '' to turn off.
    'notify_email' => '',

    // Optional: your full website address, e.g. 'https://mymasterminds.in'.
    // Leave '' to detect it automatically.
    'site_url' => '',

    // Only needed if the website is hosted on a DIFFERENT domain from this API
    // (e.g. GitHub Pages). List the website's address(es) exactly, e.g. ['https://example.github.io'].
    'allowed_origins' => [],
];
