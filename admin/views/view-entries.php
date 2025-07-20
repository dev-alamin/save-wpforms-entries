<?php
$bg_classes = [
    'bg-[#FAF7F3]',
    'bg-[#FFFFFF]',
];


$bg_classes = ['swpfe-row-bg-1', 'swpfe-row-bg-2', 'swpfe-row-bg-3', 'swpfe-row-bg-4'];

?>
<div x-data="entriesApp()" x-init="fetchEntries()" class="wrap swpfe-admin-page min-h-screen px-8 py-10 text-[15px] font-inter">
    <h1 class="!text-3xl !font-bold !text-gray-900 !mb-6">WPForms Entries Overview</h1>

    <template x-for="(formEntries, formId) in grouped" :key="formId">
        <div x-data="formTable(formEntries)" class="mb-10">
            <!-- Form Overview Row -->
            <div @click="open = !open"
                class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-6 py-4 rounded-lg shadow border border-gray-300 flex items-center justify-between hover:shadow-md transition">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800" x-text="all[0]?.form_title || 'Form'"></h2>
                    <p class="text-sm text-gray-600">Form ID: <span x-text="formId"></span> | Total Entries: <span x-text="all.length"></span></p>
                </div>
                <div class="text-indigo-600 text-sm font-medium">Click to view entries ⬇️</div>
            </div>

            <!-- Entries Table -->
            <div x-show="open" x-transition>
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200">
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(0,1fr))_100px] items-center px-6 py-3 bg-gray-100 border-b border-gray-300 text-sm font-semibold text-gray-700 uppercase tracking-wide">
                        <template x-for="(key, i) in keys" :key="i">
                            <div class="capitalize truncate" :title="key" x-text="key.split(' ').slice(0, 4).join(' ') + (key.split(' ').length > 4 ? '…' : '')"></div>
                        </template>
                        <div class="text-right uppercase">Status</div>
                    </div>


                    <template x-for="(entry, i) in all" :key="i">
                        <div
                            @click="showEntry(i)"
                            :class="bgClasses[i % bgClasses.length]"
                            class="grid grid-cols-[repeat(auto-fit,minmax(0,1fr))_100px] items-start px-6 py-4 text-sm text-gray-800 border-b border-gray-100 cursor-pointer hover:bg-gray-50"
                            title="Click to see details">

                            <!-- Dynamic columns -->
                            <template x-for="(key, j) in keys" :key="j">
                                <div x-text="entry.entry?.[key] || '-'"></div>
                            </template>

                            <!-- Status column -->
                            <div class="text-right">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                    x-text="entry.status">
                                </span>
                            </div>
                        </div>
                    </template>


                </div>
            </div>

<!-- Modal Overlay -->
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
        class="flex items-center gap-2 text-indigo-600 hover:text-indigo-800 text-sm font-semibold transition"
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

        </div>
    </template>
</div>



<script>
    
    function formTable(entries) {
        return {
            open: false,
            all: entries,
            keys: Object.keys(entries[0]?.entry || {}), // auto-detect keys from first entry
            entryModalOpen: false,
            selectedEntry: {},
            bgClasses: ['swpfe-row-bg-1', 'swpfe-row-bg-2', 'swpfe-row-bg-3', 'swpfe-row-bg-4'],

            showEntry(i) {
                this.selectedEntry = this.all[i];
                this.entryModalOpen = true;
            },
            markAs(status) {
                if (!this.entryModalOpen) return;
                this.selectedEntry.status = status;
                this.entryModalOpen = false;
            },
            deleteEntry() {
                if (!this.entryModalOpen) return;
                this.all = this.all.filter(e => e !== this.selectedEntry);
                this.entryModalOpen = false;
            },
            copied: false,

            copyEntryToClipboard() {
                const lines = Object.entries(this.selectedEntry.entry || {})
                    .map(([key, value]) => `${key}: ${value || '-'}`)
                    .join('\n');

                navigator.clipboard.writeText(lines).then(() => {
                    this.copied = true;
                    setTimeout(() => {
                        this.copied = false;
                    }, 2000); // revert after 2s
                }).catch(err => {
                    console.error('Copy failed:', err);
                });
            }


        }
    }



    function entriesApp() {
        return {
            grouped: {},

            async fetchEntries() {
                try {
                    const res = await fetch('http://localhost/devspark/wordpress-backend/wp-json/wpforms/entries/v1/entries');
                    const data = await res.json();
                    this.grouped = data;
                } catch (error) {
                    console.error("Failed to fetch entries:", error);
                }
            }
        }
    }
</script>