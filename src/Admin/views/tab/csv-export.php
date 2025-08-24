<div x-data="exportSettings()" x-init="init();
	$watch('selectedFormId', value => fetchFormFields());
" class="bg-white border border-indigo-100 shadow p-6 space-y-6 rounded-lg">

	<!-- Form Selector -->
	<div>
		<label for="femexport_form" class="block text-sm font-semibold text-gray-700 mb-1">
			<?php esc_html_e( 'Select Form', 'forms-entries-manager' ); ?>
		</label>
		<select id="femexport_form"
			name="femexport_form"
			x-model="selectedFormId"
			class="!w-full !px-4 !py-2 !border !border-gray-300 !rounded-md !shadow-sm !text-sm !focus:ring !focus:ring-indigo-200 !focus:border-indigo-500">
			<option value=""><?php esc_html_e( '-- Select a Form --', 'forms-entries-manager' ); ?></option>
			<template x-for="form in forms" :key="form.form_id">
				<option :value="form.form_id" x-text="form.form_title + ' (' + form.entry_count + ')'"></option>
			</template>
		</select>
	</div>

	<!-- Date Range Inputs -->
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
		<div>
			<label for="femexport_date_from" class="block text-sm font-semibold text-gray-700 mb-1">
				<?php esc_html_e( 'Date From', 'forms-entries-manager' ); ?>
			</label>
			<input type="date" id="femexport_date_from" name="femexport_date_from"
				class="!w-full !px-4 !py-2 !border !border-gray-300 !rounded-md !shadow-sm !text-sm !focus:ring !focus:ring-indigo-200 !focus:border-indigo-500">
		</div>
		<div>
			<label for="femexport_date_to" class="block text-sm font-semibold text-gray-700 mb-1">
				<?php esc_html_e( 'Date To', 'forms-entries-manager' ); ?>
			</label>
			<input type="date" id="femexport_date_to" name="femexport_date_to"
				class="!w-full !px-4 !py-2 !border !border-gray-300 !rounded-md !shadow-sm !text-sm !focus:ring !focus:ring-indigo-200 !focus:border-indigo-500">
		</div>
	</div>

	<!-- Field Exclusions -->
	<div x-show="fields.length > 0">
		<label class="block text-sm font-semibold text-gray-700 mb-2">
			<?php esc_html_e( 'Exclude Columns (Optional)', 'forms-entries-manager' ); ?>
		</label>
		<div class="flex flex-wrap gap-4">
			<template x-for="(field, index) in fields" :key="index">
				<label class="inline-flex items-center gap-2 text-sm text-gray-700">
					<input type="checkbox" :value="field" x-model="excludedFields" class="!rounded !text-indigo-600 !border-gray-300 !focus:ring-indigo-500">
					<span x-text="field"></span>
				</label>
			</template>
		</div>
	</div>

	<div class="pt-4 space-y-2">
		<button
			type="button"
			class="w-full md:w-auto px-6 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 !important"
			:disabled="(!selectedFormId || isExporting) && !isOntheGoExport"
			@click="exportAllBatchesAsync">
			<template x-if="isOntheGoExport">
				<span>
					<?php esc_html_e( 'Start Exporting', 'forms-entries-manager' ); ?>
				</span>
			</template>
			<template x-if="!isExporting && !isOntheGoExport">
				<span><?php esc_html_e( 'Start Exporting', 'forms-entries-manager' ); ?></span>
			</template>

			<template x-if="isExporting && !isOntheGoExport">
				<span>Exporting... <span x-text="exportProgress.toFixed(1) + '%'"></span></span>
			</template>
		</button>

		<button
			type="button"
			class="w-full md:w-auto px-6 py-2 font-semibold rounded-md transition focus:outline-none focus:ring-2 focus:ring-offset-2"
			x-show="(isExporting || isExportComplete) && !isOntheGoExport"
			@click="isExportComplete ? handleDownload() : showExportProgress()"
			:class="{
			'border border-indigo-600 text-indigo-600 hover:bg-indigo-100 focus:ring-indigo-500': !isExportComplete,
			'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500': isExportComplete
		}">
			<span x-text="isExportComplete ? '<?php esc_html_e( 'Download Export File', 'forms-entries-manager' ); ?>' : '<?php esc_html_e( 'See Export Progress', 'forms-entries-manager' ); ?>'"></span>
		</button>

		<button
			type="button"
			class="w-full md:w-auto px-6 py-2 border border-red-600 text-red-600 font-semibold rounded-md hover:bg-red-100 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
			x-show="isExportComplete && !isOntheGoExport"
			@click="deleteExportFile">
			<?php esc_html_e( 'Delete Export File', 'forms-entries-manager' ); ?>
		</button>
	</div>

	<div
		x-show="showProgressModal"
		x-cloak
		class="fixed inset-0 flex items-center justify-center backdrop-blur-sm bg-black/30 z-50"
		x-transition:enter="transition ease-out duration-300"
		x-transition:enter-start="opacity-0"
		x-transition:enter-end="opacity-100"
		x-transition:leave="transition ease-in duration-200"
		x-transition:leave-start="opacity-100"
		x-transition:leave-end="opacity-0">

		<div @click.away="closeProgressModal()" class="bg-white rounded-lg shadow-xl p-6 w-96 max-w-full">
			<div class="flex justify-between items-center mb-4">
				<h3 class="text-lg font-semibold text-gray-800"><?php esc_html_e( 'Export Progress', 'forms-entries-manager' ); ?></h3>
				<button @click="closeProgressModal()" class="text-gray-400 hover:text-gray-600">
					<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
					</svg>
				</button>
			</div>

			<div class="mb-4">
				<p class="text-sm text-gray-600 mb-2"><?php esc_html_e( 'The export is currently in progress. You can close this window and it will continue in the background.', 'forms-entries-manager' ); ?></p>

				<div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
					<div
						class="bg-indigo-600 h-2.5 rounded-full"
						:style="`width: ${exportProgress}%`"></div>
				</div>

				<div class="mt-2 text-sm font-medium text-gray-700">
					<span x-text="exportProgress.toFixed(1) + '%'"></span>
					<span x-show="exportProgress > 0" class="float-right text-gray-500">
						<span x-text="processedCount"></span> / <span x-text="totalEntries"></span>
						<?php esc_html_e( 'Entries Processed', 'forms-entries-manager' ); ?>
					</span>
				</div>
			</div>

			<div class="flex justify-end space-x-2">
				<button @click="closeProgressModal()" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
					<?php esc_html_e( 'Close', 'forms-entries-manager' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>