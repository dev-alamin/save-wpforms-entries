            <div
                x-show="entryModalOpen"
                class="fixed inset-0 flex items-center justify-center backdrop-blur-sm bg-black/30 z-50"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">

                <!-- Modal Content -->
                <div
                    class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-8 sm:p-10 relative border border-indigo-300 ring-1 ring-indigo-200"
                    x-transition:enter="transition ease-out duration-300 transform"
                    x-transition:enter-start="opacity-0 scale-90"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200 transform"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-90">

                    <!-- Close Button -->
                    <button
                        @click="entryModalOpen = false"
                        class="absolute top-5 right-6 text-gray-400 hover:text-gray-700 !text-3xl font-bold leading-none focus:outline-none transition">
                        &times;
                    </button>

                    <!-- Entry Details Header with Copy Button -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-extrabold text-indigo-700"> <?php esc_html_e('Entry Details', 'advanced-entries-manager-for-wpforms'); ?></h2>

                        <button
                            @click="copyEntryToClipboard"
                            class="flex items-center gap-2 text-indigo-600 hover:text-indigo-800 bg-slate-200 cursor-pointer rounded p-1 !text-lg font-semibold transition"
                            title="Copy all to clipboard">
                            <template x-if="copied">
                                <span class="text-green-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z"/>
                                    </svg>
                                </span>
                            </template>
                            <template x-if="!copied">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M120-220v-80h80v80h-80Zm0-140v-80h80v80h-80Zm0-140v-80h80v80h-80ZM260-80v-80h80v80h-80Zm100-160q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480Zm40 240v-80h80v80h-80Zm-200 0q-33 0-56.5-23.5T120-160h80v80Zm340 0v-80h80q0 33-23.5 56.5T540-80ZM120-640q0-33 23.5-56.5T200-720v80h-80Zm420 80Z"/></svg>
                                </span>
                            </template>
                            <span x-text="copied ? 'Copied!' : 'Copy Entry'"></span>
                        </button>
                    </div>

                    <!-- Entry Items -->
                    <div class="space-y-3">
                        <template x-for="(value, key) in selectedEntry.entry" :key="key">
                            <div class="text-base sm:text-lg text-gray-800 border-b border-dashed border-gray-300 pb-2">
                                <strong
                                    class="font-semibold text-gray-700"
                                    x-text="key.charAt(0).toUpperCase() + key.slice(1) + ':'">
                                </strong>
                                <span class="ml-1" x-text="value || '<?php echo esc_js(__('-', 'advanced-entries-manager-for-wpforms')); ?>'"></span>
                            </div>
                        </template>
                    </div>

                   <!-- Note Section -->
                    <div class="mt-8">
                        <button
                            @click="noteOpen = !noteOpen"
                            class="text-indigo-600 font-semibold flex items-center gap-2 hover:text-indigo-800 transition"
                            :aria-expanded="noteOpen.toString()"
                            :aria-controls="'swpfe-note-section'"
                        >
                            <!-- Icon toggle -->
                            <template x-if="noteOpen">
                                <!-- ❌ Close Icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" class="text-indigo-500">
                                    <path d="M480-432 336-288q-12 12-28.5 12T279-288q-12-12-12-28.5t12-28.5l144-144-144-144q-12-12-12-28.5t12-28.5q12-12 28.5-12t28.5 12l144 144 144-144q12-12 28.5-12t28.5 12q12 12 12 28.5T681-633L537-489l144 144q12 12 12 28.5T681-288q-12 12-28.5 12T624-288L480-432Z"/>
                                </svg>
                            </template>

                            <template x-if="!noteOpen && (!selectedEntry.note || selectedEntry.note.trim() === '')">
                                <!-- ➕ Add Note Icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                    <path d="M440-240h80v-120h120v-80H520v-120h-80v120H320v80h120v120ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/>
                            </svg>
                            </template>

                            <template x-if="!noteOpen && selectedEntry.note && selectedEntry.note.trim() !== ''">
                                <!-- ✏️ Edit Icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" class="text-indigo-500">
                                    <path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/>
                                </svg>
                            </template>

                            <!-- Smart label -->
                            <span x-text="noteOpen
                                ? '<?php echo esc_js(__('Close Note', 'save-wpf-entries')); ?>'
                                : (selectedEntry.note && selectedEntry.note.trim() !== ''
                                    ? '<?php echo esc_js(__('Edit Note', 'save-wpf-entries')); ?>'
                                    : '<?php echo esc_js(__('Add Note', 'save-wpf-entries')); ?>'
                                )">
                            </span>
                        </button>

                        <!-- Note content display (only shown when not editing and note exists) -->
                        <template x-if="!noteOpen && selectedEntry.note && selectedEntry.note.trim() !== ''">
                            <p class="mt-3 text-base text-gray-700 bg-indigo-50 px-4 py-3 rounded-xl border border-indigo-200 shadow-sm">
                                <span x-text="selectedEntry.note"></span>
                            </p>
                        </template>

                        <!-- Note editor (only shown when editing) -->
                        <div
                            x-ref="noteEditor"
                            x-show="noteOpen"
                            x-collapse
                            class="mt-4 overflow-hidden transition-all duration-300 ease-in-out"
                        >
                            <label for="swpfe_note_fields" class="block text-sm font-medium text-gray-700 mb-1">
                                <?php esc_html_e('Your Note', 'save-wpf-entries'); ?>
                            </label>
                            <textarea
                                id="swpfe_note_fields"
                                name="swpfe_note"
                                x-model="selectedEntry.note"
                                rows="5"
                                maxlength="1000"
                                placeholder="<?php echo esc_attr(__('Write something helpful for this entry…', 'save-wpf-entries')); ?>"
                                class="w-full bg-white border border-indigo-200 rounded-xl px-4 py-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-800 placeholder-gray-400 transition"
                            ></textarea>

                            <div class="mt-2 flex justify-between items-center">
                                <p class="text-sm text-gray-500">
                                    <?php esc_html_e('Max 1000 characters. Avoid sensitive data.', 'save-wpf-entries'); ?>
                                </p>
                                <button
                                    @click="validateAndSaveNote"
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition font-semibold flex items-center"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-2" height="20px" viewBox="0 -960 960 960" width="20px" fill="#fff">
                                        <path d="M840-680v480q0 33-23.5 56.5T760-120H200q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h480l160 160Zm-80 34L646-760H200v560h560v-446ZM480-240q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35ZM240-560h360v-160H240v160Z"/>
                                    </svg>
                                    <?php esc_html_e('Save Note', 'save-wpf-entries'); ?>
                                </button>
                            </div>
                        </div>
                    </div>



                    <!-- Actions -->
                    <div class="mt-8 flex flex-wrap gap-4 justify-end">
                        <button
                            @click="toggleModalReadStatus()"
                            class="px-5 py-2.5 cursor-pointer bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition">
                            <template x-if="selectedEntry.status === 'unread'">
                                ✅ <span><?php esc_html_e('Mark as Read', 'advanced-entries-manager-for-wpforms'); ?></span>
                            </template>
                            <template x-if="selectedEntry.status === 'read'">
                                🕓 <span><?php esc_html_e('Mark as unread', 'advanced-entries-manager-for-wpforms'); ?></span>
                            </template>
                        </button>

                        <button
                            @click="deleteEntry()"
                            class="px-5 py-2.5 cursor-pointer bg-red-600 text-white rounded-lg !font-bold hover:bg-red-700 transition">
                            🗑️ <?php esc_html_e('Delete', 'advanced-entries-manager-for-wpforms'); ?>
                        </button>

                    </div>
                </div>
            </div>