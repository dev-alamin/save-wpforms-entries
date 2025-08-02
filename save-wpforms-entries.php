<?php
/**
 * Plugin Name: Advanced Entries Manager for WPFroms
 * Plugin URI:  https://almn.me/advanced-entries-manager-for-wpforms
 * Description: The ultimate dashboard to manage, search, and sync WPForms entries like a pro.
 * Version:     1.0.0
 * Author:      Md. Al Amin
 * Author URI:  https://almn.me
 * Text Domain: advanced-entries-manager-for-wpforms
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.4
 * Requires PHP: 7.0
 * Requires Plugins: WPForms Lite
 *
 * @package     advanced-entries-manager
 * @author      Md. Al Amin
 * @copyright   Al Amin
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:     swpfe
 */

defined( 'ABSPATH' ) || exit;

define( 'SWPFE_VERSION', WP_DEBUG_LOG ? time() : '1.0.0' );
define( 'SWPFE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SWPFE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

// Load textdomain
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'save-wpf-entries', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    new App\AdvancedEntryManager\Api\Route();
    new App\AdvancedEntryManager\Entry_Handler();
    new App\AdvancedEntryManager\Scheduler\Actions\Migrate_Batch();

    if ( is_admin() ) {
        new App\AdvancedEntryManager\Admin\Admin();
    }
});

// Activation Hook - Create DB table
register_activation_hook( __FILE__, [ 'App\AdvancedEntryManager\DB_Handler', 'create_table' ] );