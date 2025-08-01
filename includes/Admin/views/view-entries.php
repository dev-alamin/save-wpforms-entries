<?php do_action('swpfe_before_entries_ui'); ?>

<div
    x-data="toastHandler()"
    x-show="visible"
    x-transition
    x-init="$watch('visible', v => v && setTimeout(() => visible = false, 3000))"
    class="fixed top-10 right-4 z-50 px-4 py-2 rounded shadow-lg text-white d-none z-[100]"
    :class="{
        'bg-[#4f46e5]': type === 'success',
        'bg-red-600': type === 'error',
        'bg-yellow-500': type === 'warning',
        'bg-blue-600': type === 'info'
    }"
    x-text="message"></div>

<div
    x-data="entriesApp()"
    x-init="fetchForms()"
    class="wrap swpfe-admin-page min-h-screen max-w-7xl !m-auto px-8 py-10 text-[15px] font-inter"
    role="main"
    aria-label="<?php echo esc_attr__('WPForms Entries Overview', 'advanced-entries-manager-for-wpforms'); ?>">
    <!-- Header -->
    <div class="mb-8 bg-slate-700 text-white px-4 py-2 rounded-lg">
        <h1 class="!text-4xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
            📋 <span><?php esc_html_e('WPForms Entries Overview', 'advanced-entries-manager-for-wpforms'); ?></span>
        </h1>
        <p class="text-gray-200 !text-[15px] leading-relaxed">
            <?php
            esc_html_e('Browse and manage form entries submitted by users. Click on a form to view its submissions, 
            mark entries as read/unread, or delete them as needed.', 'advanced-entries-manager-for-wpforms');
            ?>
        </p>
    </div>

    <!-- Migration Prompt -->
    <?php
    use App\AdvancedEntryManager\Utility\Helper;
    if ( Helper::is_wpformsdb_table_exists() && ! Helper::get_option( 'migration_complete' ) ) : ?>
        <div
            x-data="{ showMigrationNotice: true }"
            x-show="showMigrationNotice"
            x-transition
            class="mb-6 border border-yellow-400 bg-yellow-50 text-yellow-800 rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold mb-1">📦 Migrate from WPFormsDB</h2>
                    <p class="text-sm">
                        We found data in the legacy <code>wpforms_db</code> table.
                        You can migrate all your entries into our advanced manager in just a few clicks.
                    </p>
                </div>
                <div class="flex gap-2">
                    <!-- Trigger modal or redirect -->
                    <button
                        @click="window.location.href = '<?php echo esc_url(admin_url('admin.php?page=swpfe-migration')); ?>'"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition">
                        🚀 Start Migration
                    </button>
                    <button
                        @click="showMigrationNotice = false"
                        class="text-sm text-gray-600 hover:text-gray-900 transition">
                        ✖
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loop Over Forms -->
    <template x-for="form in forms" :key="form.form_id">
        <div x-data="formTable(form)" class="mb-10">

            <!-- Clickable Form Header -->
            <div
                @click="toggleOpen()"
                class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-6 rounded-xl shadow border border-gray-300 flex items-center justify-between hover:shadow-lg transition duration-200 group"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="toggleOpen()"
                aria-expanded="false"
                :aria-expanded="open.toString()"
                :aria-controls="'entries-table-' + form.form_id">
                <div class="flex items-center gap-4">
                    <div class="shrink-0 bg-indigo-100 text-indigo-600 rounded-xl p-2" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17v-2a4 4 0 014-4h7M9 17a4 4 0 01-4-4V7a4 4 0 014-4h11a4 4 0 014 4v6a4 4 0 01-4 4H9z" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800" x-text="form.form_title"></h2>
                        <p class="text-sm text-gray-600 font-medium">
                            🆔 <strong><?php esc_html_e('Form ID:', 'advanced-entries-manager-for-wpforms'); ?></strong> <span x-text="form.form_id"></span> &nbsp; | &nbsp;
                            📌 <strong><?php esc_html_e('Total Entries:', 'advanced-entries-manager-for-wpforms'); ?></strong>
                            <span
                                x-text="formatNumber(form.entry_count)"
                                :title="formatFullNumber(form.entry_count)"
                                class="cursor-help"
                                aria-label="<?php esc_attr_e('Total number of entries', 'advanced-entries-manager-for-wpforms'); ?>">
                            </span>
                        </p>
                    </div>
                </div>

                <div class="text-sm font-medium flex items-center gap-1 px-2 py-1 rounded-md text-indigo-700 transition cursor-pointer select-none">
                    <span x-show="!open" class="group-hover:underline" aria-hidden="true">
                        <?php esc_html_e('Click to view entries', 'advanced-entries-manager-for-wpforms'); ?>
                    </span>
                    <span x-show="open" class="group-hover:underline" aria-hidden="true">
                        <?php esc_html_e('Hide entries', 'advanced-entries-manager-for-wpforms'); ?>
                    </span>
                    <svg :class="open ? 'rotate-180' : ''"
                        class="w-4 h-4 transition-transform duration-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        aria-hidden="true"
                        focusable="false">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Entries Table -->
            <div
                x-show="open"
                x-transition
                id="entries-table-"
                :id="'entries-table-' + form.form_id"
                role="region"
                aria-live="polite"
                aria-label="<?php esc_attr_e('Entries table for form', 'advanced-entries-manager-for-wpforms'); ?>">
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200">
                    <?php include __DIR__ . '/table/filter-section.php'; ?>

                    <!-- Header Row -->
                    <div
                        class="items-center px-6 py-3 bg-gray-100 border-b border-gray-300 text-sm font-semibold text-gray-700 uppercase tracking-wide"
                        style="display: grid; grid-template-columns: 50px 1fr 150px 150px 250px;"
                        role="row">

                        <div role="columnheader">
                            <!-- <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                    <path d="M240-360h280l80-80H240v80Zm0-160h240v-80H240v80Zm-80-160v400h280l-80 80H80v-560h800v120h-80v-40H160Zm756 212q5 5 5 11t-5 11l-36 36-70-70 36-36q5-5 11-5t11 5l48 48ZM520-120v-70l266-266 70 70-266 266h-70ZM160-680v400-400Z"/>
                                </svg> -->
                            <input
                                type="checkbox"
                                id="bulk_action_main"
                                @change="toggleSelectAll($event)"
                                class="cursor-pointer" />

                        </div>

                        <div role="columnheader"><?php esc_html_e('Email', 'advanced-entries-manager-for-wpforms'); ?></div>
                        <div role="columnheader" class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByDate">
                            <span><?php esc_html_e('Date', 'advanced-entries-manager-for-wpforms'); ?></span>
                            <span x-text="sortAsc ? '⬆️' : '⬇️'"></span>
                        </div>
                        <div role="columnheader" class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByStatus">
                            <span><?php esc_html_e('Status', 'advanced-entries-manager-for-wpforms'); ?></span>
                            <span x-text="sortAscStatus ? '⬆️' : '⬇️'"></span>
                        </div>
                        <div role="columnheader" class="text-right"><?php esc_html_e('Actions', 'advanced-entries-manager-for-wpforms'); ?></div>
                    </div>

                    <div class="relative min-h-60">
                        <!-- Entries Rows -->
                        <template x-show="!loading" x-for="(entry, i) in entries" :key="entry.id">

                            <div
                                :class="[
                                    bgClasses[i % bgClasses.length],
                                    entry.status === 'unread' ? 'font-bold' : 'font-normal',
                                    entry.is_spam == 1 ? 'bg-red-50 opacity-50' : '',
                                    ]"
                                class="grid items-center px-6 text-sm text-gray-800 border-b border-gray-100 hover:bg-gray-50"
                                style="grid-template-columns: 50px 1fr 150px 150px 250px;"
                                role="row">
                                <input
                                    type="checkbox"
                                    :value="entry.id"
                                    x-model="bulkSelected"
                                    @click="handleCheckbox($event, entry.id)"
                                    class="cursor-pointer" />


                                <div class="py-4 cursor-pointer" title="<?php echo esc_attr__('Click for details', 'advanced-entries-manager-for-wpforms'); ?>" @click="showEntry(i)" x-text="entry.entry?.Email || entry.entry?.email || '-'"></div>
                                <div class="py-4 text-center" x-text="timeAgo(entry.date)" :title="entry.date"></div>
                                <div class="py-4 text-center">
                                    <span
                                        class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                        :class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                        x-text="entry.status.charAt(0).toUpperCase() + entry.status.slice(1)"></span>
                                </div>
                                <?php include __DIR__ . '/table/action-column.php'; ?>
                            </div>
                        </template>

                        <!-- Loader Overlay -->
                        <div
                            x-show="loading"
                            x-transition
                            class="absolute inset-0 z-50 flex items-center justify-center bg-white/60 backdrop-blur-sm">
                            <lottie-player
                                src="<?php echo esc_url(SWPFE_URL . 'assets/admin/lottie/loading.json'); ?>"
                                background="transparent"
                                speed="1"
                                class="w-auto h-auto"
                                loop
                                autoplay>
                            </lottie-player>
                        </div>

                    </div>

                    <!-- Pagination Controls -->
                    <?php include __DIR__ . '/table/pagination.php'; ?>
                </div>
            </div>
            <!-- Entries Table -->
            <!-- Entry Modal -->
            <?php include __DIR__ . '/modal.php'; ?>
        </div>
    </template>

    <!-- Before loading the forms, show a loading state -->
    <div x-show="loading" class="flex items-center justify-center min-h-60">
        <lottie-player
            src="<?php echo esc_url(SWPFE_URL . 'assets/admin/lottie/loading.json'); ?>"
            background="transparent"
            speed="1"
            class="w-auto h-auto"
            loop
            autoplay>
        </lottie-player>
    </div>

    <!-- If no forms available -->
    <?php include __DIR__ . '/empty-page.php'; ?>
</div>
<?php do_action('swpfe_after_entries_ui'); ?>