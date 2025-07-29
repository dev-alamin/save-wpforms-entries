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
             class="flex items-center justify-between gap-4"
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
                     class="flex items-center justify-between w-full px-4 py-[11px] bg-white border border-gray-300 rounded-lg shadow-sm text-gray-800 font-medium hover:border-indigo-500 transition"
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
                 class="px-5 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed font-semibold">
                 <?php esc_html_e('Apply', 'advanced-entries-manager-for-wpforms'); ?>
             </button>
         </div>

         <!-- Search Input -->
         <div x-data="{
        searchQuery: '',
        searchType: 'email',
        dropdownOpen: false,
        loading: false,
        entries: [],
        types: [
            { key: 'email', label: 'Email' },
            { key: 'name', label: 'Name' },
            { key: 'id', label: 'Entry ID' }
        ]
    }"
             @search.window="e => { searchQuery = e.detail.query; searchType = e.detail.type; loading = true; fetchEntries(); }"
             class="relative flex items-center md:w-[450px] sm:w-[300px]">

             <!-- Dropdown -->
             <div class="relative min-w-[95px] !border !border-gray-300 !border-r-0 !rounded-l-lg !rounded-tr-none !rounded-br-none">
                 <button @click="dropdownOpen = !dropdownOpen"
                     class="!px-3 !py-[10px] !text-gray-800 !font-medium !hover:border-indigo-500 !transition !text-sm flex items-center gap-1"
                     type="button"
                     aria-label="Select search type">
                     <span x-text="types.find(t => t.key === searchType)?.label"></span>
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

             <!-- Search Input -->
             <div class="relative w-full">
                 <input
                     type="search"
                     aria-label="Search entries"
                     class="!w-full !px-3 !py-[6px] !border !border-gray-300 !rounded-r-lg !rounded-tl-none !rounded-bl-none !text-gray-800 !font-medium !focus:outline-none !focus:ring-2 !focus:ring-indigo-500 !transition !hover:border-indigo-500"
                     :placeholder="`🔍 Search by ${searchType}...`"
                     x-model="searchQuery"
                     @input.debounce.400ms="handleSearchInput" />

                 <!-- No Results -->
                 <div
                     x-show="searchQuery && !entries.length && !loading"
                     x-transition
                     class="absolute z-50 top-[100%] left-0 mt-1 !w-full !bg-white !border !border-gray-200 !rounded-lg !shadow !px-4 !py-3 text-sm !text-gray-500">
                     <?php esc_html_e('No matching entries found.', 'advanced-entries-manager-for-wpforms'); ?>
                 </div>

                 <!-- Loading -->
                 <div
                     x-show="loading"
                     class="absolute top-2 right-3 text-xs !text-indigo-500 animate-pulse"
                     aria-live="assertive"
                     aria-atomic="true">
                     <?php esc_html_e('Searching...', 'advanced-entries-manager-for-wpforms'); ?>
                 </div>

                 <!-- Result List (optional) -->
                 <div
                     x-show="entries.length && searchQuery"
                     x-transition
                     class="absolute z-50 top-[100%] left-0 mt-1 !w-full !bg-white !shadow-lg !border !border-gray-200 !rounded-lg max-h-72 overflow-auto"
                     role="listbox">
                     <template x-for="(entry, i) in entries" :key="entry.id">
                         <div
                             @click="showEntry(i)"
                             class="flex flex-col px-4 py-3 border-b border-gray-200 hover:bg-indigo-50 cursor-pointer text-sm"
                             role="option"
                             tabindex="0"
                             @keydown.enter.prevent="showEntry(i)">
                             <div class="font-semibold text-indigo-700 truncate" x-text="entry.entry?.Email || '-'"></div>
                             <div class="text-gray-700 truncate" x-text="entry.name || '-'"></div>
                             <div class="text-xs text-gray-500 mt-1" x-text="timeAgo(entry.date)"></div>
                         </div>
                     </template>


                 </div>
             </div>
         </div>
         <!-- Status Filter -->
     </div>
 </div>