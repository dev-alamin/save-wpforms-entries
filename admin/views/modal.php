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
                        <h2 class="text-2xl font-extrabold text-indigo-700">Entry Details</h2>

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
                                <strong class="font-semibold text-gray-700" x-text="key + ':'"></strong>
                                <span class="ml-1" x-text="value || '-'"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Actions -->
                    <div class="mt-8 flex flex-wrap gap-4 justify-end">
                        <button
                            @click="markAs('read')"
                            class="px-5 py-2.5 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition"
                            :disabled="selectedEntry.status === 'read'">
                            ✅ Mark as Read
                        </button>

                        <button
                            @click="markAs('unread')"
                            class="px-5 py-2.5 bg-yellow-500 text-white rounded-lg font-semibold hover:bg-yellow-600 transition"
                            :disabled="selectedEntry.status === 'unread'">
                            🕓 Mark as Unread
                        </button>

                        <button
                            @click="deleteEntry()"
                            class="px-5 py-2.5 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
            </div>