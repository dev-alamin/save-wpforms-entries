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
                        <h2 class="text-2xl font-extrabold text-indigo-700"> <?php esc_html_e('Entry Details', 'save-wpf-entries'); ?></h2>

                        <button
                            @click="copyEntryToClipboard"
                            class="flex items-center gap-2 text-indigo-600 hover:text-indigo-800 !text-lg font-semibold transition"
                            title="Copy all to clipboard">
                            <template x-if="copied">
                                <span class="text-green-600">✅</span>
                            </template>
                            <template x-if="!copied">
                                <span>📋</span>
                            </template>
                            <span x-text="copied ? 'Copied to Clipboard!' : 'Copy Entry'"></span>
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
                                <span class="ml-1" x-text="value || '<?php echo esc_js(__('-', 'save-wpf-entries')); ?>'"></span>
                            </div>
                        </template>
                    </div>


                    <!-- Actions -->
                    <div class="mt-8 flex flex-wrap gap-4 justify-end">
                        <button class="py-2.5 px-5 cursor-pointer bg-violet-600 text-white rounded-lg font-semibold hover:bg-violet-700 transition" @click="printEntry(i)" title="Print Entry">
                            <?php esc_html_e('Print Entry', 'save-wpf-entries'); ?>
                        </button>

                        <button
                            @click="toggleModalReadStatus()"
                            class="px-5 py-2.5 cursor-pointer bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition">
                            <template x-if="selectedEntry.status === 'unread'">
                                ✅ <span><?php esc_html_e('Mark as Read', 'save-wpf-entries'); ?></span>
                            </template>
                            <template x-if="selectedEntry.status === 'read'">
                                🕓 <span><?php esc_html_e('Mark as unread', 'save-wpf-entries'); ?></span>
                            </template>
                        </button>

                        <button
                            @click="deleteEntry()"
                            class="px-5 py-2.5 cursor-pointer bg-red-600 text-white rounded-lg !font-bold hover:bg-red-700 transition">
                            🗑️ <?php esc_html_e('Delete', 'save-wpf-entries'); ?>
                        </button>

                    </div>
                </div>
            </div>