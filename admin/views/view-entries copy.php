<?php
include_once __DIR__ . '/temp_data.php';
$form_groups = get_data();

$bg_classes = [
    'bg-[#FAF7F3]',
    'bg-[#FFFFFF]',
];


$bg_classes = ['swpfe-row-bg-1', 'swpfe-row-bg-2', 'swpfe-row-bg-3', 'swpfe-row-bg-4'];

?>

<div class="wrap swpfe-admin-page min-h-screen px-8 py-10 text-[15px] font-inter">
    <h1 class="!text-3xl !font-bold !text-gray-900 !mb-6">WPForms Entries Overview</h1>

    <?php foreach ($form_groups as $form_id => $form_data) :
        // Pass entries JSON safely to Alpine
        $entries_json = htmlspecialchars(json_encode($form_data['entries']), ENT_QUOTES, 'UTF-8');
    ?>
        <div x-data="formTable(<?php echo $entries_json; ?>)" class="mb-10">

            <!-- Form Overview Row -->
            <div @click="open = !open"
                class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-6 py-4 rounded-lg shadow border border-gray-300 flex items-center justify-between hover:shadow-md transition">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800"><?php echo esc_html($form_data['title']); ?></h2>
                    <p class="text-sm text-gray-600">Form ID: <?php echo $form_id; ?> | Total Entries: <span x-text="all.length"></span></p>
                </div>
                <div class="text-indigo-600 text-sm font-medium">Click to view entries ⬇️</div>
            </div>

            <!-- Entries Table (toggle) -->
            <div x-show="open" x-transition>
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200">
                    <!-- Header Row -->
                    <div class="grid grid-cols-12 items-center px-6 py-3 bg-gray-100 border-b border-gray-300 text-sm font-semibold text-gray-700 uppercase tracking-wide">
                        <div class="col-span-3">Name</div>
                        <div class="col-span-2">Email</div>
                        <div class="col-span-3">Message</div>
                        <div class="col-span-2">Submitted At</div>
                        <div class="col-span-2 text-right">Status</div>
                    </div>

                    <!-- Entry Rows -->
                    <template x-for="(entry, i) in all" :key="i">
                        <div
                            @click="showEntry(i)"
                            :class="bgClasses[i % bgClasses.length]"
                            class="grid grid-cols-12 items-start px-6 py-4 text-sm text-gray-800 border-b border-gray-100 cursor-pointer hover:bg-gray-50">
                            <div class="col-span-3 font-medium" x-text="entry.name"></div>
                            <div class="col-span-2 text-gray-500" x-text="entry.email"></div>
                            <div class="col-span-3 text-gray-600 line-clamp-2" x-text="entry.message"></div>
                            <div class="col-span-2 text-gray-400 text-xs" x-text="entry.date"></div>
                            <div class="col-span-2 text-right">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                    x-text="entry.status">
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Popup Modal -->
            <!-- Overlay -->
            <div
                x-show="entryModalOpen"
                class="fixed inset-0 flex items-center justify-center backdrop-blur-sm z-50"
                style="background-color: rgba(0, 0, 0, 0.15);"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;">
                <!-- Popup Content -->
                <div
                    class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-10 relative border-4 border-indigo-300 transform"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-90"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-90">
                    <button
                        @click="entryModalOpen = false"
                        class="absolute top-5 right-6 text-gray-600 hover:text-gray-900 text-3xl font-bold">&times;</button>
                    <h3 class="!text-3xl font-extrabold mb-6 text-indigo-800" x-text="selectedEntry.name"></h3>
                    <p class="!text-lg mb-2"><strong class="font-semibold text-gray-700">Email:</strong> <span x-text="selectedEntry.email"></span></p>
                    <p class="!text-lg mb-2"><strong class="font-semibold text-gray-700">Message:</strong> <span x-text="selectedEntry.message"></span></p>
                    <p class="!text-lg mb-2"><strong class="font-semibold text-gray-700">Submitted At:</strong> <span x-text="selectedEntry.date"></span></p>
                    <p class="!text-lg mb-4"><strong class="font-semibold text-gray-700">Status:</strong> <span x-text="selectedEntry.status"></span></p>

                    <div class="mt-8 flex gap-6 justify-end">
                        <button
                            @click="markAs('read')"
                            class="px-6 py-3 bg-green-600 text-white rounded-lg text-lg font-semibold hover:bg-green-700 transition"
                            :disabled="selectedEntry.status === 'read'">Mark as Read</button>
                        <button
                            @click="markAs('unread')"
                            class="px-6 py-3 bg-yellow-500 text-white rounded-lg text-lg font-semibold hover:bg-yellow-600 transition"
                            :disabled="selectedEntry.status === 'unread'">Mark as Unread</button>
                        <button
                            @click="deleteEntry()"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg text-lg font-semibold hover:bg-red-700 transition">Delete</button>
                    </div>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</div>


<script>
    function formTable(entries) {
        return {
            open: false,
            all: entries,
            bgClasses: ['swpfe-row-bg-1', 'swpfe-row-bg-2', 'swpfe-row-bg-3', 'swpfe-row-bg-4'],
            entryModalOpen: false,
            selectedEntry: {},

            showEntry(i) {
                this.selectedEntry = this.all[i];
                this.entryModalOpen = true;
            },
            markAs(status) {
                if (!this.entryModalOpen) return;
                this.selectedEntry.status = status;
                // Update entry in all array for reactivity
                this.all = this.all.map(e => e === this.selectedEntry ? {
                    ...e,
                    status
                } : e);
                this.entryModalOpen = false;
            },
            deleteEntry() {
                if (!this.entryModalOpen) return;
                this.all = this.all.filter(e => e !== this.selectedEntry);
                this.entryModalOpen = false;
            }
        }
    }


    function entriesApp(entriesData) {
        return {
            allEntries: entriesData,
            entries: [],
            perPage: 5,
            currentPage: 1,
            sortKey: 'date',
            sortAsc: false,

            init() {
                this.sortEntries();
            },

            sortEntries(key = null) {
                if (key) {
                    if (this.sortKey === key) {
                        this.sortAsc = !this.sortAsc;
                    } else {
                        this.sortKey = key;
                        this.sortAsc = true;
                    }
                }
                this.entries = this.allEntries.slice().sort((a, b) => {
                    let valA = a[this.sortKey]?.toLowerCase?.() || a[this.sortKey];
                    let valB = b[this.sortKey]?.toLowerCase?.() || b[this.sortKey];

                    if (valA < valB) return this.sortAsc ? -1 : 1;
                    if (valA > valB) return this.sortAsc ? 1 : -1;
                    return 0;
                });
            },

            paginatedEntries() {
                let start = (this.currentPage - 1) * this.perPage;
                return this.entries.slice(start, start + this.perPage);
            },

            pageCount() {
                return Math.ceil(this.entries.length / this.perPage);
            },

            setPage(page) {
                this.currentPage = page;
            }
        }
    }
</script>