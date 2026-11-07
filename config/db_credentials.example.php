<?php
/**
 * Production database credentials (Hostinger, nap-ai.com).
 *
 * Copy this file to config/db_credentials.php on the server and fill in the
 * values from hPanel → Databases. db_credentials.php is gitignored — never
 * commit it. Local WAMP doesn't need it (db.php uses root / no password).
 */
return [
    'host' => '127.0.0.1',
    'user' => 'your_db_user',
    'pass' => 'your_db_password',
    'name' => 'your_db_name',
];
