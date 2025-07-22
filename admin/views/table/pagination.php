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