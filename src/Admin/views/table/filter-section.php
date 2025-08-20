<div class="flex flex-wrap gap-4 items-center py-4 px-4">
    <?php
    include_once __DIR__ . '/../template-functions.php';
    $fembulk_actions = bulk_action_items();
    ?>
    <div
        class="flex items-center justify-between gap-4"
        x-data='{
            showBulkMenu: false,
            selectedBulkAction: "",
            bulkActions: <?php echo json_encode($fembulk_actions); ?>,
            bulkIcon() {
                const action = this.bulkActions.find(a => a.key === this.selectedBulkAction);
                return action ? action.icon : "";
            },
            bulkLabel() {
                const action = this.bulkActions.find(a => a.key === this.selectedBulkAction);
                return action ? action.label : "<?php echo esc_js(__('Select Action', 'forms-entries-manager')); ?>";
            }
        }'
        x-cloak>
        <div class="relative w-50">
            <button
                type="button"
                @click="showBulkMenu = !showBulkMenu"
                class="flex items-center justify-between w-full !px-3 !py-[10px] !border !border-gray-300 !rounded-lg !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500"
                :disabled="bulkSelected.length === 0">
                <span class="flex items-center gap-2" x-html="bulkIcon() + ' ' + bulkLabel()"></span>

                <svg class="ml-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <ul
                x-show="showBulkMenu"
                @click.outside="showBulkMenu = false"
                x-transition
                x-cloak
                class="absolute left-0 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                <template x-for="action in bulkActions" :key="action.key">
                    <li
                        @click="selectedBulkAction = action.key; showBulkMenu = false"
                        class="px-4 py-2 hover:bg-indigo-100 cursor-pointer text-sm text-gray-800 flex items-center gap-2"
                        :class="{ 'bg-indigo-50': selectedBulkAction === action.key }">
                        <span class="w-6 h-6" x-html="action.icon"></span>
                        <span x-text="action.label"></span>
                    </li>
                </template>
            </ul>
        </div>

        <button
            @click="performBulkAction(selectedBulkAction)"
            :disabled="bulkSelected.length === 0 || !selectedBulkAction"
            class="px-5 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed font-semibold">
            <?php esc_html_e('Apply', 'forms-entries-manager'); ?>
        </button>
    </div>

    <div class="flex items-center gap-4">
        <div class="relative md:w-[150px]">
            <label for="status-filter" class="sr-only"><?php esc_html_e( 'Filter by Status', 'forms-entries-manager' ); ?></label>
            <select
                id="status-filter"
                x-model="filterStatus"
                @change="handleStatusChange()"
                class="md:w-[150px] !px-3 !py-[6px] !border !border-gray-300 !rounded-lg !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500">
                <option value="all"><?php esc_html_e('All Statuses', 'forms-entries-manager'); ?></option>
                <option value="read"><?php esc_html_e('Read', 'forms-entries-manager'); ?></option>
                <option value="unread"><?php esc_html_e('Unread', 'forms-entries-manager'); ?></option>
            </select>
        </div>

        <div class="relative md:w-[150px]">
            <label for="date-from-filter" class="sr-only"><?php esc_html_e( 'Date From', 'forms-entries-manager' ); ?></label>
            <input
                id="date-from-filter"
                type="date"
                x-model="dateFrom"
                @change="handleDateChange()"
                class="!px-3 !py-[6px] !border !border-gray-300 !rounded-lg !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500">
        </div>

        <div class="relative md:w-[150px]">
            <label for="date-to-filter" class="sr-only"><?php esc_html_e( 'Date To', 'forms-entries-manager' ); ?></label>
            <input
                id="date-to-filter"
                type="date"
                x-model="dateTo"
                @change="handleDateChange()"
                class="!px-3 !py-[6px] !border !border-gray-300 !rounded-lg !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500">
        </div>
    </div>

    <div class="relative flex items-center md:w-[350px] sm:w-[300px]">
        <div class="relative min-w-[75px] !border !border-gray-300 !border-r-0 !rounded-l-lg !rounded-tr-none !rounded-br-none">
            <button @click="dropdownOpen = !dropdownOpen"
                class="!px-3 !py-[10px] !text-gray-800 !font-medium !hover:border-indigo-500 !transition !text-sm flex items-center gap-1"
                type="button"
                aria-label="<?php esc_attr_e('Select search type', 'forms-entries-manager'); ?>">
                <span x-text="searchType"></span>
                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <ul x-show="dropdownOpen" @click.outside="dropdownOpen = false" x-transition
                class="absolute z-50 mt-1 left-0 !bg-white !border !border-gray-300 !rounded-lg !shadow text-sm w-36">
                <template x-for="type in types" :key="type.key">
                    <li @click="searchType = type.key; dropdownOpen = false"
                        :class="{ 'bg-indigo-50 text-indigo-700': searchType === type.key }"
                        class="px-3 py-2 hover:bg-indigo-100 hover:text-indigo-700 cursor-pointer">
                        <span x-text="type.label"></span>
                    </li>
                </template>
            </ul>
        </div>

        <div class="relative w-full">
            <input
                type="search"
                aria-label="Search entries"
                class="!w-full !px-3 !py-[6px] !border !border-gray-300 !rounded-r-lg !rounded-tl-none !rounded-bl-none !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500"
                :placeholder="femStrings.searchPlaceholder.replace('%s', searchType)"
                x-model="searchQuery"
                @input.debounce.400ms="handleSearchInput" />

            <div
                x-show="searchQuery && !entries.length && !loading"
                x-transition
                class="absolute z-50 top-[100%] left-0 mt-1 !w-full !bg-white !border !border-gray-200 !rounded-lg !shadow !px-4 !py-3 text-sm !text-gray-500">
                <?php esc_html_e('No matching entries found.', 'forms-entries-manager'); ?>
            </div>

            <div
                x-show="loading"
                class="absolute top-2 right-3 text-xs !text-indigo-500 animate-pulse"
                aria-live="assertive"
                aria-atomic="true">
                <?php esc_html_e('Searching...', 'forms-entries-manager'); ?>
            </div>
        </div>
    </div>
</div>