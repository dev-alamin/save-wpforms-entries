<div class="flex justify-end items-center space-x-2">
	<!-- Toggle Read/Unread -->
	<button @click="toggleRead(i)" title="<?php esc_attr_e( 'Mark as Read/Unread', 'forms-entries-manager' ); ?>" class=" mr-4 cursor-pointer">
		<!-- Unread Icon -->
		<svg x-show="entry.status === 'unread'" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h404q-4 20-4 40t4 40H160l320 200 146-91q14 13 30.5 22.5T691-572L480-440 160-640v400h640v-324q23-5 43-14t37-22v360q0 33-23.5 56.5T800-160H160Zm0-560v480-480Zm600 80q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35Z" />
		</svg>

		<!-- Read Icon -->
		<svg x-show="entry.status === 'read'" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M638-80 468-250l56-56 114 114 226-226 56 56L638-80ZM480-520l320-200H160l320 200Zm0 80L160-640v400h206l80 80H160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v174l-80 80v-174L480-440Z" />
		</svg>
	</button>

	<!-- Toggle Favorite -->
	<button @click="toggleFavorite(i)" title="<?php esc_attr_e( 'Mark as Favorite', 'forms-entries-manager' ); ?>" class=" mr-4 cursor-pointer">
		<!-- Not Favorite Icon -->
		<svg x-show="entry.is_favorite" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="m480-120-58-52q-101-91-167-157T150-447.5Q111-500 95.5-544T80-634q0-94 63-157t157-63q52 0 99 22t81 62q34-40 81-62t99-22q94 0 157 63t63 157q0 46-15.5 90T810-447.5Q771-395 705-329T538-172l-58 52Z" />
		</svg>

		<!-- Favorite Icon -->
		<svg x-show="!entry.is_favorite" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M718-313 604-426l57-56 57 56 141-141 57 56-198 198ZM440-501Zm0 381L313-234q-72-65-123.5-116t-85-96q-33.5-45-49-87T40-621q0-94 63-156.5T260-840q52 0 99 22t81 62q34-40 81-62t99-22q81 0 136 45.5T831-680h-85q-18-40-53-60t-73-20q-51 0-88 27.5T463-660h-46q-31-45-70.5-72.5T260-760q-57 0-98.5 39.5T120-621q0 33 14 67t50 78.5q36 44.5 98 104T440-228q26-23 61-53t56-50l9 9 19.5 19.5L605-283l9 9q-22 20-56 49.5T498-172l-58 52Z" />
		</svg>
	</button>

	<!-- Notes Indicator -->
	<button
		@click="showEntry(i)"
		title=""
		class="mr-2 text-indigo-600 hover:text-indigo-800 transition cursor-pointer"
		:title="entry.note && entry.note.trim() !== '' 
			? '<?php echo esc_js( __( 'Edit Note', 'forms-entries-manager' ) ); ?>' 
			: '<?php echo esc_js( __( 'Add Note', 'forms-entries-manager' ) ); ?>'"
	>
		<!-- Filled Note Icon (if has note) -->
		<template x-if="entry.note && entry.note.trim() !== ''">
			<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="#4f46e5">
				<path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/>
			</svg>
		</template>

		<!-- Outline Note Icon (if no note) -->
		<template x-if="!entry.note || entry.note.trim() === ''">
			<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
					<path d="M440-240h80v-120h120v-80H520v-120h-80v120H320v80h120v120ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/>
			</svg>
		</template>
	</button>

	<!-- Sync to Google Sheet -->
	<button @click="toggleGoogleSheetSync(i)" title="<?php esc_attr_e( 'Sync to Google Sheet', 'forms-entries-manager' ); ?>" class="mr-4 cursor-pointer">
		<!-- Not Synced -->
		<svg x-show="entry.synced_to_gsheet" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M160-160v-80h110l-16-14q-52-46-73-105t-21-119q0-111 66.5-197.5T400-790v84q-72 26-116 88.5T240-478q0 45 17 87.5t53 78.5l10 10v-98h80v240H160Zm400-10v-84q72-26 116-88.5T720-482q0-45-17-87.5T650-648l-10-10v98h-80v-240h240v80H690l16 14q49 49 71.5 106.5T800-482q0 111-66.5 197.5T560-170Z" />
		</svg>

		<!-- Synced -->
		<svg x-show="!entry.synced_to_gsheet" xmlns="http://www.w3.org/2000/svg" height="24px"
			viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M792-56 643-205q-19 11-39.5 20T560-170v-84q6-2 12-4.5t12-5.5L264-584q-11 25-17.5 51.5T240-478q0 45 17 87.5t53 78.5l10 10v-98h80v240H160v-80h110l-16-14q-49-49-71.5-106.5T160-478q0-45 11.5-86.5T205-643L56-792l57-57 736 736-57 57Zm-35-263-60-60q11-24 17-50t6-53q0-45-17-87.5T650-648l-10-10v98h-80v-240h240v80H690l16 14q49 49 71.5 106.5T800-482q0 45-11.5 85.5T757-319Z" />
		</svg>
	</button>

	<!-- Print -->
	<button class="cursor-pointer mr-4" @click="printEntry(i)" title="<?php esc_attr_e( 'Print Entry', 'forms-entries-manager' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M640-640v-120H320v120h-80v-200h480v200h-80Zm-480 80h640-640Zm560 100q17 0 28.5-11.5T760-500q0-17-11.5-28.5T720-540q-17 0-28.5 11.5T680-500q0 17 11.5 28.5T720-460Zm-80 260v-160H320v160h320Zm80 80H240v-160H80v-240q0-51 35-85.5t85-34.5h560q51 0 85.5 34.5T880-520v240H720v160Zm80-240v-160q0-17-11.5-28.5T760-560H200q-17 0-28.5 11.5T160-520v160h80v-80h480v80h80Z" />
		</svg>
	</button>

	<!-- Export CSV -->
	<button class="cursor-pointer mr-4"
		@click="exportSingleEntry(entry)"
		title="<?php esc_attr_e( 'Export as CSV', 'forms-entries-manager' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24" fill="#4f46e5">
			<path d="M5 20q-.825 0-1.412-.587Q3 18.825 3 18V6q0-.825.588-1.412Q4.175 4 5 4h14q.825 0 1.413.588Q21 5.175 21 6v12q0 .825-.587 1.413Q19.825 20 19 20Zm0-2h14V6H5v12Zm7-1-4-4h3V9h2v4h3Zm0-6Z" />
		</svg>
	</button>

	<!-- View Entry Button -->
	<button
		class="cursor-pointer text-gray-600 hover:text-indigo-600 transition mr-4"
		@click="showEntry(i)"
		title="<?php esc_attr_e( 'View Details', 'forms-entries-manager' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#4f46e5">
			<path d="M80-560v-320h320v320H80Zm80-80h160v-160H160v160ZM80-80v-320h320v320H80Zm80-80h160v-160H160v160Zm400-400v-320h320v320H560Zm80-80h160v-160H640v160ZM560-80v-320h320v320H560Zm80-80h160v-160H640v160ZM320-640Zm0 320Zm320-320Zm0 320Z"/>
		</svg>
	</button>

</div>