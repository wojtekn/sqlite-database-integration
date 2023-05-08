<?php
/**
 * Plugin Name: SQLite Database Integration
 * Description: SQLite database driver drop-in.
 * Author: WordPress Performance Team
 * Version: 2.1.0
 * Requires PHP: 5.6
 * Textdomain: sqlite-database-integration
 *
 * This feature plugin allows WordPress to use SQLite instead of MySQL as its database.
 *
 * @package wp-sqlite-integration
 */

define( 'SQLITE_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/admin-page.php';
require_once __DIR__ . '/activate.php';
require_once __DIR__ . '/deactivate.php';
require_once __DIR__ . '/admin-notices.php';
require_once __DIR__ . '/health-check.php';
