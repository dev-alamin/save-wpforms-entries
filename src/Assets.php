<?php

namespace App\AdvancedEntryManager;

/**
 * Class Assets
 *
 * Handles the registration and enqueueing of admin assets.
 */
class Assets {

    /**
     * Constructor: Hook into admin asset loading.
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [ $this, 'register_assets' ]);
    }

    /**
     * Get all admin scripts.
     *
     * @return array
     */
    public function get_scripts() {
        $version = defined('AEMFW_VERSION') ? AEMFW_VERSION : time();

        return [
            'aemfw-tailwind-css' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/tailwind.min.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => false,
            ],
            'aemfw-admin-js' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/admin.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => true,
            ],
            'aemfw-collapse' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/collapse.js',
                'deps'    => [],
                'version' => null,
                'in_footer' => true,
            ],
            'aemfw-alpine' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/alpine.min.js',
                'deps'    => ['aemfw-collapse'],
                'version' => null,
                'in_footer' => true,
            ],
            'aemfw-lodash' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/lodash.min.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => false,
            ],
            'aemfw-lottie' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/lottie-player.js',
                'deps'    => [],
                'version' => '5.12.0',
                'in_footer' => false,
            ],
        ];
    }

    /**
     * Get all admin styles.
     *
     * @return array
     */
    public function get_styles() {
        $version = defined('AEMFW_VERSION') ? AEMFW_VERSION : time();

        return [
            'aemfw-admin-css' => [
                'src'     => AEMFW_ASSETS_URL . 'admin/admin.css',
                'deps'    => [],
                'version' => $version,
            ],
        ];
    }

    /**
     * Register and enqueue assets on specific admin pages.
     *
     * @param string $hook
     * @return void
     */
    public function register_assets( $hook ) {
        if ( ! in_array( $hook, [
            'toplevel_page_aemfw-entries',
            'wpforms-entries_page_aemfw-settings',
            'wpforms-entries_page_aemfw-migration',
        ], true ) ) {
            return;
        }

        // Register styles
        foreach ( $this->get_styles() as $handle => $style ) {
            wp_register_style(
                $handle,
                $style['src'],
                $style['deps'] ?? [],
                $style['version'] ?? false
            );

            wp_enqueue_style( $handle );
        }

        // Register scripts
        foreach ( $this->get_scripts() as $handle => $script ) {
            wp_register_script(
                $handle,
                $script['src'],
                $script['deps'] ?? [],
                $script['version'] ?? false,
                $script['in_footer'] ?? true
            );

            wp_enqueue_script( $handle );
        }

        // Localize main admin JS
        wp_localize_script( 'aemfw-admin-js', 'aemfwSettings', [
            'restUrl'  => esc_url_raw( rest_url() ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'perPage'  => get_option( 'aemfw_entries_per_page', 20 ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ] );

        wp_localize_script(
            'aemfw-admin-js',
            'aemfwMigrationNotice',
            array(
                'title'       => __('Migrate from WPFormsDB', 'advanced-entries-manager-for-wpforms'),
                'message'     => __('We found data in the legacy', 'advanced-entries-manager-for-wpforms') . ' <code>wpforms_db</code> ' . __('table. You can migrate all your entries into our advanced manager in just a few clicks.', 'advanced-entries-manager-for-wpforms'),
                'start'       => __('Start Migration', 'advanced-entries-manager-for-wpforms'),
                'dismissAlt'  => __('Dismiss', 'advanced-entries-manager-for-wpforms'),
            )
        );
    }
}
