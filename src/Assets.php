<?php

namespace App\AdvancedEntryManager;

defined('ABSPATH') || exit;
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
                'version' => filemtime( AEMFW_PATH . 'admin/admin.js' ),
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
                'version' => filemtime( AEMFW_PATH . 'admin/admin.css' ),
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

        wp_localize_script(
            'aemfw-admin-js',
            'aemfwStrings',
            array(
                'title'       => __('Migrate from WPFormsDB', 'advanced-entries-manager-for-wpforms'),
                'message'     => __('We found data in the legacy', 'advanced-entries-manager-for-wpforms') . ' <code>wpforms_db</code> ' . __('table. You can migrate all your entries into our advanced manager in just a few clicks.', 'advanced-entries-manager-for-wpforms'),
                'start'       => __('Start Migration', 'advanced-entries-manager-for-wpforms'),
                'dismissAlt'  => __('Dismiss', 'advanced-entries-manager-for-wpforms'),
                // General Messages
                'csvExportedSuccess'      => __('✅ CSV exported successfully!', 'advanced-entries-manager-for-wpforms'),
                'entryUpdatedSuccess'     => __('✅ %s entries updated successfully!', 'advanced-entries-manager-for-wpforms'),
                'entryDeletedSuccess'     => __('✅ %s', 'advanced-entries-manager-for-wpforms'),
                'changesSavedSuccess'     => __('✅ Saved changes successfully!', 'advanced-entries-manager-for-wpforms'),
                'settingsSavedSuccess'    => __('✅ Settings saved successfully!', 'advanced-entries-manager-for-wpforms'),

                // Time ago
                'timeAgoJustNow'          => __('just now', 'advanced-entries-manager-for-wpforms'),
                'timeAgoMinutes'          => _n('%d minute ago', '%d minutes ago', 0, 'advanced-entries-manager-for-wpforms'), // Use a plural-aware function
                'timeAgoHours'            => _n('%d hour ago', '%d hours ago', 0, 'advanced-entries-manager-for-wpforms'),
                'timeAgoYesterday'        => __('Yesterday', 'advanced-entries-manager-for-wpforms'),

                // Errors & Warnings
                'noteTooLong'             => __('Note is too long. Please limit to 1000 characters.', 'advanced-entries-manager-for-wpforms'),
                'deleteFailedUnknown'     => __('Failed to delete entry: Unknown error', 'advanced-entries-manager-for-wpforms'),
                'deleteRequestFailed'     => __('Delete request failed. Check console for details.', 'advanced-entries-manager-for-wpforms'),
                'networkError'            => __('A network error occurred. Please try again.', 'advanced-entries-manager-for-wpforms'),
                'entryNotFound'           => __('❌ Entry not found in the list.', 'advanced-entries-manager-for-wpforms'),
                'bulkActionFailed'        => __('Bulk action failed:', 'advanced-entries-manager-for-wpforms'),
                'exportFailed'            => __('Failed to start export.', 'advanced-entries-manager-for-wpforms'),
                'exportProgressFailed'    => __('Failed to fetch export progress.', 'advanced-entries-manager-for-wpforms'),
                'exportSelectForm'        => __('Please select a form before exporting.', 'advanced-entries-manager-for-wpforms'),
                'exportInvalidCSV'        => __('Invalid CSV content.', 'advanced-entries-manager-for-wpforms'),
                'exportComplete'          => __('Export complete! Your download should start shortly.', 'advanced-entries-manager-for-wpforms'),
                'fetchFormsError'         => __('Failed to fetch forms:', 'advanced-entries-manager-for-wpforms'),
                'fetchEntriesError'       => __('Failed to fetch entries:', 'advanced-entries-manager-for-wpforms'),
                'fetchFieldsError'        => __('Failed to fetch form fields. Please try again.', 'advanced-entries-manager-for-wpforms'),
                'unexpectedError'         => __('❌ Unexpected error occurred.', 'advanced-entries-manager-for-wpforms'),
                'syncDone'                => __('Entry synchronization Done!', 'advanced-entries-manager-for-wpforms'),
                'syncFailed'              => __('❌ Synchronization failed.', 'advanced-entries-manager-for-wpforms'),
                'saveFailed'              => __('❌ Save failed.', 'advanced-entries-manager-for-wpforms'),
            )
        );
    }
}
