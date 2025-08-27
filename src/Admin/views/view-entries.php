<?php
defined( 'ABSPATH' ) || exit;

do_action( 'fem_before_entries_ui' );
?>

<div
	x-data="toastHandler()"
	x-show="visible"
	x-transition
	x-init="$watch('visible', v => v && setTimeout(() => visible = false, 3000))"
	class="fixed top-10 right-4 z-50 px-4 py-2 rounded shadow-lg text-white d-none z-[100]"
	:class="{
		'bg-[#4f46e5]': type === 'success',
		'bg-red-600': type === 'error',
		'bg-yellow-500': type === 'warning',
		'bg-blue-600': type === 'info'
	}"
	x-text="message"></div>

<div
	x-data="entriesApp()"
	x-init="fetchForms()"
	class="wrap fem-admin-page min-h-screen !m-auto px-8 py-10 text-[15px] font-inter"
	role="main"
	aria-label="<?php echo esc_attr__( 'Forms Entries Manager Overview', 'forms-entries-manager' ); ?>">

	<?php do_action( 'fem_before_entries_ui_header' ); ?>
	<!-- Header -->
	<div class="mb-8 bg-slate-700 text-white px-4 py-2 rounded-lg">
		<h1 class="!text-3xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
			📋 <span><?php esc_html_e( 'Forms Entries Manager Overview', 'forms-entries-manager' ); ?></span>
		</h1>
		<p class="text-gray-200 !text-[15px] leading-relaxed !m-0 !mt-2">
			<?php
			esc_html_e( 'Browse and manage form entries submitted by users. Click on a form to view its submissions, mark entries as read/unread, or delete them as needed.', 'forms-entries-manager' );
			?>
		</p>
	</div>

	<!-- Migration Prompt -->
	<?php

	use App\AdvancedEntryManager\Utility\Helper;

	if ( ! Helper::table_exists( 'wpforms_db' ) && ! Helper::get_option( 'migration_complete' ) ) :
		?>
		<div
			x-data="{ showMigrationNotice: true }"
			x-show="showMigrationNotice"
			x-transition
			class="mb-6 border border-yellow-400 bg-yellow-50 text-yellow-800 rounded-lg p-4 shadow-sm">

			<div class="flex items-center justify-between gap-4">
				<div class="flex-1">
					<h2 class="text-lg font-semibold mb-1 flex items-center gap-2">
						<!-- Material Icon: inventory -->
						<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-700" viewBox="0 0 24 24" fill="currentColor">
							<path d="M20 2H4C2.9 2 2 2.9 2 4v4c0 1.1.9 2 2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 8H4V4h16v6z" />
						</svg>
						<span x-text="femMigrationNotice.title"></span>
					</h2>
					<p class="text-sm" x-html="femMigrationNotice.message"></p>
				</div>

				<div class="flex gap-2">
					<button
						@click="window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=fem-migration' ) ); ?>'"
						class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition">
						<!-- Material Icon: rocket_launch -->
						<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
							<path d="M20 2c-2 0-3.94.79-5.38 2.21l-1.44 1.44 4.24 4.24 1.44-1.44C21.21 8.94 22 7 22 5c0-1.1-.9-2-2-2zm-8.49 4.91l-7.07 7.07c-.28.28-.49.63-.61 1.01l-1.82 5.55c-.18.56.37 1.1.93.93l5.55-1.82c.38-.12.73-.33 1.01-.61l7.07-7.07-4.06-4.06zM5 20h4v2H5v-2z" />
						</svg>
						<span x-text="femMigrationNotice.start"></span>
					</button>

					<button
						@click="showMigrationNotice = false"
						class="text-sm text-gray-600 hover:text-gray-900 transition"
						:aria-label="femMigrationNotice.dismissAlt">
						<!-- Material Icon: close -->
						<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
							<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
						</svg>
					</button>
				</div>
			</div>
		</div>

	<?php endif; ?>

	<!-- Loop Over Forms -->
	<template x-for="form in forms" :key="form.form_id">
		<div x-data="formTable(form)" class="mb-10" x-cloak :key="domKey">

			<!-- Clickable Form Header -->
			<div
				@click="toggleOpen()"
				class="cursor-pointer bg-gradient-to-r from-indigo-50 via-purple-100 to-pink-50 px-4 sm:px-4 sm:py-4 rounded-xl shadow border border-gray-300 flex flex-col sm:flex-row items-start sm:items-center justify-between hover:shadow-lg transition duration-200 group"
				role="button"
				tabindex="0"
				@keydown.enter.prevent="toggleOpen()"
				aria-expanded="false"
				:aria-expanded="open.toString()"
				:aria-controls="'entries-table-' + form.form_id">
				<div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4 w-full sm:w-auto">
					<div class="shrink-0 bg-indigo-100 text-indigo-600 rounded-xl p-2 mb-2 hide-on-mobile" aria-hidden="true">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path stroke-linecap="round" stroke-linejoin="round"
								d="M9 17v-2a4 4 0 014-4h7M9 17a4 4 0 01-4-4V7a4 4 0 014-4h11a4 4 0 014 4v6a4 4 0 01-4 4H9z" />
						</svg>
					</div>

					<div>
						<h2 class="text-xl sm:text-2xl font-semibold text-gray-800 !m-2" x-text="form.form_title"></h2>
						<p class="text-xs sm:text-sm text-gray-600 font-medium flex items-center gap-2 mt-1 !m-0">
							<span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-bold">
								<?php esc_html_e( 'ID:', 'forms-entries-manager' ); ?> <span x-text="form.form_id"></span>
							</span>

							<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">
								<?php esc_html_e( 'Entries:', 'forms-entries-manager' ); ?>
								<span
									x-text="formatNumber(form.entry_count)"
									:title="formatFullNumber(form.entry_count)"
									class="cursor-help"
									aria-label="<?php esc_attr_e( 'Total number of entries', 'forms-entries-manager' ); ?>">
								</span>
							</span>

							<span
								class="px-2 py-0.5 rounded-full font-bold"
								:class="{ 'bg-orange-100 text-orange-700': form.number_unread > 0, 'bg-gray-100 text-gray-500': form.number_unread === 0 }"
								x-show="form.number_unread > 0"
								title="<?php esc_attr_e( 'Number of unread entries', 'forms-entries-manager' ); ?>"
								aria-label="<?php esc_attr_e( 'Total number of unread entries', 'forms-entries-manager' ); ?>">
								<?php esc_html_e( 'Unread:', 'forms-entries-manager' ); ?> <span x-text="form.number_unread"></span>
							</span>
						</p>
					</div>
				</div>

				<div class="text-sm font-medium flex items-center gap-1 px-2 py-1 rounded-md text-indigo-700 transition cursor-pointer select-none mb-4 sm:mb-0 sm:mt-0 w-full sm:w-auto justify-center sm:justify-start">
					<span x-show="!open" class="group-hover:underline" aria-hidden="true">
						<?php esc_html_e( 'Click to view entries', 'forms-entries-manager' ); ?>
					</span>
					<span x-show="open" class="group-hover:underline" aria-hidden="true">
						<?php esc_html_e( 'Hide entries', 'forms-entries-manager' ); ?>
					</span>
					<svg :class="open ? 'rotate-180' : ''"
						class="w-4 h-4 transition-transform duration-300"
						fill="none"
						stroke="currentColor"
						viewBox="0 0 24 24"
						stroke-width="2"
						aria-hidden="true"
						focusable="false">
						<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
					</svg>
				</div>
			</div>

			<!-- Entries Table -->
			<div
				x-show="open"
				x-transition
				id="entries-table-"
				:id="'entries-table-' + form.form_id"
				role="region"
				aria-live="polite"
				aria-label="<?php esc_attr_e( 'Entries table for form', 'forms-entries-manager' ); ?>"
				class="overflow-x-auto">
				<div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200 min-w-[600px]">
					<?php
					require __DIR__ . '/table/filter-section.php';
					$custom_column = Helper::get_option( 'cusom_form_columns_settings', array() );
					?>

					<!-- Header Row -->
					<div
						class="items-center px-4 py-3 bg-gray-100 border-b border-gray-300 text-sm font-semibold text-gray-700 uppercase tracking-wide"
						:style="'display: grid; grid-template-columns:' + gridStyle"
						role="row">
						<div role="columnheader">
							<input type="checkbox" id="bulk_action_main" @change="toggleSelectAll($event)" class="cursor-pointer" />
						</div>

						<div role="columnheader"><?php esc_html_e( 'Email', 'forms-entries-manager' ); ?></div>

						<!-- Custom Columns -->
						<template x-for="field in chosenFields" :key="field.key">
							<div role="columnheader" class="text-center">
								<span x-text="field.label"></span>
							</div>
						</template>

						<div role="columnheader" class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByDate">
							<span><?php esc_html_e( 'Date', 'forms-entries-manager' ); ?></span>
							<span x-text="sortAsc ? '⬆️' : '⬇️'"></span>
						</div>
						<div role="columnheader" class="text-center cursor-pointer select-none flex items-center justify-center gap-1" @click="sortByStatus">
							<span><?php esc_html_e( 'Status', 'forms-entries-manager' ); ?></span>
							<span x-text="sortAscStatus ? '⬆️' : '⬇️'"></span>
						</div>
						<div role="columnheader" class="text-right"><?php esc_html_e( 'Actions', 'forms-entries-manager' ); ?></div>
					</div>

					<div class="relative min-h-60">
						<template x-show="!loading" x-for="(entry, i) in entries" :key="entry.id">
							<div
								:class="[
				bgClasses[i % bgClasses.length],
				entry.status === 'unread' ? 'font-bold' : 'font-normal',
				entry.is_spam == 1 ? 'bg-red-50 opacity-50' : '',
			]"
								class="py-2 grid items-center px-4 text-sm text-gray-800 border-b border-gray-100 hover:bg-gray-50"
								:style="'grid-template-columns:' + gridStyle"
								role="row">
								<input type="checkbox" :value="entry.id" x-model="bulkSelected" @click="handleCheckbox($event, entry.id)" class="cursor-pointer" />

								<div
									class="cursor-pointer truncate flex items-center gap-2"
									title="<?php echo esc_attr__( 'Click for details', 'forms-entries-manager' ); ?>"
									@click="showEntry(i)">
									<span x-text="entry.name + ' - ' || 'no name found'"></span>
									<span x-text="entry.email || '-'"></span>
									<span class="text-xs text-gray-400" x-text="'#' + entry.id"></span>
								</div>

								<!-- Custom Columns -->
								<template x-for="field in chosenFields" :key="field.key">
									<div class="text-center truncate" x-text="getEntryData(entry.entry, field.key)"></div>
								</template>

								<div class="text-center whitespace-nowrap" x-text="timeAgo(entry.date)" :title="entry.date"></div>
								<div class="text-center">
									<span
										class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
										:class="entry.status === 'unread' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'"
										x-text="entry.status === 'unread' ? '<?php echo esc_html__( 'Unread', 'forms-entries-manager' ); ?>' : '<?php echo esc_html__( 'Read', 'forms-entries-manager' ); ?>'"></span>
								</div>
								<?php require __DIR__ . '/table/action-column.php'; ?>
							</div>
						</template>

						<!-- Loader Overlay -->
						<div
							x-show="loading"
							x-transition
							class="absolute inset-0 z-50 flex items-center justify-center bg-white/60 backdrop-blur-sm">
							<lottie-player
								src="<?php echo esc_url( FEM_URL . 'assets/admin/lottie/loading.json' ); ?>"
								background="transparent"
								speed="1"
								class="w-auto h-auto"
								loop
								autoplay>
							</lottie-player>
						</div>

					</div>

					<!-- Pagination Controls -->
					<?php require __DIR__ . '/table/pagination.php'; ?>
				</div>
			</div>

			<!-- Entries Table -->
			<!-- Entry Modal -->
			<?php require __DIR__ . '/modal.php'; ?>
		</div>
	</template>

	<!-- Before loading the forms, show a loading state -->
	<div x-show="loading" class="flex items-center justify-center min-h-60">
		<lottie-player
			src="<?php echo esc_url( FEM_URL . 'assets/admin/lottie/loading.json' ); ?>"
			background="transparent"
			speed="1"
			class="w-auto h-auto"
			loop
			autoplay>
		</lottie-player>
	</div>

	<!-- If no forms available -->
	<?php require __DIR__ . '/empty-page.php'; ?>

	<!-- Powered by Message  -->
	<div class="mt-8 border-t border-slate-300 pt-4 text-center text-gray-500 text-sm select-none flex items-center justify-center gap-2">
		<svg
			class="w-5 h-5 text-indigo-500"
			xmlns="http://www.w3.org/2000/svg"
			fill="none"
			viewBox="0 0 24 24"
			stroke="currentColor"
			stroke-width="2"
			aria-hidden="true"
			focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
		</svg>
		<span>
			<?php esc_html_e( 'Powered by', 'forms-entries-manager' ); ?> <a href="https://entriesmanager.com/" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-700 transition"><?php echo esc_html( 'Advanced Entries Manager' ); ?></a>
		</span>
	</div>
	<!-- Powered by message ends -->

</div>
<?php do_action( 'fem_after_entries_ui' ); ?>