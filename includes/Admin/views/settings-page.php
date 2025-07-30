<?php
$access_token  = get_option('swpfe_google_access_token');
$token_expires = get_option('swpfe_google_token_expires');
$now           = time();
$expires_in    = $token_expires ? max(0, $token_expires - $now) : 0;

function seconds_to_human_readable($seconds)
{
    if ($seconds <= 0) return esc_html__('Expired', 'advanced-entries-manager-for-wpforms');
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return ($h ? $h . 'h ' : '') . ($m ? $m . 'm' : esc_html__('less than a minute', 'advanced-entries-manager-for-wpforms'));
}

$per_page  = get_option('swpfe_entries_per_page', 20);
$sheet_id  = get_option('swpfe_google_sheet_id');
$auto_sync = get_option('swpfe_google_sheet_auto_sync', true);
$sheet_tab = get_option('swpfe_google_sheet_tab', 'Sheet1');
?>

<div x-data="toastHandler()" x-init="init()" x-show="visible"
    x-transition
    :class="{
        '!bg-green-100 text-green-800 border-green-200': type === 'success',
        '!bg-red-100 text-red-800 border-red-200': type === 'error'
     }"
    class="!fixed !bottom-6 !right-6 !px-6 !py-3 !rounded-lg !border !shadow-lg !text-sm !font-medium z-50">
    <span x-text="message"></span>
</div>


<div class="wrap swpfe-admin-page min-h-screen max-w-7xl !m-auto bg-gray-50 px-8 py-10 text-[15px] font-inter text-gray-800 space-y-10 !m-auto"
    x-data="settingsForm()">
    <div class="mb-8 bg-slate-700 text-white px-4 py-2 rounded-lg">
        <h1 class="!text-4xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
            📋 <span><?php esc_html_e('WPForms Entries Pro Settings', 'advanced-entries-manager-for-wpforms'); ?></span>
        </h1>
        <p class="text-gray-200 !text-[15px] leading-relaxed">
            <?php
            esc_html_e('Browse and manage form entries submitted by users. Click on a form to view its submissions, 
                mark entries as read/unread, or delete them as needed.', 'advanced-entries-manager-for-wpforms');
            ?>
        </p>
    </div>

    <div x-data="{ tab: 'google' }" class="swpfe-settings-tabs mb-10">
        <!-- Tab Control Navigation -->
        <nav class="flex flex-wrap gap-3 border-b border-indigo-200 text-sm font-medium">
            <button
                @click="tab = 'google'"
                :class="tab === 'google' 
                    ? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
                    : '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
                class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50">
                🔐 Google Sync
            </button>
            <button
                @click="tab = 'csv'"
                :class="tab === 'csv' 
                    ? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
                    : '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
                class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50">
                📤 CSV Export
            </button>
            <button
                @click="tab = 'general'"
                :class="tab === 'general' 
                    ? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
                    : '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
                class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50">
                📄 General Settings
            </button>
        </nav>

        <form id="swpfe-settings-form" @submit.prevent="saveSettings" class="space-y-6">
            <div x-show="tab === 'google'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2">
                <!-- ✅ Connected Notice -->
                <?php include __DIR__ . '/tab/google-connection.php'; ?>
            </div>

            <div x-show="tab === 'csv'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2">
                <?php include __DIR__ . '/tab/csv-export.php'; ?>
            </div>

            <div x-show="tab === 'general'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2">
                <?php include __DIR__ . '/tab/general-settings.php'; ?>
            </div>

            <button type="submit"
                class="!inline-flex mt-5 !items-center !gap-2 !px-6 !py-3 !bg-indigo-600 hover:!bg-indigo-700 !text-white !text-sm !font-semibold !rounded-lg !shadow-sm hover:!shadow-md !transition-all !duration-200"
                :disabled="isSaving">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <?php echo esc_html__('Save Changes', 'advanced-entries-manager-for-wpforms'); ?>
            </button>

            <p x-text="message" class="text-sm mt-2 text-green-600 font-medium"></p>
        </form>

    </div>
</div>