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
        $version = defined('SWPFE_VERSION') ? SWPFE_VERSION : time();

        return [
            'swpfe-tailwind-css' => [
                'src'     => SWPFE_URL . 'assets/admin/tailwind.min.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => false,
            ],
            'swpfe-admin-js' => [
                'src'     => SWPFE_URL . 'assets/admin/admin.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => true,
            ],
            'swpfe-collapse' => [
                'src'     => SWPFE_URL . 'assets/admin/collapse.js',
                'deps'    => [],
                'version' => null,
                'in_footer' => true,
            ],
            'swpfe-alpine' => [
                'src'     => SWPFE_URL . 'assets/admin/alpine.min.js',
                'deps'    => ['swpfe-collapse'],
                'version' => null,
                'in_footer' => true,
            ],
            'swpfe-lodash' => [
                'src'     => SWPFE_URL . 'assets/admin/lodash.min.js',
                'deps'    => [],
                'version' => $version,
                'in_footer' => false,
            ],
            'swpfe-lottie' => [
                'src'     => SWPFE_URL . 'assets/admin/lottie-player.js',
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
        $version = defined('SWPFE_VERSION') ? SWPFE_VERSION : time();

        return [
            'swpfe-admin-css' => [
                'src'     => SWPFE_URL . 'assets/admin/admin.css',
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
            'toplevel_page_swpfe-entries',
            'wpforms-entries_page_swpfe-settings',
            'wpforms-entries_page_swpfe-migration',
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
        wp_localize_script( 'swpfe-admin-js', 'swpfeSettings', [
            'restUrl'  => esc_url_raw( rest_url() ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'perPage'  => get_option( 'swpfe_entries_per_page', 20 ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ] );
    }
}
