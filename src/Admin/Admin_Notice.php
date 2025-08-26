<?php

namespace App\AdvancedEntryManager\Admin;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all admin-related notices, links, and UI elements.
 */
class Admin_Notice {


	/**
	 * Constructor.
	 */
	public function __construct() {
		// Display a link to the documentation on the Entries UI header.
		add_action( 'fem_before_entries_ui_header', array( $this, 'display_doc_link' ) );

		// Display a specific REST API notice on the Entries UI header.
		add_action( 'fem_before_entries_ui_header', array( $this, 'fem_rest_notice' ) );

		// General admin notices for REST API and Cron issues.
		add_action( 'admin_notices', array( $this, 'rest_disabled_notice' ) );
		add_action( 'admin_notices', array( $this, 'cron_issue_notice' ) );

		// Add custom action links to the plugin row in the Plugins screen.
		add_filter( 'plugin_action_links_' . FEM_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add custom action links to the plugin row in the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function plugin_action_links( $links ) {
		// Array of custom links to prepend to the existing links.
		$custom_links = array(
			// Settings link.
			'settings'    => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=fem-settings' ) ),
				esc_html__( 'Settings', 'forms-entries-manager' )
			),
			// Documentation link.
			'docs'        => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				'https://entriesmanager.com/doc',
				esc_html__( 'Docs', 'forms-entries-manager' )
			),
			// Upgrade PRO link.
			'upgrade_pro' => sprintf(
				'<a href="%s" target="_blank" style="color:#d54e21;font-weight:bold;">%s</a>',
				'https://entriesmanager.com/fem/pro',
				esc_html__( 'Upgrade PRO', 'forms-entries-manager' )
			),
		);

		// Merge the custom links with the existing links, placing custom links first.
		return array_merge( $custom_links, $links );
	}

	/**
	 * Display REST API disabled notice.
	 *
	 * This function checks if the REST API is disabled and displays a notice
	 * in the admin area if it is. It only shows the notice to users with
	 * 'manage_options' capability.
	 */
	public function rest_disabled_notice() {
		if ( ! Helper::is_rest_enabled() ) {
			echo '<div class="notice notice-error fem-notice"><p>';
			esc_html_e( '🚫 Your site is blocking REST API access required by Advanced Entries Manager. Please whitelist /wp-json/fem/entries/v1/* to ensure full functionality.', 'forms-entries-manager' );
			echo '</p></div>';
		}
	}


