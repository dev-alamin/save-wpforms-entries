<?php
namespace SWPFE;

class Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action('admin_head', [ $this, 'hide_update_notices' ]);
	}

    public function register_settings() {
        register_setting('swpfe_google_settings', 'swpfe_google_client_id');
        register_setting('swpfe_google_settings', 'swpfe_google_client_secret');
    }

	public function add_menu() {
		add_menu_page(
			__( 'WPForms Entries', 'save-wpf-entries' ),
			__( 'WPForms Entries', 'save-wpf-entries' ),
			'manage_options',
			'swpfe-entries',
			[ $this, 'render_page' ],
			'dashicons-feedback',
			25
		);
        add_submenu_page(
            'swpfe-entries',
            'WPForms Entry Sync Settings',
            'Settings',
            'manage_options',
            'swpfe-settings',
            [ $this, 'render_settings_page' ],
            65
        );
	}

    function render_settings_page() {
        include SWPFE_PATH . 'admin/views/settings-page.php';
    }

	public function render_page() {
		include SWPFE_PATH . 'admin/views/view-entries.php';
	}

	public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_swpfe-entries' && $hook !== 'wpforms-entries_page_swpfe-settings' ) {
            return;
        }

		wp_enqueue_style( 'swpfe-admin-css', SWPFE_URL . 'admin/assets/admin.css', [], time() );
        wp_enqueue_script( 'swpfe-tailwind-css', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', [], time(), false );
		wp_enqueue_script( 'swpfe-alpine', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', [], null, true );
        wp_enqueue_script( 'swpfe-admin-js', SWPFE_URL . 'admin/assets/admin.js', [], time() );
        wp_enqueue_script( 'swpfe-landash', 'https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js', [], time(), false );

        wp_enqueue_script( 'lottie-web', SWPFE_URL . 'admin/assets/lottie-player.js', [], '5.12.0', false );

        wp_localize_script('swpfe-admin-js', 'swpfeSettings', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
	}

    public function hide_update_notices() {
        $screen = get_current_screen();

        if (strpos($screen->id, 'swpfe') !== false) {
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
