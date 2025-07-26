 <!-- Filter Controls -->
 <div
     class="relative w-full"
     x-data="formEntriesApp(form.form_id, form.entry_count)"
     x-init="fetchEntries()">
     <!-- Controls -->
     <div class="flex flex-wrap gap-4 items-center py-4 px-4">
         <?php
            $swpfe_bulk_actions = [
                [
                    'key'   => 'mark_read',
                    'label' => __('Mark as Read', 'save-wpf-entries'),
                    'icon'  => '✅',
                ],
                [
                    'key'   => 'mark_unread',
                    'label' => __('Mark as Unread', 'save-wpf-entries'),
                    'icon'  => '🕓',
                ],
                [
                    'key'   => 'favorite',
                    'label' => __('Mark as Favorite', 'save-wpf-entries'),
                    'icon'  => '⭐',
                ],
                [
                    'key'   => 'unfavorite',
                    'label' => __('Unmark Favorite', 'save-wpf-entries'),
                    'icon'  => '☆',
                ],
                [
                    'key'   => 'mark_spam',
                    'label' => __('Mark as Spam', 'save-wpf-entries'),
                    'icon'  => '🚫',
                ],
                [
                    'key'   => 'unmark_spam',
                    'label' => __('Unmark Spam', 'save-wpf-entries'),
                    'icon'  => '❎',
                ],
                [
                    'key'   => 'delete',
                    'label' => __('Delete Entries', 'save-wpf-entries'),
                    'icon'  => '🗑️',
                ],
            ];
            ?>
         <div
             class="flex items-center justify-between mt-6 gap-4"
             x-data='{
                            showBulkMenu: false,
                            selectedBulkAction: "",
                            bulkActions: <?php echo json_encode($swpfe_bulk_actions); ?>
                        }'
             x-cloak>
             <!-- Dropdown Button -->
             <div class="relative w-60">
                 <button
                     type="button"
                     @click="showBulkMenu = !showBulkMenu"
                     class="flex items-center justify-between w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-gray-800 font-medium hover:border-indigo-500 transition"
                     :disabled="bulkSelected.length === 0">
                     <span x-text="selectedBulkAction
                                        ? (bulkActions.find(a => a.key === selectedBulkAction)?.icon + ' ' + bulkActions.find(a => a.key === selectedBulkAction)?.label)
                                        : '<?php echo esc_js(__('Select Action', 'save-wpf-entries')); ?>'">
                     </span>
                     <svg class="ml-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                             d="M19 9l-7 7-7-7" />
                     </svg>
                 </button>

                 <!-- Dropdown List -->
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
                             <span x-text="action.icon"></span>
                             <span x-text="action.label"></span>
                         </li>
                     </template>
                 </ul>
             </div>

             <!-- Apply Button -->
             <button
                 @click="performBulkAction(selectedBulkAction)"
                 :disabled="bulkSelected.length === 0 || !selectedBulkAction"
                 class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed font-semibold">
                 <?php esc_html_e('Apply', 'save-wpf-entries'); ?>
             </button>
         </div>

         <!-- Search Input -->
         <div class="relative">
             <input
                 type="search"
                 aria-label="<?php esc_attr_e('Search entries', 'advanced-entries-manager-for-wpforms'); ?>"
                 class="border px-3 py-2 rounded w-[250px] focus:outline-none focus:ring-2 focus:ring-indigo-500"
                 placeholder="<?php echo esc_attr__('🔍 Search...', 'advanced-entries-manager-for-wpforms'); ?>"
                 x-model="searchQuery"
                 @input.debounce.400ms="handleSearchInput">

             <!-- Floating Results -->
             <div
                 x-show="searchQuery && entries.length"
                 x-transition
                 class="absolute z-50 top-[100%] left-0 mt-1 w-full bg-white shadow-xl border border-gray-200 rounded-md max-h-72 overflow-auto"
                 role="listbox">
                 <template x-for="(entry, i) in entries" :key="entry.id">
                     <div
                         @click="showEntry(i)"
                         class="px-4 py-3 border-b border-[#ddd] hover:bg-indigo-50 cursor-pointer text-sm"
                         role="option"
                         tabindex="0"
                         @keydown.enter.prevent="showEntry(i)">
                         <div class="font-semibold" x-text="entry.entry?.Email || '-'"></div>
                         <div class="text-xs text-gray-500" x-text="timeAgo(entry.date)"></div>
                     </div>
                 </template>
             </div>

             <!-- No Results -->
             <div
                 x-show="searchQuery && !entries.length && !loading"
                 class="absolute z-50 top-[100%] left-0 mt-1 w-full bg-white border border-gray-200 rounded-md shadow px-4 py-3 text-sm text-gray-500">
                 <?php esc_html_e('No matching entries found.', 'advanced-entries-manager-for-wpforms'); ?>
             </div>

             <!-- Loading State -->
             <div
                 x-show="loading"
                 class="absolute top-2 right-3 text-xs text-indigo-500 animate-pulse"
                 aria-live="assertive"
                 aria-atomic="true">
                 <?php esc_html_e('Searching...', 'advanced-entries-manager-for-wpforms'); ?>
             </div>
         </div>

         <!-- Status Filter -->
         <select
             x-model="filterStatus"
             @change="handleStatusChange"
             aria-label="<?php esc_attr_e('Filter entries by status', 'advanced-entries-manager-for-wpforms'); ?>"
             class="border px-3 py-2 rounded text-sm text-gray-700">
             <option value="all"><?php esc_html_e('All Status', 'advanced-entries-manager-for-wpforms'); ?></option>
             <option value="read">✅ <?php esc_html_e('Read', 'advanced-entries-manager-for-wpforms'); ?></option>
             <option value="unread">🕓 <?php esc_html_e('Unread', 'advanced-entries-manager-for-wpforms'); ?></option>
         </select>

         <!-- Favorites Only -->
         <label class="flex items-center gap-2 text-sm text-gray-700">
             <input
                 type="checkbox"
                 x-model="onlyFavorites"
                 @change="handleFavoriteToggle"
                 class="form-checkbox rounded"
                 aria-checked="false">
             <span><?php esc_html_e('Only Favorites', 'advanced-entries-manager-for-wpforms'); ?></span>
         </label>
         <!-- Date From -->
         <div>
             <label for="date_from" class="block text-xs font-medium text-gray-600 mb-1">
                 <?php esc_html_e('From Date', 'advanced-entries-manager-for-wpforms'); ?>
             </label>
             <input
                 type="date"
                 id="date_from"
                 x-model="dateFrom"
                 @change="handleDateFilterChange"
                 class="border px-3 py-2 rounded text-sm text-gray-700">
         </div>

         <!-- Date To -->
         <div>
             <label for="date_to" class="block text-xs font-medium text-gray-600 mb-1">
                 <?php esc_html_e('To Date', 'advanced-entries-manager-for-wpforms'); ?>
             </label>
             <input
                 type="date"
                 id="date_to"
                 x-model="dateTo"
                 @change="handleDateFilterChange"
                 class="border px-3 py-2 rounded text-sm text-gray-700">
         </div>
     </div>
 </div>