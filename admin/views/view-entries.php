<div x-data="entriesApp()" x-init="fetchEntries()" class="wrap swpfe-admin-page min-h-screen px-8 py-10 text-[15px] font-inter">
    <div class="mb-8 bg-slate-700 text-wite px-4 py-2 rounded-lg">
        <h1 class="!text-4xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
            📋 <span>WPForms Entries Overview</span>
        </h1>
        <p class="text-gray-200 !text-[15px] leading-relaxed">
            Browse and manage form entries submitted by users. Click on a form to view its submissions, mark entries as read/unread, or delete them as needed.
        </p>
    </div>

    <template x-for="(formEntries, formId) in grouped" :key="formId">
        <div x-data="formTable(formEntries)" class="mb-10">
            <!-- Form Overview Row -->
            <div
                @click="open = !open"
                class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-6 rounded-xl shadow border border-gray-300 flex items-center justify-between hover:shadow-lg transition duration-200 group">

                <!-- Left side: Icon + Info -->
                <div class="flex items-center gap-4">
                    <!-- Icon -->
                    <div class="shrink-0 bg-indigo-100 text-indigo-600 rounded-xl p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17v-2a4 4 0 014-4h7M9 17a4 4 0 01-4-4V7a4 4 0 014-4h11a4 4 0 014 4v6a4 4 0 01-4 4H9z" />
                        </svg>
                    </div>

                    <!-- Text Content -->
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800" x-text="all[0]?.form_title || 'Form Title'"></h2>
                        <p class="text-sm text-gray-600 font-medium">
                            🆔 <strong>Form ID:</strong> <span x-text="formId"></span> &nbsp; | &nbsp;
                            📌 <strong>Total Entries:</strong> <span x-text="all.length"></span>
                        </p>
                    </div>
                </div>

                <!-- Right side: Toggle caret -->
                <div class="text-indigo-600 text-sm font-medium flex items-center gap-1">
                    <span x-show="!open" class="group-hover:underline">Click to view entries</span>
                    <span x-show="open" class="group-hover:underline">Hide entries</span>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform duration-300" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
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
                        :style="`display: grid; grid-template-columns: repeat(${keys.length}, minmax(0,1fr)) 150px 100px;`">
                        <template x-for="(key, i) in keys" :key="i">
                            <div class="capitalize truncate" :title="key"
                                x-text="key.split(' ').slice(0, 4).join(' ') + (key.split(' ').length > 4 ? '…' : '')">
                            </div>
                        </template>
                        <div class="text-center uppercase" style="min-width: 150px;">Date</div>
                        <div class="text-right uppercase">Status</div>
                    </div>

                    <!-- Entries Rows -->
                    <template x-for="(entry, i) in paginatedEntries" :key="i">
                        <div
                            @click="showEntry(i)"
                            :class="bgClasses[i % bgClasses.length]"
                            class="items-start px-6 py-4 text-sm text-gray-800 border-b border-gray-100 cursor-pointer hover:bg-gray-50"
                            :style="`display: grid; grid-template-columns: repeat(${keys.length}, minmax(0,1fr)) 150px 100px;`"
                            title="Click to see details">
                            <!-- Dynamic columns -->
                            <template x-for="(key, j) in keys" :key="j">
                                <div x-text="entry.entry?.[key] || '-'"></div>
                            </template>

                            <!-- Date column -->
                            <div class="text-center whitespace-nowrap" x-text="entry.date || '-'"></div>

                            <!-- Status column -->
                            <div class="text-right">
                                <span
                                    class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                                    :class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
                                    x-text="entry.status"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Pagination Controls -->
                    <div class="mt-4 flex justify-center items-center gap-1 text-gray-700 text-sm font-medium select-none mb-4">
                        <!-- Previous Button -->
                        <button
                            @click="prevPage"
                            :disabled="currentPage === 1"
                            class="w-9 h-9 flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            aria-label="Previous Page">
                            &lt;
                        </button>

                        <!-- Page Numbers -->
                        <template x-for="page in totalPages" :key="page">
                            <button
                                @click="goToPage(page)"
                                x-text="page"
                                :class="[
                'w-9 h-9 rounded-md border transition',
                currentPage === page
                    ? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700'
                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'
            ]"
                                :aria-current="currentPage === page ? 'page' : null"
                                :aria-label="`Go to page ${page}`">
                            </button>
                        </template>

                        <!-- Next Button -->
                        <button
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="w-9 h-9 flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            aria-label="Next Page">
                            &gt;
                        </button>
                    </div>

                </div>
            </div>
            <!-- Modal Overlay -->
            <?php include __DIR__ . '/modal.php'; // Popup Modal 
            ?>
        </div>
    </template>

    <div
        x-show="Object.keys(grouped).length === 0"
        class="flex flex-col items-center justify-center mt-20 space-y-6 text-gray-600">
        <!-- Lottie JSON animation (optional, needs lottie-player script) -->
        <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
        <lottie-player
            src="https://assets5.lottiefiles.com/packages/lf20_qp1q7mct.json"
            background="transparent"
            speed="1"
            style="width: 320px; height: 320px"
            loop
            autoplay>
        </lottie-player>

        <h2 class="!text-2xl sm:text-3xl !font-extrabold text-gray-800">No Entries Found</h2>

        <p class="!text-base !sm:text-lg text-gray-500 max-w-md text-center">
            Looks like this form hasn't received any submissions yet.<br class="hidden sm:block">
            Sit back and relax — we’ll show the entries here as soon as they arrive! 📨
        </p>
    </div>

</div>