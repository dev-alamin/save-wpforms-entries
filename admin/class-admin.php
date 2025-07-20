<?php
namespace SWPFE;

class Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
	}

	public function render_page() {
		include SWPFE_PATH . 'admin/views/view-entries.php';
	}

	public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_swpfe-entries' ) {
            return;
        }

		wp_enqueue_style( 'swpfe-admin-css', SWPFE_URL . 'admin/assets/admin.css', [], time() );
        wp_enqueue_script( 'swpfe-tailwind-css', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', [], time(), false );
		wp_enqueue_script( 'swpfe-alpine', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', [], null, true );
	}
}
