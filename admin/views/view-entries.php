<div x-data="entriesApp()" x-init="fetchForms()" class="wrap swpfe-admin-page min-h-screen px-8 py-10 text-[15px] font-inter">
<!-- Header -->
    <div class="mb-8 bg-slate-700 text-white px-4 py-2 rounded-lg">
        <h1 class="!text-4xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
            📋 <span><?php esc_html_e('WPForms Entries Overview', 'save-wpf-entries'); ?></span>
        </h1>
        <p class="text-gray-200 !text-[15px] leading-relaxed">
            <?php esc_html_e('Browse and manage form entries submitted by users. Click on a form to view its submissions, mark entries as read/unread, or delete them as needed.', 'save-wpf-entries'); ?>
        </p>
    </div>

    <!-- Loop Over Forms -->
    <template x-for="form in forms" :key="form.form_id">
        <div x-data="formTable(form)" class="mb-10">
            
            <!-- Clickable Form Header -->
            <div
                @click="toggleOpen()"
                class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-6 rounded-xl shadow border border-gray-300 flex items-center justify-between hover:shadow-lg transition duration-200 group"
            >
                <div class="flex items-center gap-4">
                    <div class="shrink-0 bg-indigo-100 text-indigo-600 rounded-xl p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17v-2a4 4 0 014-4h7M9 17a4 4 0 01-4-4V7a4 4 0 014-4h11a4 4 0 014 4v6a4 4 0 01-4 4H9z" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800" x-text="form.form_title"></h2>
                        <p class="text-sm text-gray-600 font-medium">
                            🆔 <strong><?php esc_html_e('Form ID:', 'save-wpf-entries'); ?></strong> <span x-text="form.form_id"></span> &nbsp; | &nbsp;
                            📌 <strong><?php esc_html_e('Total Entries:', 'save-wpf-entries'); ?></strong> <span x-text="form.entry_count"></span>
                        </p>
                    </div>
                </div>

                <div class="text-sm font-medium flex items-center gap-1 px-2 py-1 rounded-md text-indigo-700 transition cursor-pointer select-none">
                    <span x-show="!open" class="group-hover:underline">
                        <?php esc_html_e('Click to view entries', 'save-wpf-entries'); ?>
                    </span>
                    <span x-show="open" class="group-hover:underline">
                        <?php esc_html_e('Hide entries', 'save-wpf-entries'); ?>
                    </span>
                    <svg :class="open ? 'rotate-180' : ''"
                        class="w-4 h-4 transition-transform duration-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Entries Table -->
            <div x-show="open" x-transition>
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200">

                    <!-- Header Row -->
                    <div
                        class="items-center px-6 py-3 bg-gray-100 border-b border-gray-300 text-sm font-semibold text-gray-700 uppercase tracking-wide"
                        style="display: grid; grid-template-columns: 1fr 1fr 150px 250px;">
                        <div><?php esc_html_e('Email', 'save-wpf-entries'); ?></div>
                        <div class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByDate">
                            <span><?php esc_html_e('Date', 'save-wpf-entries'); ?></span>
                            <span x-text="sortAsc ? '⬆️' : '⬇️'"></span>
                        </div>
                        <div class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByStatus">
                            <span><?php esc_html_e('Status', 'save-wpf-entries'); ?></span>
                            <span x-text="sortAscStatus ? '⬆️' : '⬇️'"></span>
                        </div>
                        <div class="text-right"><?php esc_html_e('Actions', 'save-wpf-entries'); ?></div>
                    </div>

                    <!-- Entries Rows -->
                    <template x-for="(entry, i) in paginatedEntries" :key="entry.id">
                        <!-- <pre x-text="JSON.stringify(paginatedEntries, null, 2)"></pre> -->
                        <div
                            :class="[
                                bgClasses[i % bgClasses.length],
                                entry.status === 'unread' ? 'font-bold' : 'font-normal'
                            ]"
                            class="grid items-center px-6 text-sm text-gray-800 border-b border-gray-100 hover:bg-gray-50"
                            style="grid-template-columns: 1fr 1fr 150px 250px"
                        >
                            <div class="py-4 cursor-pointer" title="Click for details" @click="showEntry(i)" x-text="entry.entry?.Email || entry.entry?.email || '-'"></div>
                            <div class="py-4 text-center" x-text="timeAgo(entry.date)" :title="entry.date"></div>
                            <div class="py-4 text-center">
                                <span
                                    class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                    x-text="entry.status.charAt(0).toUpperCase() + entry.status.slice(1)">
                                </span>
                            </div>
                            <?php include __DIR__ . '/table/action-column.php'; ?>
                        </div>
                    </template>

                    <!-- Pagination Controls -->
                    <?php include __DIR__ . '/table/pagination.php'; ?>
                </div>
            </div>

            <!-- Entry Modal -->
            <?php include __DIR__ . '/modal.php'; ?>
        </div>
    </template>

    <!-- If no forms available -->
    <?php include __DIR__ . '/empty-page.php'; ?>
</div>
