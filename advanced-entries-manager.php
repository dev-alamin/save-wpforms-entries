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
 * Prefix:     aemfw
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Define the version of the plugin.
 * 
 * This is used to manage cache busting for assets and for version control.
 * If WP_DEBUG_LOG is enabled, it uses the current timestamp; otherwise, it uses a fixed version number.
 */

if( ! defined( 'AEMFW_VERSION' ) ) {
    define( 'AEMFW_VERSION', WP_DEBUG_LOG ? time() : '1.0.0' );
}

/**
 * Define the path for the plugin.
 * 
 * This is used to include files and for various plugin functionalities.
 */
if( ! defined( 'AEMFW_PATH' ) ) {
    define( 'AEMFW_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Define the URL for the plugin.
 * 
 * This is used to load assets and for various plugin functionalities.
 */
if( ! defined( 'AEMFW_URL' ) ) {
    define( 'AEMFW_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Define the assets URL for the plugin.
 * 
 * This is used to load CSS, JS, and other assets from the plugin directory.
 */
if( ! defined( 'AEMFW_ASSETS_URL' ) ) {
    define( 'AEMFW_ASSETS_URL', AEMFW_URL . 'assets/' );
}

/**
 * Define the prefix for generic usage without i18n.
 * 
 * This is used to ensure that the plugin's prefix is consistent across the plugin.
 */
if( ! defined( 'AEMFW_PREFIX' ) ) {
    define( 'AEMFW_PREFIX', 'AEMFW_' );
}

/**
 * Define the table name without prefix for storing WPForms entries.
 * 
 * This is used to ensure the table name is consistent across the plugin.
 * It is also used in the database handler to create and access the custom entries table.
 */
if( ! defined( 'AEMFW_TABLE_NAME' ) ) {
    define( 'AEMFW_TABLE_NAME', 'aemfw_entries_manager' );
}

/**
 * Define the plugin base path for use in various functionalities.
 * 
 * This is used to ensure that the plugin's base path is consistent across the plugin.
 * It is also used in the plugin's main file to ensure that the plugin is loaded correctly
 */
define( 'AEMFW_PLUGIN_BASE', plugin_basename( __FILE__ ) );

use App\AdvancedEntryManager\Plugin;

/**
 * Initialize the plugin.
 *
 * This function is called to start the plugin and set up necessary components.
 */
function aemfw_init() {
    return Plugin::init();
}

// Kick-off the plugin initialization
aemfw_init();

// Print all capabilities of the current user for debugging purposes
// if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
//     add_action( 'admin_init', function() {
//         $current_user = wp_get_current_user();
//         if ( $current_user ) {
//             echo '<pre>';
//             echo 'Current User Capabilities:';
//             print_r( $current_user->allcaps );
//             echo '</pre>';
//         } else {
//             echo '<pre>';
//             echo 'No current user found.';
//             echo '</pre>';
//         }
//     } );
// }

/**
 * A debugging utility to unschedule all pending export jobs.
 *
 * To use: visit a URL like yoursite.com/wp-admin/?stop_export_jobs=true
 *
 * @return void
 */
function stop_all_export_jobs_for_debug() {
    // Check for a specific GET parameter and ensure the user is an admin
    if (isset($_GET['stop_export_jobs']) && current_user_can('manage_options')) {
        
        // Use a static hook and group names to match your implementation
        $batch_hook = 'aemfw_process_export_batch'; // Replace with your actual hook name
        $finalize_hook = 'aemfw_finalize_export_file'; // Replace with your actual hook name
        $group = 'aemfw_exports'; // Replace with your actual group name

        // Unschedule all pending batch processing actions
        $count_batch = as_unschedule_all_actions($batch_hook, [], $group);

        // Unschedule all pending finalization actions
        $count_finalize = as_unschedule_all_actions($finalize_hook, [], $group);
        
        $message = sprintf(
            'Action Scheduler Debug: Stopped %d batch jobs and %d finalization jobs.',
            $count_batch,
            $count_finalize
        );
        
        // Print a confirmation message for debugging
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });

        // Optional: Also delete any job transients for a clean slate
        // Note: This would require looping through all transients to find them,
        // so it's often easier to do manually or with a dedicated tool.
    }
}
add_action('admin_init', 'stop_all_export_jobs_for_debug');