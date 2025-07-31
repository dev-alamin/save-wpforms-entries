<div class="bg-white border border-indigo-100 shadow p-6">
    <h3 class="text-xl font-bold text-indigo-700 mb-3"><?php esc_html_e('📄 Pagination Settings', 'advanced-entries-manager-for-wpforms'); ?></h3>
    <p class="text-sm text-gray-600 mb-4">
        <?php esc_html_e('Set how many entries are shown per page in the admin UI.', 'advanced-entries-manager-for-wpforms'); ?>
    </p>

    <div>
        <label for="swpfe_entries_per_page" class="block text-sm font-medium text-gray-700 mb-1">
            <?php esc_html_e('Entries per Page', 'advanced-entries-manager-for-wpforms'); ?>
        </label>
        <input type="number" name="swpfe_entries_per_page" id="swpfe_entries_per_page"
            value="<?php echo esc_attr($per_page); ?>" min="5" max="200"
            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm">
    </div>
</div>