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

    add_action('swpfe_migrate_batch', function($args){
        error_log('[SWPFE MIGRATION TEST] swpfe_migrate_batch fired with args: ' . print_r($args, true));
    });


    if ( is_admin() ) {
        new App\AdvancedEntryManager\Admin\Admin();
    }
});

// Activation Hook - Create DB table
register_activation_hook( __FILE__, [ 'App\AdvancedEntryManager\DB_Handler', 'create_table' ] );

// add_filter('rest_authentication_errors', function () {
//     return new WP_Error(
//         'rest_disabled',
//         __('The REST API has been disabled.', 'your-text-domain'),
//         array('status' => 403)
//     );
// });

add_action('admin_notices', function () {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $rest_enabled = apply_filters( 'rest_authentication_errors', null );

    $check = wp_remote_get(rest_url('/aem/v1/forms'), [
        'headers' => [
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
        ]
    ]);

    if ( is_wp_error( $rest_enabled ) ) {
        echo '<div class="notice notice-error aem-notice"><p>';
        esc_html_e('🚫 Your site is blocking REST API access required by Advanced Entries Manager. Please whitelist /wp-json/aem/entries/v1/* to ensure full functionality.', 'save-wpf-entries');
        echo '</p></div>';
    }
});

