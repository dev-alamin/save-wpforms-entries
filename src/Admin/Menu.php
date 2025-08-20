<?php

namespace App\AdvancedEntryManager\Admin;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Utility\Helper;
/**
 * Class Menu
 *
 * Handles the admin menu registration, settings page, and asset enqueuing
 * for the Advanced Entries Manager for WPForms plugin.
 */
class Menu {

    /**
     * Constructor.
     *
     * Hooks into WordPress admin actions to initialize the admin menu,
     * enqueue assets, register settings, and hide update notices on plugin pages.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_head', [$this, 'hide_update_notices']);
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_action('wp_ajax_aemfw_save_settings', function () {
            check_ajax_referer('wp_rest');

            update_option('aemfw_export_limit', absint($_POST['aemfw_export_limit'] ?? 100));
            update_option('aemfw_entries_per_page', absint($_POST['aemfw_entries_per_page'] ?? 20));

            wp_send_json_success(['message' => 'Saved']);
        });
    }

    public function register_settings(){
        // OAuth credentials
        register_setting('aemfw_google_settings', 'aemfw_google_sheet_tab', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // New custom options
        register_setting('aemfw_google_settings', 'aemfw_entries_per_page', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 25,
        ]);

        register_setting('aemfw_google_settings', 'aemfw_google_sheet_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('aemfw_google_settings', 'aemfw_google_sheet_auto_sync', [
            'type'              => 'boolean',
            'sanitize_callback' => function($val) {
                return $val === '1' || $val === 1;
            },
            'default' => true,
        ]);
    }

    /**
     * Add admin menu pages.
     *
     * Adds a top-level menu for WPForms Entries and a submenu for
     * plugin settings. Both are accessible only to users with
     * 'manage_options' capability.
     *
     * @return void
     */
    public function add_menu() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpforms_db';
        $legacy_table_exists = Helper::table_exists( $table);

        add_menu_page(
            __('WPForms Entries', 'advanced-entries-manager-for-wpforms'),
            __('WPForms Entries', 'advanced-entries-manager-for-wpforms'),
            'manage_options',
            'aemfw-entries',
            [$this, 'render_page'],
            'dashicons-feedback',
            25
        );

        add_submenu_page(
            'aemfw-entries',
            __('WPForms Entry Sync Settings', 'advanced-entries-manager-for-wpforms'),
            __('Settings', 'advanced-entries-manager-for-wpforms'),
            'manage_options',
            'aemfw-settings',
            [$this, 'render_settings_page'],
            65
        );

        if ( $legacy_table_exists && ! Helper::get_option( 'migration_complete' )  ) :
            add_submenu_page(
                'aemfw-entries',
                __('Migration', 'advanced-entries-manager-for-wpforms'),
                __('Migration', 'advanced-entries-manager-for-wpforms'),
                'manage_options',
                'aemfw-migration',
                [ $this, 'render_migration_page' ]
            );
        endif;
        }

    /**
     * Render main entries page.
     *
     * Includes the main admin page view file.
     *
     * @return void
     */
    public function render_page() {
        include __DIR__ . '/views/view-entries.php';
    }

    /**
     * Render settings page.
     *
     * Includes the settings page view file.
     *
     * @return void
     */
    public function render_settings_page() {
        include __DIR__ . '/views/settings-page.php';
    }

    /**
     * Render migration page.
     *
     * Includes the migration page view file.
     *
     * @return void
     */
    public function render_migration_page() {
        include __DIR__ . '/views/migration-page.php';
    }

    /**
     * Hide update notices on plugin admin pages.
     *
     * Prevents update nags, warnings, and other notices from
     * displaying on the plugin's admin screens to keep UI clean.
     *
     * @return void
     */
    public function hide_update_notices() {
        $screen = get_current_screen();

        if ($screen && strpos($screen->id, 'aemfw') !== false) {
            echo '<style>
                .update-nag, 
                .updated, 
                .notice, 
                .update-message,
                div.notice.notice-warning,
                .notice.is-dismissible {
                    display: none !important;
                }
            </style>';
        }
    }
}
