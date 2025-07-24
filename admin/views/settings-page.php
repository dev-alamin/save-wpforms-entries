<?php 
$access_token = get_option('swpfe_google_access_token'); 
$token_expires = get_option('swpfe_google_token_expires');
$now = time();
$expires_in = $token_expires ? max(0, $token_expires - $now) : 0;

function seconds_to_human_readable($seconds) {
    if ($seconds <= 0) return 'Expired';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return ($h ? $h . 'h ' : '') . ($m ? $m . 'm' : 'less than a minute');
}
?>
<div class="wrap swpfe-admin-page min-h-screen bg-gray-50 px-8 py-10 text-[15px] font-inter text-gray-800 !max-w-3xl !mx-auto">

    <h1 class="!text-4xl !font-extrabold !text-indigo-700 !tracking-tight !flex !items-center !gap-3 !mb-8">
        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" 
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 10h-3.17a4 4 0 0 0-7.66 0H7a5 5 0 0 0 0 10h14a5 5 0 0 0 0-10z"></path>
        </svg>
        WPForms to Google Sheets
    </h1>

    <div class="bg-white shadow-md rounded-xl border border-indigo-100 p-8 text-center">
        <h3 class="!text-2xl font-semibold !mb-4">Google Account Connection</h3>

        <?php if ($access_token): ?>
            <div class="flex justify-center !mb-6">
                <div class="flex items-center gap-4 p-5 rounded-lg bg-green-50 border border-green-200 text-green-800 shadow max-w-md mx-auto">
                    <div class="relative w-5 h-5">
                        <!-- Pulsing Dot -->
                        <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex rounded-full h-5 w-5 bg-green-600"></span>
                    </div>
                    <div class="text-left">
                        <p class="font-semibold text-lg">Connected to Google Sheets</p>
                        <p class="text-sm text-green-700">Live data sync is active. Streaming enabled ✅</p>
                    </div>
                </div>
            </div>

            <p class="!mb-6 text-gray-600">
                Your WPForms submissions are now syncing automatically with your Google Sheets in real-time.
                This connection allows you to streamline your data collection and analysis.
            </p>

            <div class="max-w-xs mx-auto flex justify-between items-center bg-green-100 border border-green-300 rounded-lg px-5 py-3 text-green-800 shadow-sm !text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" 
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 8v4l3 3"></path>
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <span class="font-medium">Token expires in: <strong><?= seconds_to_human_readable($expires_in) ?></strong></span>
                </div>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=swpfe_reconnect')); ?>" 
                   class="text-green-700 hover:text-green-900 underline font-semibold" title="Reconnect Google Account">
                    Reconnect
                </a>
            </div>

        <?php else: ?>
            <p class="!mb-6 text-gray-600">
                To start syncing WPForms entries with Google Sheets, please connect your Google account.
                This will enable live synchronization and easy data management.
            </p>

            <a href="https://api.almn.me/oauth/init?site=<?php echo esc_url(site_url()); ?>"
               class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 !text-white font-medium shadow transition max-w-xs mx-auto">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" 
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 12H8m0 0l4-4m-4 4l4 4"></path>
                </svg>
                🔐 Connect with Google
            </a>
        <?php endif; ?>
    </div>
</div>
