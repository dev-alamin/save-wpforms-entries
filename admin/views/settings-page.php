<?php 
$access_token = get_option('swpfe_google_access_token'); 
$token_expires = get_option('swpfe_google_token_expires');
$now = time();
$expires_in = $token_expires ? max(0, $token_expires - $now) : 0;

function seconds_to_human_readable($seconds) {
    if ($seconds <= 0) return esc_html__('Expired', 'advanced-entries-manager-for-wpforms');
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return ($h ? $h . 'h ' : '') . ($m ? $m . 'm' : esc_html__('less than a minute', 'advanced-entries-manager-for-wpforms'));
}

$entries_per_page = get_option('swpfe_entries_per_page', 20);
$sheet_id = get_option('swpfe_google_sheet_id');
$auto_sync = get_option('swpfe_google_sheet_auto_sync', true);
$sheet_tab = get_option( 'swpfe_google_sheet_tab', 'Sheet1' );
?>
<div class="wrap swpfe-admin-page min-h-screen bg-gray-50 px-8 py-10 text-[15px] font-inter text-gray-800 max-w-5xl mx-auto space-y-10 !m-auto">
    <h1 class="!text-4xl text-center !font-bold !text-indigo-700 !mb-6"><?php esc_html_e( 'WPForms Entries Pro Settings', 'advanced-entries-manager-for-wpforms' ); ?></h1>
    <!-- ✅ Connected Notice -->
    <section class="">
        <div class="bg-white shadow-md rounded-xl border border-indigo-100 p-8 text-center">
        <h3 class="!text-2xl font-semibold !mb-4"><?php esc_html_e('Google Account Connection', 'advanced-entries-manager-for-wpforms'); ?></h3>

        <?php if ($access_token): ?>
            <div class="flex justify-center !mb-6">
                <div class="flex items-center gap-4 p-5 rounded-lg bg-green-50 border border-green-200 text-green-800 shadow max-w-md mx-auto">
                    <div class="relative w-5 h-5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex rounded-full h-5 w-5 bg-green-600"></span>
                    </div>
                    <div class="text-left">
                        <p class="font-semibold text-lg"><?php esc_html_e('Connected to Google Sheets', 'advanced-entries-manager-for-wpforms'); ?></p>
                        <p class="text-sm text-green-700"><?php esc_html_e('Live data sync is active. Streaming enabled ✅', 'advanced-entries-manager-for-wpforms'); ?></p>
                    </div>
                </div>
            </div>

            <p class="!mb-6 text-gray-600">
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
                        <strong><?php echo esc_html( seconds_to_human_readable( absint( $expires_in ?? 0 ) ) ); ?></strong>
                    </span>
                </div>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=swpfe_reconnect' ) ); ?>"
                   class="text-green-700 hover:text-green-900 underline font-semibold"
                   title="<?php esc_attr_e('Reconnect Google Account', 'advanced-entries-manager-for-wpforms'); ?>">
                    <?php esc_html_e('Reconnect', 'advanced-entries-manager-for-wpforms'); ?>
                </a>
            </div>
        <?php else: ?>
            <p class="!mb-6 text-gray-600">
                <?php esc_html_e('To start syncing WPForms entries with Google Sheets, please connect your Google account. This will enable live synchronization and easy data management.', 'advanced-entries-manager-for-wpforms'); ?>
            </p>

            <a href="<?php echo esc_url( 'https://api.almn.me/oauth/init?site=' . rawurlencode( site_url() ) ); ?>"
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

    <!-- ⚙️ Settings Grid -->
    <section class="grid md:grid-cols-2 gap-8">

        <!-- ℹ️ Left Info -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-xl font-bold text-indigo-700 mb-3"><?php echo esc_html__('📋 About These Settings', 'advanced-entries-manager-for-wpforms'); ?></h3>
                <p class="text-gray-600 text-sm leading-relaxed">
                    <?php echo esc_html__('Configure how your WPForms entries sync with Google Sheets. You can adjust page size, default sheet ID, and more. These settings apply globally across all forms.', 'advanced-entries-manager-for-wpforms'); ?>
                </p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-xl font-bold text-indigo-700 mb-3"><?php echo esc_html__('🔗 Sync Details', 'advanced-entries-manager-for-wpforms'); ?></h3>
                <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1">
                    <li><?php echo esc_html__('Automatic sync enabled after form submission.', 'advanced-entries-manager-for-wpforms'); ?></li>
                    <li><?php echo esc_html__('Manual re-sync option from entry table.', 'advanced-entries-manager-for-wpforms'); ?></li>
                    <li><?php echo esc_html__('Each form can optionally override default sheet.', 'advanced-entries-manager-for-wpforms'); ?></li>
                </ul>
            </div>
        </div>

        <!-- 🔧 Right Settings -->
        <form method="post" action="options.php" class="bg-white p-6 rounded-xl shadow space-y-6">
            <?php settings_fields('swpfe_google_settings'); ?>

            <h3 class="text-xl font-bold text-indigo-700 mb-4"><?php echo esc_html__('🔧 Settings', 'advanced-entries-manager-for-wpforms'); ?></h3>

            <!-- Pagination -->
            <div>
                <label for="swpfe_entries_per_page" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php esc_html_e('Entries per Page', 'advanced-entries-manager-for-wpforms'); ?>
                </label>
                <input
                    type="number"
                    name="swpfe_entries_per_page"
                    id="swpfe_entries_per_page"
                    value="<?php echo esc_attr( $entries_per_page ); ?>"
                    min="10" max="200"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('Controls pagination in entry tables (default: 20).', 'advanced-entries-manager-for-wpforms'); ?></p>
            </div>

            <!-- Sheet ID -->
            <div>
                <label for="swpfe_google_sheet_id" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php esc_html_e('Default Google Sheet ID', 'advanced-entries-manager-for-wpforms'); ?>
                </label>
                <input
                    type="text"
                    name="swpfe_google_sheet_id"
                    id="swpfe_google_sheet_id"
                    value="<?php echo esc_attr( $sheet_id ); ?>"
                    placeholder="<?php echo esc_attr__('e.g. 1G5XyaXc92nfhPyzLoTx1h6P', 'advanced-entries-manager-for-wpforms'); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('Leave empty if using custom sheet per form.', 'advanced-entries-manager-for-wpforms'); ?></p>
            </div>

            <!-- Sheet Tab -->
            <div>
                <label for="swpfe_sheet_tab" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php esc_html_e('Default Sheet Tab Name', 'advanced-entries-manager-for-wpforms'); ?>
                </label>
                <input
                    type="text"
                    name="swpfe_sheet_tab"
                    id="swpfe_sheet_tab"
                    value="<?php echo esc_attr( $sheet_tab ); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('Usually "Sheet1", can be overridden.', 'advanced-entries-manager-for-wpforms'); ?></p>
            </div>

            <!-- Auto Sync -->
             <div>
                <label for="swpfe_google_sheet_auto_sync" class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                    <input
                        type="checkbox"
                        name="swpfe_google_sheet_auto_sync"
                        id="swpfe_google_sheet_auto_sync"
                        value="1"
                        <?php checked( $auto_sync, true ); ?>
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <?php esc_html_e( 'Auto Sync to Google Sheet', 'advanced-entries-manager-for-wpforms' ); ?>
                </label>
                <p class="text-xs text-gray-500 mt-1">
                    <?php esc_html_e( 'Enable this to automatically send WPForms entries to the connected sheet after submission.', 'advanced-entries-manager-for-wpforms' ); ?>
                </p>
            </div>

            <!-- Submit -->
            <button type="submit"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-md transition">
                <?php echo esc_html__('💾 Save Changes', 'advanced-entries-manager-for-wpforms'); ?>
            </button>
        </form>
    </section>
</div>
