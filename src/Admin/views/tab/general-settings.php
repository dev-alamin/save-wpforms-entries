<?php
use App\AdvancedEntryManager\Utility\Helper;
?>
<div
	x-data="customColumnsForm()"
	x-init="init()"
	class="space-y-6">

	<!-- Existing Pagination Settings block (unmodified) -->
	<div class="bg-white border border-indigo-100 shadow p-6">
		<h3 class="text-xl font-bold text-indigo-700 mb-3"><?php esc_html_e( '📄 Pagination Settings', 'forms-entries-manager' ); ?></h3>
		<p class="text-sm text-gray-600 mb-4">
			<?php esc_html_e( 'Set how many entries are shown per page in the admin UI.', 'forms-entries-manager' ); ?>
		</p>

		<div>
			<label for="fem_entries_per_page" class="block text-sm font-medium text-gray-700 mb-1">
				<?php esc_html_e( 'Entries per Page', 'forms-entries-manager' ); ?>
			</label>
			<input type="number" name="fem_entries_per_page" id="fem_entries_per_page"
				value="<?php echo esc_attr( $per_page ); ?>" min="5" max="200"
				class="!w-full !px-4 !py-2 !border !border-gray-300 !rounded-md !shadow-sm !text-sm !focus:ring !focus:ring-indigo-200 !focus:border-indigo-500">
		</div>
	</div>
	<?php

	$custom_column = Helper::get_option( 'cusom_form_columns_settings', array() );

	echo '<pre>';
	print_r( json_decode( $custom_column ) );
	echo '</pre>';
	?>
	<!-- New block for Custom Columns -->
	<div class="bg-white border border-indigo-100 shadow p-6">
		<h3 class="text-xl font-bold text-indigo-700 mb-3">
			<?php esc_html_e( '📊 Table Columns', 'forms-entries-manager' ); ?>
		</h3>
		<p class="text-sm text-gray-600 mb-4">
			<?php esc_html_e( 'Select which form fields you want to display as columns in the entries table.', 'forms-entries-manager' ); ?>
		</p>

		<!-- Render all forms with their fields -->
		<template x-for="form in forms" :key="form.form_id">
			<div class="mb-6 border border-gray-200 rounded-md p-4">
				<!-- Form Title -->
				<h4 class="font-semibold text-indigo-700 mb-2" x-text="form.form_title"></h4>

				<!-- Field Checkboxes -->
				<div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 p-4 rounded-md">
					<template x-for="field in allFields[form.form_id]" :key="field.key">
						<div class="flex items-center">
							
							<input
								type="checkbox"
								:value="field.key"
								x-model="selectedColumns[form.form_id]"
								:id="'field-' + form.form_id + '-' + field.key"
								:name="'fem_custom_columns[' + form.form_id + '][]'"
								class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" />
							<label
								:for="'field-' + form.form_id + '-' + field.key"
								class="ml-2 block text-sm text-gray-900"
								x-text="field.label">
							</label>
						</div>
					</template>

					<p x-show="!allFields[form.form_id]" class="text-gray-500 text-xs mt-1">
						<?php esc_html_e( 'Loading fields...', 'forms-entries-manager' ); ?>
					</p>
				</div>
			</div>
		</template>
	</div>