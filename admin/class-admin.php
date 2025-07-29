<?php

namespace SWPFE;
/**
 * Class Admin
 *
 * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Admin {

    /**
     * Constructor.
     *
     * Hooks into WordPress admin actions to initialize the admin menu,
     * enqueue assets, register settings, and hide update notices on plugin pages.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_head', [$this, 'hide_update_notices']);
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_action('wp_ajax_swpfe_save_settings', function () {
            check_ajax_referer('wp_rest');

            update_option('swpfe_export_limit', absint($_POST['swpfe_export_limit'] ?? 100));
            update_option('swpfe_entries_per_page', absint($_POST['swpfe_entries_per_page'] ?? 20));

            wp_send_json_success(['message' => 'Saved']);
        });


    }

    public function register_settings(){
        // OAuth credentials
        register_setting('swpfe_google_settings', 'swpfe_google_sheet_tab', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // New custom options
        register_setting('swpfe_google_settings', 'swpfe_entries_per_page', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 25,
        ]);

        register_setting('swpfe_google_settings', 'swpfe_google_sheet_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('swpfe_google_settings', 'swpfe_google_sheet_auto_sync', [
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
        add_menu_page(
            __('WPForms Entries', 'advanced-entries-manager-for-wpforms'),
            __('WPForms Entries', 'advanced-entries-manager-for-wpforms'),
            'manage_options',
            'swpfe-entries',
            [$this, 'render_page'],
            'dashicons-feedback',
            25
        );

        add_submenu_page(
            'swpfe-entries',
            __('WPForms Entry Sync Settings', 'advanced-entries-manager-for-wpforms'),
            __('Settings', 'advanced-entries-manager-for-wpforms'),
            'manage_options',
            'swpfe-settings',
            [$this, 'render_settings_page'],
            65
        );
    }

    /**
     * Render main entries page.
     *
     * Includes the main admin page view file.
     *
     * @return void
     */
    public function render_page() {
        include SWPFE_PATH . 'admin/views/view-entries.php';
    }

    /**
     * Render settings page.
     *
     * Includes the settings page view file.
     *
     * @return void
     */
    public function render_settings_page() {
        include SWPFE_PATH . 'admin/views/settings-page.php';
    }

    /**
     * Enqueue admin CSS and JavaScript assets.
     *
     * Only enqueues assets on the plugin's own admin pages to avoid
     * unnecessary loading elsewhere.
     * Uses `wp_localize_script` to pass REST API URL and nonce
     * for secure AJAX requests.
     *
     * Uses cache-busting based on current timestamp (consider
     * replacing with plugin version constant in production).
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     */
    public function enqueue_assets($hook) {
        // Only enqueue on our plugin pages
        if ($hook !== 'toplevel_page_swpfe-entries' && $hook !== 'wpforms-entries_page_swpfe-settings') {
            return;
        }

        $version = defined('SWPFE_VERSION') ? SWPFE_VERSION : time();

        wp_enqueue_style('swpfe-admin-css', SWPFE_URL . 'admin/assets/admin.css', [], $version);
        wp_enqueue_script('swpfe-tailwind-css', SWPFE_URL . 'admin/assets/tailwind.min.js', [], $version, false);
        wp_enqueue_script('swpfe-admin-js', SWPFE_URL . 'admin/assets/admin.js', [], $version, true);
        wp_enqueue_script('alpine-collapse', SWPFE_URL . 'admin/assets/collapse.js', [], null, true );
        wp_enqueue_script('swpfe-alpine', SWPFE_URL . 'admin/assets/alpine.min.js', ['alpine-collapse'], null, true);
        wp_enqueue_script('swpfe-lodash', SWPFE_URL . 'admin/assets/lodash.min.js', [], $version, false);
        wp_enqueue_script('lottie-web', SWPFE_URL . 'admin/assets/lottie-player.js', [], '5.12.0', false);


        wp_localize_script('swpfe-admin-js', 'swpfeSettings', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce'   => wp_create_nonce('wp_rest'),
            'perPage' => get_option('swpfe_entries_per_page', 20),
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
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

        if ($screen && strpos($screen->id, 'swpfe') !== false) {
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
