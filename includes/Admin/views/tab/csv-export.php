<div x-data="exportSettings()" x-init="
    fetchForms();
    $watch('selectedFormId', value => fetchFormFields());
" class="bg-white border border-indigo-100 shadow p-6 space-y-6">

    <!-- Form Dropdown -->
    <label for="swpfe_export_form" class="block text-sm font-medium text-gray-700 mb-1">
        <?php esc_html_e('Select Form', 'advanced-entries-manager-for-wpforms'); ?>
    </label>
    <select id="swpfe_export_form"
            x-model="selectedFormId"
            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
        <option value=""><?php esc_html_e('-- Select a Form --', 'advanced-entries-manager-for-wpforms'); ?></option>
        <template x-for="form in forms" :key="form.form_id">
            <option :value="form.form_id" x-text="form.form_title + ' (' + form.entry_count + ')'"></option>
        </template>
    </select>

    <!-- Date Range -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div>
            <label for="swpfe_export_date_from" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('Date From', 'advanced-entries-manager-for-wpforms'); ?>
            </label>
            <input type="date" id="swpfe_export_date_from" name="swpfe_export_date_from"
                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
        </div>
        <div>
            <label for="swpfe_export_date_to" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('Date To', 'advanced-entries-manager-for-wpforms'); ?>
            </label>
            <input type="date" id="swpfe_export_date_to" name="swpfe_export_date_to"
                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
        </div>
    </div>

    <!-- Batch Size -->
    <div>
        <label for="swpfe_export_limit" class="block text-sm font-medium text-gray-700 mb-1">
            <?php esc_html_e('Max Entries per Batch', 'advanced-entries-manager-for-wpforms'); ?>
        </label>
        <input type="number" name="swpfe_export_limit" id="swpfe_export_limit"
            value="<?php echo esc_attr(get_option('swpfe_export_limit', 100)); ?>"
            min="10" max="1000"
            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
        <p class="text-xs text-gray-500 mt-1">
            <?php esc_html_e('Used to process large exports in chunks. Default is 100.', 'advanced-entries-manager-for-wpforms'); ?>
        </p>
    </div>

    <!-- Exclude Fields -->
    <div x-show="fields.length > 0" class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            <?php esc_html_e('Exclude Columns', 'advanced-entries-manager-for-wpforms'); ?>
        </label>
        <div class="flex flex-wrap gap-4">
            <template x-for="(field, index) in fields" :key="index">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" :value="field" x-model="excludedFields" class="rounded text-indigo-600">
                    <span x-text="field"></span>
                </label>
            </template>
        </div>
    </div>

    <button
    type="button"
    class="mt-6 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition"
    :disabled="!selectedFormId"
    @click="exportAllBatches"
>
    <?php esc_html_e('Download CSV', 'advanced-entries-manager-for-wpforms'); ?>
</button>


</div>
