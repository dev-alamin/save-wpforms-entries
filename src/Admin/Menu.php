<?php

namespace App\AdvancedEntryManager\Admin;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Admin\Logs\LogViewerPage;

/**
 * Class Menu
 *
 * Handles the admin menu registration, settings page, and asset enqueuing
 * for the Advanced Entries Manager for WPForms plugin.
 */
class Menu {

    /**
     * LogViewer instance.
     * @var Assets
     */
    protected $log_viewer_page;

    /**
     * Constructor.
     *
     * Hooks into WordPress admin actions to initialize the admin menu,
     * enqueue assets, register settings, and hide update notices on plugin pages.
     */
    public function __construct() {
        $this->log_viewer_page = new LogViewerPage();
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_head', [$this, 'hide_update_notices']);
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_action('wp_ajax_femsave_settings', function () {
            check_ajax_referer('wp_rest');

            update_option('femexport_limit', absint($_POST['femexport_limit'] ?? 100));
            update_option('fem_entries_per_page', absint($_POST['fem_entries_per_page'] ?? 20));

            wp_send_json_success(['message' => 'Saved']);
        });
    }

    public function register_settings(){
        // OAuth credentials
        register_setting('femgoogle_settings', 'femgoogle_sheet_tab', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // New custom options
        register_setting('femgoogle_settings', 'fem_entries_per_page', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 25,
        ]);

        register_setting('femgoogle_settings', 'femgoogle_sheet_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('femgoogle_settings', 'femgoogle_sheet_auto_sync', [
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
            __('Forms Entries', 'forms-entries-manager'),
            __('Forms Entries', 'forms-entries-manager'),
            'manage_options',
            'forms-entries-manager',
            [$this, 'render_page'],
            'dashicons-feedback',
            25
        );

        add_submenu_page(
            'forms-entries-manager',
            __('WPForms Entry Sync Settings', 'forms-entries-manager'),
            __('Settings', 'forms-entries-manager'),
            'manage_options',
            'form-entries-settings',
            [$this, 'render_settings_page'],
            65
        );

        add_submenu_page(
            'forms-entries-manager',
            __('Logs', 'forms-entries-manager'),
            __('Logs', 'forms-entries-manager'),
            'manage_options',
            'forms-entries-manager-logs',
            [ $this->log_viewer_page, 'render_page' ]
        );

        if ( $legacy_table_exists && ! Helper::get_option( 'migration_complete' )  ) :
            add_submenu_page(
                'forms-entries-manager',
                __('Migration', 'forms-entries-manager'),
                __('Migration', 'forms-entries-manager'),
                'manage_options',
                'form-entries-migration',
                [ $this,  ]
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
     * Render log viewer page.
     *
     * Includes the log viewer page view file.
     *
     * @return void
     */
    public function render_log_viewer_page() {
        include __DIR__ . '/views/log-viewer-page.php';
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

        if ($screen && strpos($screen->id, 'fem') !== false) {
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
