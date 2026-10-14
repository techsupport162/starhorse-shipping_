<?php
/**
 * config/database.local.example.php
 * -----------------------------------------------------------------------
 * LIVE SERVER database credentials — template.
 *
 * 1. Copy this file to `database.local.php` in this same folder.
 * 2. Fill in the database name, user and password your hosting control
 *    panel gave you when you created the MySQL database.
 * 3. Upload database.local.php to the server only. Don't put it in any
 *    zip/backup you share.
 *
 * Use a dedicated MySQL user for this site with a strong password —
 * never `root`. It only needs SELECT, INSERT, UPDATE and DELETE on this
 * one database.
 *
 * The config/ folder is blocked from the web by .htaccess, and PHP files
 * are executed rather than shown, so these values are never visible to
 * visitors.
 * -----------------------------------------------------------------------
 */

define('DB_HOST', 'localhost');          // usually "localhost" on shared hosting
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_strong_password');
