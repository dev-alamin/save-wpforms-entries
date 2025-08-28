<section class="">
	<div class="bg-white shadow-md border border-indigo-100 p-8 text-center">
		<h3 class="!text-2xl font-semibold !mb-4">
			<?php
			use App\AdvancedEntryManager\Utility\Helper;
			esc_html_e( 'Google Account Connection', 'forms-entries-manager' );
			?>
		</h3>

		<?php if ( $has_access_token ) : ?>
			<div class="flex justify-center !mb-6">
				<div class="flex items-center gap-4 p-5 rounded-lg bg-green-50 border border-green-200 text-green-800 shadow max-w-md mx-auto">
					<div class="relative w-5 h-5">
						<span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
						<span class="relative inline-flex rounded-full h-5 w-5 bg-green-600"></span>
					</div>
					<div class="text-left">
						<p class="font-semibold text-lg">
							<?php esc_html_e( 'Connected to Google Sheets', 'forms-entries-manager' ); ?>
							<br />
							<a href="<?php echo esc_url( FEM_GOOGLE_PROXY_URL . '?site=' . rawurlencode( Helper::get_settings_page_url() ) ); ?>"
								class="text-green-700 hover:text-green-900 underline font-semibold"
								title="<?php esc_attr_e( 'Reconnect or switch Google Account', 'forms-entries-manager' ); ?>">
								<?php esc_html_e( 'Switch Account', 'forms-entries-manager' ); ?>
							</a>
						</p>
						<p class="text-sm text-green-700"><?php esc_html_e( 'Live data sync is active. Streaming enabled ✅', 'forms-entries-manager' ); ?></p>

					</div>
				</div>
			</div>
			
			<p class="!mb-6 text-gray-600 max-w-2xl mx-auto text-center !m-auto">
				<?php esc_html_e( 'Your WPForms submissions are now syncing automatically with your Google Sheets in real-time. This connection allows you to streamline your data collection and analysis.', 'forms-entries-manager' ); ?>
			</p>
			
			<div class="max-w-md mx-auto bg-green-50 border border-green-200 rounded-xl px-6 py-4 text-green-800 shadow-md transition-transform transform hover:scale-105">
				<div class="flex items-center space-x-3">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M482-160q-134 0-228-93t-94-227v-7l-64 64-56-56 160-160 160 160-56 56-64-64v7q0 100 70.5 170T482-240q26 0 51-6t49-18l60 60q-38 22-78 33t-82 11Zm278-161L600-481l56-56 64 64v-7q0-100-70.5-170T478-720q-26 0-51 6t-49 18l-60-60q38-22 78-33t82-11q134 0 228 93t94 227v7l64-64 56 56-160 160Z"/></svg>
					<span class="text-base font-semibold text-green-900">
						<?php esc_html_e( 'Connected Google Sheets', 'forms-entries-manager' ); ?>
					</span>
				</div>

				<div class="mt-4 space-y-2">
					<?php
					$forms = Helper::get_all_forms();

					if ( ! empty( $forms ) ) {
						foreach ( $forms as $form_id ) {
							$form_title     = get_the_title( $form_id );
							$spreadsheet_id = Helper::get_option( 'gsheet_spreadsheet_id_' . $form_id );

							if ( $form_title && $spreadsheet_id ) {
								$sheet_link = 'https://docs.google.com/spreadsheets/d/' . esc_attr( $spreadsheet_id );
								echo '<div class="flex items-center space-x-2">';
								echo '<span class="text-sm font-medium text-green-700">' . esc_html( $form_title ) . ':</span>';
								echo '<a href="' . esc_url( $sheet_link ) . '" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-1 text-sm font-medium text-green-600 hover:text-green-800 underline transition-colors">';
								echo '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="m560-240-56-58 142-142H160v-80h486L504-662l56-58 240 240-240 240Z"/></svg>';
								echo '<span>' . esc_html_e( 'View Sheet', 'forms-entries-manager' ) . '</span>';
								echo '</a>';
								echo '</div>';
							}
						}
					} else {
						echo '<p class="text-sm text-green-600">' . esc_html__( 'No forms are currently connected to Google Sheets.', 'forms-entries-manager' ) . '</p>';
					}
					?>
				</div>
			</div>

		<a
		title="<?php esc_attr_e( 'Revoke Google Connection. It won\'t remove your sheets just no sync anymore.', 'forms-entries-manager' ); ?>"
		href="
			<?php
			echo esc_url(
				admin_url(
					add_query_arg(
						array(
							'action'   => 'revoke_google_connection',
							'_wpnonce' => wp_create_nonce( 'revoke_connection_nonce' ),
						),
						'admin.php?page=form-entries-settings'
					)
				)
			);
			?>
		" 
		class="inline-flex mt-4 items-center justify-center gap-2 px-7 py-3 rounded-lg bg-red-600 hover:bg-red-700 !text-white font-medium shadow transition max-w-xs mx-auto">
			<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#fff"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
			<?php esc_html_e( 'Revoke Connection', 'forms-entries-manager' ); ?>
		</a>
		
		<!-- No access token then show connection way -->
		<?php else : ?>
			<p class="!mb-6 text-gray-600">
				<?php esc_html_e( 'To start syncing WPForms entries with Google Sheets, please connect your Google account. This will enable live synchronization and easy data management.', 'forms-entries-manager' ); ?>
			</p>

			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'site' => rawurlencode( Helper::get_settings_page_url() ),
					),
					FEM_GOOGLE_PROXY_URL
				)
			);
			?>
			"
				class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 !text-white font-medium shadow transition max-w-xs mx-auto">
				<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M16 12H8m0 0l4-4m-4 4l4 4"></path>
				</svg>
				🔐 <?php esc_html_e( 'Connect with Google', 'forms-entries-manager' ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>