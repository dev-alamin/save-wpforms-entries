<div class="mt-4 flex justify-center items-center gap-1 text-gray-700 text-sm font-medium select-none mb-4">
	<p class="!mr-3">
		<span class="bg-blue-100 text-blue-700 px-4 py-3 rounded-full font-bold" x-text="totalEntries"></span>
	</p>
	<!-- Previous -->
	<button
		@click="prevPage"
		:disabled="currentPage === 1"
		class="w-9 h-9 flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
		aria-label="<?php esc_attr_e( 'Previous Page', 'forms-entries-manager' ); ?>">
		&lt;
	</button>

	<!-- Page Numbers -->
	<template x-for="(page, index) in visiblePages" :key="`page-${index}`">
		<template x-if="page !== '...'">
			<button
				@click="goToPage(page)"
				x-text="page"
				class="w-15 h-9 rounded-md border transition"
				:class="currentPage === page
					? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700'
					: 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
				:aria-current="currentPage === page ? 'page' : null"
				:aria-label="`<?php esc_attr_e( 'Go to page', 'forms-entries-manager' ); ?> ${page}`">
			</button>
		</template>
		<template x-if="page === '...'">
			<span class="w-9 h-9 flex items-center justify-center text-gray-400">…</span>
		</template>
	</template>

	<!-- Next -->
	<button
		@click="nextPage"
		:disabled="currentPage === totalPages"
		class="w-9 h-9 flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
		aria-label="<?php esc_attr_e( 'Next Page', 'forms-entries-manager' ); ?>">
		&gt;
	</button>
	
	<!-- Go to Page Input -->
	<div class="ml-4 flex items-center gap-1">
		<span class="text-sm text-gray-600"><?php esc_html_e( 'Go to', 'forms-entries-manager' ); ?></span>

		<input
			type="number"
			min="1"
			:max="totalPages"
			x-model.number="jumpTo"
			@keydown.enter.prevent="
				if (jumpTo >= 1 && jumpTo <= totalPages) {
					goToPage(jumpTo);
				}
			"
			class="w-16 px-2 py-1 border border-gray-300 rounded-md text-center text-sm focus:outline-none focus:ring focus:border-indigo-500"
			:placeholder="currentPage"
			aria-label="<?php esc_attr_e( 'Jump to page number', 'forms-entries-manager' ); ?>"
		/>

		<span class="text-sm text-gray-600">/ <span x-text="totalPages"></span></span>
	</div>
</div>