	/**
	 * Display REST Error Notice in own page.
	 * This notice is shown on the Entries Manager page itself.
	 */
	public function fem_rest_notice() {
		if ( Helper::is_rest_enabled() ) {
			return; // REST API working, no notice needed
		}
		?>
		<div
			x-data="{ show: true }"
			x-show="show"
			x-transition
			class="mb-4 rounded-lg border border-red-400 bg-red-50 text-red-800 p-4 relative shadow-sm"
			role="alert">
			<div class="flex items-start gap-3">
				<!-- Material warning icon -->
				<svg class="w-5 h-5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 24 24">
					<path
						d="M1 21h22L12 2 1 21zm12-3h-2v2h2v-2zm0-8h-2v6h2v-6z" />
				</svg>

				<div class="flex-1 text-sm leading-5">
					<?php
					echo wp_kses_post(
						__(
							'Your site is blocking the REST API endpoints required by Advanced Entries Manager. Please whitelist <code>/wp-json/wpforms/entries/v1/*</code> in your firewall or security plugin (e.g., Wordfence, Sucuri) to ensure full functionality.',
							'forms-entries-manager'
						)
					);
					?>
				</div>

				<button
					@click="show = false"
					class="ml-auto text-red-500 hover:text-red-700 transition"
					aria-label="<?php esc_attr_e( 'Dismiss REST API alert', 'forms-entries-manager' ); ?>">
					<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
						<path
							d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7a1 1 0 0 0-1.41 
							1.41L10.59 12l-4.9 4.89a1 1 0 1 0 1.41 1.41L12 13.41l4.89 
							4.9a1 1 0 0 0 1.41-1.41L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z" />
					</svg>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Display a notice if wp-cron is not running reliably.
	 *
	 * This notice advises the user to set up a real cron job for reliable
	 * background processing.
	 */
	public function cron_issue_notice() {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			return; // Only show if wp-cron has been disabled.
		}

		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div
			x-data="{ show: true }"
			x-show="show"
			x-transition
			class="notice notice-warning is-dismissible fem-notice"
			role="alert">
			<p class="font-medium text-lg leading-6 mb-2">
				<strong><?php esc_html_e( 'Action Required: Improve Cron Reliability', 'forms-entries-manager' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: a URL to the WordPress documentation */
						__(
							'Your site has the <code>DISABLE_WP_CRON</code> constant enabled, which can prevent scheduled tasks (like Google Sheets syncing) from running reliably. For consistent background processing, we recommend setting up a server-side cron job. %s',
							'forms-entries-manager'
						),
						'<a href="' . esc_url( 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about this here.', 'forms-entries-manager' ) . '</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	public function display_doc_link() {
		?>
		<div
			x-data="{ show: true, expanded: false }"
			x-show="show"
			x-cloak
			x-transition
			class="mb-5 rounded-xl border border-blue-300 bg-blue-50 text-blue-800 p-4 relative shadow-sm space-y-3">
			<!-- Header Section -->
			<div class="flex items-start gap-3 mb-0">
				<!-- Material Info Icon -->
				<svg class="w-5 h-5 shrink-0 text-blue-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
					<path
						d="M11 17h2v-6h-2v6zm0-8h2V7h-2v2zm1-7C6.48 2 2 6.48 2 12s4.48 10 10 10 
				10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 
				8-8 8 3.59 8 8-3.59 8-8 8z" />
				</svg>

				<!-- Notice Text -->
				<div class="flex-1 text-sm leading-5">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: documentation URL */
							__( 'Need help managing entries or exporting them to Google Sheets? <a href="%s" class="text-blue-600 hover:underline font-medium" target="_blank" rel="noopener noreferrer">Visit the documentation</a>.', 'forms-entries-manager' ),
							esc_url( 'https://entriesmanager.com/doc' )
						)
					);
					?>
				</div>

				<!-- Expand Button -->
				<button
					@click="expanded = !expanded"
					class="text-blue-500 hover:text-blue-700 transition transform"
					:aria-label="expanded ? '<?php echo esc_attr( __( 'Collapse details', 'forms-entries-manager' ) ); ?>' : '<?php echo esc_attr( __( 'Expand details', 'forms-entries-manager' ) ); ?>'">
					<svg :class="{ 'rotate-180': expanded }" class="w-5 h-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
					</svg>
				</button>

				<!-- Dismiss Button -->
				<button
					@click="show = false"
					class="ml-2 text-blue-400 hover:text-blue-700 transition"
					aria-label="<?php echo esc_attr__( 'Dismiss', 'forms-entries-manager' ); ?>">
					<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
						<path
							d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7a1 1 0 0 0-1.41 
					1.41L10.59 12l-4.9 4.89a1 1 0 1 0 1.41 1.41L12 13.41l4.89 
					4.9a1 1 0 0 0 1.41-1.41L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z" />
					</svg>
				</button>
			</div>

			<!-- Expanded Content -->
			<div x-show="expanded" x-collapse x-transition class="text-sm leading-6 text-blue-900 pt-2 border-blue-200">
				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">
					<a href="https://entriesmanager.com/doc#getting-started" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-blue-100 rounded-lg p-3 hover:bg-blue-100 transition shadow-sm">
						<svg class="w-5 h-5 text-green-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor">
							<path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z" />
						</svg>
						<span><?php echo esc_html__( 'Getting Started Guide', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Entry Viewer Modal -->
					<a href="https://entriesmanager.com/doc#entry-modal" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-indigo-100 rounded-lg p-3 hover:bg-indigo-100 transition shadow-sm">
						<svg class="w-5 h-5 text-indigo-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M3 4v16h18V4H3zm16 14H5V6h14v12z" />
						</svg>
						<span><?php echo esc_html__( 'View Full Entry in Modal', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Bulk Actions -->
					<a href="https://entriesmanager.com/doc#bulk-actions" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-blue-100 rounded-lg p-3 hover:bg-blue-100 transition shadow-sm">
						<svg class="w-5 h-5 text-purple-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 14.5h-2V11h2v5.5zm0-7h-2V9h2v.5z" />
						</svg>
						<span><?php echo esc_html__( 'Bulk Actions & Filters', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Export & Field Exclusion -->
					<a href="https://entriesmanager.com/doc#csv-export" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-green-100 rounded-lg p-3 hover:bg-green-100 transition shadow-sm">
						<svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M5 20h14v-2H5v2zm7-18L5.33 9h4.67v6h4V9h4.67L12 2z" />
						</svg>
						<span><?php echo esc_html__( 'CSV Export & Field Exclusion', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Commenting Feature -->
					<a href="https://entriesmanager.com/doc#entry-comments" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-pink-100 rounded-lg p-3 hover:bg-pink-100 transition shadow-sm">
						<svg class="w-5 h-5 text-pink-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z" />
						</svg>
						<span><?php echo esc_html__( 'Comment on Entries', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Print Friendly -->
					<a href="https://entriesmanager.com/doc#print-entry" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-indigo-100 rounded-lg p-3 hover:bg-indigo-100 transition shadow-sm">
						<svg class="w-5 h-5 text-indigo-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M19 8H5V5h14m0 14H5v-4h14m0-10a2 2 0 0 1 2 2v6h-2v4H5v-4H3V8a2 2 0 0 1 2-2h14z" />
						</svg>
						<span><?php echo esc_html__( 'Print Friendly Entries', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Google Sheets Sync -->
					<a href="https://entriesmanager.com/doc#google-sheets-sync" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-yellow-100 rounded-lg p-3 hover:bg-yellow-100 transition shadow-sm">
						<svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M3 3v18h18V3H3zm6 14H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z" />
						</svg>
						<span><?php echo esc_html__( 'Sync to Google Sheets', 'forms-entries-manager' ); ?></span>
					</a>

					<!-- Migration Help -->
					<a href="https://entriesmanager.com/doc#migration" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-red-100 rounded-lg p-3 hover:bg-red-100 transition shadow-sm">
						<svg class="w-5 h-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M12 2L3 21h18L12 2zm0 3.84L17.53 19H6.47L12 5.84zM11 10h2v4h-2v-4zm0 6h2v2h-2v-2z" />
						</svg>
						<span><?php echo esc_html__( 'Entry Migration Guide', 'forms-entries-manager' ); ?></span>
					</a>

					<a href="https://entriesmanager.com/doc#wp-cron" target="blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-red-100 rounded-lg p-3 hover:bg-red-100 transition shadow-sm">
						<svg class="w-5 h-5 text-yellow-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor">
						<path d="M480-80q-134 0-227-93t-93-227q0-134 93-227t227-93q134 0 227 93t93 227q0 134-93 227t-227 93Zm0-80q101 0 190.5-47T770-290q-51-68-51-125.5v-274.5l-44 44q-26 26-62.5 40.5t-75.5 14.5v-80q48 0 91.5-16.5T703-677l57-57q31-31 31-72t-31-72l-57-57q-31-31-72-31t-72 31l-57 57q-29 29-45 74t-16 93v169q0 39-16 75.5t-46 62.5q-54 54-124 54H480Zm0 80q174 0 297-123t123-297q0-174-123-297t-297-123q-174 0-297 123t-123 297q0 174 123 297t297 123Z" />
						</svg>
						<span><?php echo esc_html_e( 'Fix Cron Issues Guide', 'forms-entries-manager' ); ?></span>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
