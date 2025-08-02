<?php

namespace App\AdvancedEntryManager\Admin;

class Admin_Notice
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'display_notice']);

        add_action('swpfe_before_entries_ui_header', [$this, 'display_aem_notice']);
        add_action('swpfe_before_entries_ui_header', [$this, 'aem_rest_notice']);
    }

    /**
     * Display admin notice.
     */
    public function display_notice()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info is-dismissible aem-notice">';
        echo '<p>' . esc_html__('Advanced Entries Manager for WPForms is active. Please configure the settings.', 'advanced-entries-manager-for-wpforms') . '</p>';
        echo '</div>';
    }

    /**
     * Display REST Error Notice
     */
    public function aem_rest_notice()
    {
        $rest_enabled = apply_filters('rest_authentication_errors', null);

        if (! is_wp_error($rest_enabled)) {
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
                            'save-wpf-entries'
                        )
                    );
                    ?>
                </div>

                <button
                    @click="show = false"
                    class="ml-auto text-red-500 hover:text-red-700 transition"
                    aria-label="<?php esc_attr_e('Dismiss REST API alert', 'save-wpf-entries'); ?>">
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

    public function display_aem_notice()
    {
    ?>
<div
    x-data="{ show: true, expanded: false }"
    x-show="show"
    x-cloak
    x-transition
    class="mb-5 rounded-xl border border-blue-300 bg-blue-50 text-blue-800 p-4 relative shadow-sm space-y-3"
>
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
            <?php echo wp_kses_post(sprintf(
                __('Need help managing entries or exporting them to Google Sheets? <a href="%s" class="text-blue-600 hover:underline font-medium" target="_blank" rel="noopener noreferrer">Visit the documentation</a>.', 'save-wpf-entries'),
                esc_url('https://entriesmanager.com/aem/docs')
            )); ?>
        </div>

        <!-- Expand Button -->
        <button
            @click="expanded = !expanded"
            class="text-blue-500 hover:text-blue-700 transition transform"
            :aria-label="expanded ? '<?php echo esc_js(__('Collapse details', 'save-wpf-entries')); ?>' : '<?php echo esc_js(__('Expand details', 'save-wpf-entries')); ?>'"
        >
            <svg :class="{ 'rotate-180': expanded }" class="w-5 h-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dismiss Button -->
        <button
            @click="show = false"
            class="ml-2 text-blue-400 hover:text-blue-700 transition"
            aria-label="<?php esc_attr_e('Dismiss', 'save-wpf-entries'); ?>"
        >
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
            <a href="https://entriesmanager.com/aem/docs#getting-started" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-blue-100 rounded-lg p-3 hover:bg-blue-100 transition shadow-sm">
                <svg class="w-5 h-5 text-green-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor">
                    <path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/>
                </svg>
                <span>Getting Started Guide</span>
            </a>

            <!-- Entry Viewer Modal -->
            <a href="https://entriesmanager.com/aem/docs#entry-modal" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-indigo-100 rounded-lg p-3 hover:bg-indigo-100 transition shadow-sm">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 4v16h18V4H3zm16 14H5V6h14v12z"/>
                </svg>
                <span>View Full Entry in Modal</span>
            </a>

             <!-- Bulk Actions -->
            <a href="https://entriesmanager.com/aem/docs#bulk-actions" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-blue-100 rounded-lg p-3 hover:bg-blue-100 transition shadow-sm">
                <svg class="w-5 h-5 text-purple-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 14.5h-2V11h2v5.5zm0-7h-2V9h2v.5z" />
                </svg>
                <span>Bulk Actions & Filters</span>
            </a>

            <!-- Export & Field Exclusion -->
            <a href="https://entriesmanager.com/aem/docs#csv-export" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-green-100 rounded-lg p-3 hover:bg-green-100 transition shadow-sm">
                <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M5 20h14v-2H5v2zm7-18L5.33 9h4.67v6h4V9h4.67L12 2z"/>
                </svg>
                <span>CSV Export & Field Exclusion</span>
            </a>

            <!-- Commenting Feature -->
            <a href="https://entriesmanager.com/aem/docs#entry-comments" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-pink-100 rounded-lg p-3 hover:bg-pink-100 transition shadow-sm">
                <svg class="w-5 h-5 text-pink-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
                </svg>
                <span>Comment on Entries</span>
            </a>

            <!-- Print Friendly -->
            <a href="https://entriesmanager.com/aem/docs#print-entry" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-indigo-100 rounded-lg p-3 hover:bg-indigo-100 transition shadow-sm">
                <svg class="w-5 h-5 text-indigo-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 8H5V5h14m0 14H5v-4h14m0-10a2 2 0 0 1 2 2v6h-2v4H5v-4H3V8a2 2 0 0 1 2-2h14z"/>
                </svg>
                <span>Print Friendly Entries</span>
            </a>

            <!-- Google Sheets Sync -->
            <a href="https://entriesmanager.com/aem/docs#google-sheets-sync" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-yellow-100 rounded-lg p-3 hover:bg-yellow-100 transition shadow-sm">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 3v18h18V3H3zm6 14H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/>
                </svg>
                <span>Sync to Google Sheets</span>
            </a>

            <!-- Migration Help -->
            <a href="https://entriesmanager.com/aem/docs#migration" target="_blank" rel="noopener noreferrer" class="flex items-start space-x-2 bg-white/60 border border-red-100 rounded-lg p-3 hover:bg-red-100 transition shadow-sm">
                <svg class="w-5 h-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L3 21h18L12 2zm0 3.84L17.53 19H6.47L12 5.84zM11 10h2v4h-2v-4zm0 6h2v2h-2v-2z"/>
                </svg>
                <span>Entry Migration Guide</span>
            </a>
        </div>
    </div>
</div>

<?php
    }
}
