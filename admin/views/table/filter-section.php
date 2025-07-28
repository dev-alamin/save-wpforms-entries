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
                    'label' => __('Mark as Read', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>',
                ],
                [
                    'key'   => 'mark_unread',
                    'label' => __('Mark as Unread', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Z"/></svg>',
                ],
                [
                    'key'   => 'favorite',
                    'label' => __('Mark as Favorite', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="m320-240 160-122 160 122-60-198 160-114H544l-64-208-64 208H220l160 114-60 198ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>',
                ],
                [
                    'key'   => 'unfavorite',
                    'label' => __('Unmark Favorite', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="m354-287 126-76 126 77-33-144 111-96-146-13-58-136-58 135-146 13 111 97-33 143ZM233-120l65-281L80-590l288-25 112-265 112 265 288 25-218 189 65 281-247-149-247 149Zm247-350Z"/></svg>',
                ],
                [
                    'key'   => 'mark_spam',
                    'label' => __('Mark as Spam', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q54 0 104-17.5t92-50.5L228-676q-33 42-50.5 92T160-480q0 134 93 227t227 93Zm252-124q33-42 50.5-92T800-480q0-134-93-227t-227-93q-54 0-104 17.5T284-732l448 448Z"/></svg>',
                ],
                [
                    'key'   => 'unmark_spam',
                    'label' => __('Unmark Spam', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="m456-320 104-104 104 104 56-56-104-104 104-104-56-56-104 104-104-104-56 56 104 104-104 104 56 56Zm-96 160q-19 0-36-8.5T296-192L80-480l216-288q11-15 28-23.5t36-8.5h440q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H360ZM180-480l180 240h440v-480H360L180-480Zm400 0Z"/></svg>',
                ],
                [
                    'key'   => 'delete',
                    'label' => __('Delete Entries', 'advanced-entries-manager-for-wpforms'),
                    'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>',
                ],
            ];
            ?>
         <div
             class="flex items-center justify-between mt-6 gap-4"
             x-data='{
         showBulkMenu: false,
         selectedBulkAction: "",
         bulkActions: <?php echo json_encode($swpfe_bulk_actions); ?>,
         bulkIcon() {
             const action = this.bulkActions.find(a => a.key === this.selectedBulkAction);
             return action ? action.icon : "";
         },
         bulkLabel() {
             const action = this.bulkActions.find(a => a.key === this.selectedBulkAction);
             return action ? action.label : "<?php echo esc_js(__('Select Action', 'advanced-entries-manager-for-wpforms')); ?>";
         }
     }'
             x-cloak>
             <!-- Dropdown Button -->
             <div class="relative w-60">
                 <button
                     type="button"
                     @click="showBulkMenu = !showBulkMenu"
                     class="flex items-center justify-between w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-gray-800 font-medium hover:border-indigo-500 transition"
                     :disabled="bulkSelected.length === 0">
                     <!-- Render icon + label using x-html -->
                     <span class="flex items-center gap-2" x-html="bulkIcon() + ' ' + bulkLabel()"></span>

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
                             <!-- Render icon as raw SVG -->
                             <span class="w-6 h-6" x-html="action.icon"></span>
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
                 <?php esc_html_e('Apply', 'advanced-entries-manager-for-wpforms'); ?>
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