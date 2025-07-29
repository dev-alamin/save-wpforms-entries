<div class="bg-white border border-indigo-100 shadow p-6 space-y-6">
    <h3 class="text-xl font-bold text-indigo-700"><?php esc_html_e('📤 CSV Export Settings', 'advanced-entries-manager-for-wpforms'); ?></h3>
    <p class="text-sm text-gray-600 mb-4">
        <?php esc_html_e('Configure what data should be exported, how the file is structured, and what filters apply.', 'advanced-entries-manager-for-wpforms'); ?>
    </p>

    <div>
        <label for="swpfe_export_limit" class="block text-sm font-medium text-gray-700 mb-1">
            <?php esc_html_e('Maximum entries per export', 'advanced-entries-manager-for-wpforms'); ?>
        </label>
        <input type="number" name="swpfe_export_limit" id="swpfe_export_limit"
            value="<?php echo esc_attr(get_option('swpfe_export_limit', 100)); ?>"
            min="10" max="1000"
            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
        <p class="text-xs text-gray-500 mt-1">
            <?php esc_html_e('Limits how many entries can be exported at once. Prevents memory overload.', 'advanced-entries-manager-for-wpforms'); ?>
        </p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            <?php esc_html_e('Include columns', 'advanced-entries-manager-for-wpforms'); ?>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="swpfe_export_columns[]" value="id" checked disabled class="rounded text-indigo-600">
            <span>ID</span>
        </label>
        <label class="inline-flex items-center gap-2 ml-4">
            <input type="checkbox" name="swpfe_export_columns[]" value="email" class="rounded text-indigo-600">
            <span>Email</span>
        </label>
        <label class="inline-flex items-center gap-2 ml-4">
            <input type="checkbox" name="swpfe_export_columns[]" value="date" class="rounded text-indigo-600">
            <span>Date</span>
        </label>
        <!-- Add more checkboxes based on available fields -->
    </div>
</div>