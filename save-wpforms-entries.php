<?php
/**
 * Plugin Name: Save WPFORMS Entries
 * Plugin URI:  https://almn.me/save-wpforms-entries
 * Description: Easily store all your WPForms submissions directly in your WordPress database.
 * Version:     1.0.0
 * Author:      Md. Al Amin
 * Author URI:  https://almn.me
 * Text Domain: save-wpf-entries
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.4
 * Requires PHP: 7.0
 * Requires Plugins: WPForms Lite
 *
 * @package     save-wpforms-entries
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

require_once SWPFE_PATH . 'admin/class-db-handler.php';
require_once SWPFE_PATH . 'admin/class-admin.php';
require_once SWPFE_PATH . 'includes/class-entry-handler.php';
require_once SWPFE_PATH . 'includes/api/class-rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/google-sheet/class-send-data.php';

// Load textdomain
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'save-wpf-entries', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    new SWPFE\Rest_API();
    new SWPFE\GSHEET\Send_Data();

    if ( is_admin() ) {
        new SWPFE\Admin();
    }
});

// Activation Hook - Create DB table
register_activation_hook( __FILE__, [ 'SWPFE\DB_Handler', 'create_table' ] );

add_action('init', 'swpfe_capture_token_from_url');

function swpfe_capture_token_from_url() {
    if ( isset($_GET['access_token']) ) {
        update_option('swpfe_google_access_token', sanitize_text_field($_GET['access_token']));
        update_option('swpfe_google_token_expires', time() + 3600); // fallback

        // Optional: redirect to settings or a thank you screen
        wp_safe_redirect(admin_url('admin.php?page=swpfe-settings&connected=true'));
        exit;
    }
}
