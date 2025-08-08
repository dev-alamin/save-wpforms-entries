<section class="">
    <div class="bg-white shadow-md border border-indigo-100 p-8 text-center">
        <h3 class="!text-2xl font-semibold !mb-4">
            <?php
            use App\AdvancedEntryManager\Utility\Helper;
            esc_html_e('Google Account Connection', 'advanced-entries-manager-for-wpforms');

            if (isset($_GET['aem_start_sync'])) {
                $send_data = new \App\AdvancedEntryManager\GoogleSheet\Send_Data();
                // $count = $send_data->enqueue_unsynced_entries(2);
                // $send_data->process_single_entry([ 'entry_id' => 18 ] );
                $ids = [
                    4255232, 4254976
                ];

                foreach( $ids as $id ) {
                    $send_data->process_single_entry( ['entry_id' => $id ] );
                }

                // $send_data->unsync_entry( 4254567 );
            }
            ?>
        </h3>
        <?php if ($access_token): ?>
            <div class="flex justify-center !mb-6">
                <div class="flex items-center gap-4 p-5 rounded-lg bg-green-50 border border-green-200 text-green-800 shadow max-w-md mx-auto">
                    <div class="relative w-5 h-5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex rounded-full h-5 w-5 bg-green-600"></span>
                    </div>
                    <div class="text-left">
                        <p class="font-semibold text-lg">
                            <?php esc_html_e('Connected to Google Sheets', 'advanced-entries-manager-for-wpforms'); ?>
                            <br />
                            <a href="<?php echo esc_url( AEMFW_GOOGLE_PROXY_URL . '?site=' . rawurlencode(Helper::get_settings_page_url())); ?>"
                                class="text-green-700 hover:text-green-900 underline font-semibold"
                                title="<?php esc_attr_e('Reconnect or switch Google Account', 'advanced-entries-manager-for-wpforms'); ?>">
                                <?php esc_html_e('Switch Account', 'advanced-entries-manager-for-wpforms'); ?>
                            </a>
                        </p>
                        <p class="text-sm text-green-700"><?php esc_html_e('Live data sync is active. Streaming enabled ✅', 'advanced-entries-manager-for-wpforms'); ?></p>
                    </div>
                </div>
            </div>

            <p class="!mb-6 text-gray-600 max-w-2xl mx-auto text-center !m-auto">
                <?php esc_html_e('Your WPForms submissions are now syncing automatically with your Google Sheets in real-time. This connection allows you to streamline your data collection and analysis.', 'advanced-entries-manager-for-wpforms'); ?>
            </p>

            <div class="max-w-xs mx-auto flex justify-between items-center bg-green-100 border border-green-300 rounded-lg px-5 py-3 text-green-800 shadow-sm !text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 8v4l3 3"></path>
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <span class="font-medium">
                        <?php esc_html_e('Token expires in:', 'advanced-entries-manager-for-wpforms'); ?>
                        <strong><?php echo esc_html( $expires_label ); ?></strong>
                    </span>
                </div>
                <a href="<?php echo esc_url( AEMFW_GOOGLE_PROXY_URL . '?site=' . rawurlencode(Helper::get_settings_page_url())); ?>"
                    class="text-green-700 hover:text-green-900 underline font-semibold"
                    title="<?php esc_attr_e('Reconnect or switch Google Account', 'advanced-entries-manager-for-wpforms'); ?>">
                    <?php esc_html_e('Reconnect', 'advanced-entries-manager-for-wpforms'); ?>
                </a>
            </div>

        <?php else: ?>
            <p class="!mb-6 text-gray-600">
                <?php esc_html_e('To start syncing WPForms entries with Google Sheets, please connect your Google account. This will enable live synchronization and easy data management.', 'advanced-entries-manager-for-wpforms'); ?>
            </p>

            <a href="<?php echo esc_url( AEMFW_GOOGLE_PROXY_URL . '?site=' . rawurlencode(Helper::get_settings_page_url())); ?>"
                class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 !text-white font-medium shadow transition max-w-xs mx-auto">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 12H8m0 0l4-4m-4 4l4 4"></path>
                </svg>
                🔐 <?php esc_html_e('Connect with Google', 'advanced-entries-manager-for-wpforms'); ?>
            </a>
        <?php endif; ?>
    </div>
</section>