<?php
/**
 * Plugin Name: Pressundo Framework
 * Plugin URI: https://pressundo.com/
 * Description: Modern WordPress developer framework with progressive data migration.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Pressundo
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressundo-framework
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('PRESSUNDO_FRAMEWORK_VERSION', '1.0.0');
define('PRESSUNDO_FRAMEWORK_API_VERSION', '1');
define('PRESSUNDO_FRAMEWORK_FILE', __FILE__);
define('PRESSUNDO_FRAMEWORK_DIR', plugin_dir_path(__FILE__));
define('PRESSUNDO_FRAMEWORK_URL', plugin_dir_url(__FILE__));

require_once PRESSUNDO_FRAMEWORK_DIR . 'src/Support/Autoloader.php';
\Pressundo\Framework\Support\Autoloader::register(PRESSUNDO_FRAMEWORK_DIR . 'src/');
require_once PRESSUNDO_FRAMEWORK_DIR . 'src/Api/functions.php';

// Register the lightweight Vafpress public-API compatibility bridge before the active theme loads.
\Pressundo\Framework\Legacy\Vafpress\LegacyRuntime::register();

add_action('plugins_loaded', static function (): void {
    \Pressundo\Framework\Bootstrap::instance()->boot();
}, 5);
