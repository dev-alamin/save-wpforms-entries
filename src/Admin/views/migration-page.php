<?php defined('ABSPATH') || exit; ?>
<div x-data="migrationHandler()" x-init="init()"
    class="wrap fem-admin-page min-h-screen !max-w-7xl !m-auto bg-white px-8 py-10 text-[15px] font-inter text-gray-800 space-y-10">

    <!-- Header -->
    <div class="mb-8 bg-slate-700 !text-white px-4 py-2 rounded-lg">
        <h1 class="!text-3xl !font-extrabold !tracking-tight !flex !items-center !text-white !gap-3">
            🚀 <span><?php esc_html_e('Migrate from WPFormsDB', 'forms-entries-manager'); ?></span>
        </h1>
        <p class="!text-gray-200 !mt-1">
            <?php esc_html_e('Easily migrate your old WPFormsDB entries to take full advantage of our advanced features.', 'forms-entries-manager'); ?>
        </p>
    </div>

    <!-- Input + Start -->
    <div class="bg-indigo-50 border border-indigo-200 p-6 rounded-lg space-y-4">
        <h2 class="text-lg font-semibold text-indigo-700">
            <strong><?php esc_html_e('Total Entries:', 'forms-entries-manager'); ?></strong>
            <span x-text="totalEntries"></span>
        </h2>

        <?php $summary = \App\AdvancedEntryManager\Utility\Helper::wpformd_db_data_summary(); ?>

        <div class="bg-white rounded-md border border-gray-200 shadow-sm p-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <!-- Forms Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 6h16M4 10h16M4 14h10m-5 4h5" />
                </svg>
                <?php esc_html_e('Migration Overview', 'forms-entries-manager'); ?>
            </h2>

            <ul class="divide-y divide-gray-100 text-sm">
                <?php foreach ($summary as $item) : ?>
                    <li class="py-3 flex items-start justify-between">
                        <div class="flex items-center gap-2 text-gray-700">
                            <!-- Form Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="m560-240-56-58 142-142H160v-80h486L504-662l56-58 240 240-240 240Z" />
                            </svg>
                            <span class="font-medium"><?php echo esc_html($item['post_title']); ?></span>
                        </div>
                        <div class="flex items-center gap-1 text-gray-600">
                            <!-- Entry Count Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 7H7v6h6V7z" />
                                <path fill-rule="evenodd" d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm10 12H5V5h10v10z" clip-rule="evenodd" />
                            </svg>
                            <span><?php echo number_format_i18n($item['entry_count']); ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <template x-if="totalEntries > 10000">
            <p class="text-sm text-red-600">
                <?php esc_html_e('⚠️ You have a large dataset. We recommend using a batch size of 100 or less to avoid timeouts.', 'forms-entries-manager'); ?>
            </p>
        </template>

        <div class="flex items-center gap-4">
            <span class="text-gray-700 text-sm"><?php esc_html_e('Batch Size:', 'forms-entries-manager'); ?></span>
            <template x-if="totalEntries > 10000">
                <label class="block">
                    <div class="relative mt-1 w-32">
                        <input type="number" min="10" max="100" x-model.number="batchSize"
                            class="!block !w-full !rounded-md !border !border-gray-300 !bg-white !pl-10 !pr-3 !py-2 !text-sm !text-gray-800 !shadow !focus:border-indigo-500 !focus:ring-indigo-200 !focus:outline-none !focus:ring-1 !placeholder:text-gray-400"
                            placeholder="<?php echo esc_attr__('50', 'forms-entries-manager'); ?>"
                            oninput="this.value = Math.max(10, Math.min(100, parseInt(this.value) || 10));" />
                    </div>
                </label>
            </template>

            <!-- Start Migration Button -->
            <button @click="startMigration"
                x-show="!migrating && !complete && !migrationInProgress"
                class="flex items-center gap-2 px-6 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow transition">
                <!-- Play SVG Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor"
                    viewBox="0 0 20 20">
                    <path d="M6 4l10 6-10 6V4z" />
                </svg>
                <?php esc_html_e('Start Migration', 'forms-entries-manager'); ?>
            </button>

            <!-- See Progress Button -->
            <button @click="seeProgress"
                title="<?php esc_attr_e('View Migration Progress', 'forms-entries-manager'); ?>"
                x-show="!migrating && !complete && migrationInProgress"
                class="flex items-center gap-2 px-6 py-2 text-sm cursor-pointer font-semibold bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow transition">
                
                <!-- Spinner SVG -->
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>

                <?php esc_html_e('Migration is running. See Progress', 'forms-entries-manager'); ?>
            </button>


            <!-- Stop Migration Button -->
            <button @click="stopMigration"
                x-show="migrating"
                class="flex items-center gap-2 px-6 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded shadow transition">
                <!-- Stop SVG Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor"
                    viewBox="0 0 24 24">
                    <path d="M6 6h12v12H6z" />
                </svg>
                <?php esc_html_e('Stop Migration', 'forms-entries-manager'); ?>
            </button>

            <!-- Completion Message -->
            <span x-show="complete" class="text-green-600 font-medium flex items-center gap-1 text-sm">
                <!-- Check Circle SVG Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor"
                    viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd" />
                </svg>
                <?php esc_html_e('Migration Completed!', 'forms-entries-manager'); ?>
            </span>
        </div>
    </div>

    <!-- Progress Wrapper -->
    <div x-show="migrating" class="mt-4 space-y-2 bg-indigo-50 p-6 pb-8 rounded-lg">
        <h2 class="text-lg font-semibold mb-2"><?php esc_html_e('Migration Progress', 'forms-entries-manager'); ?></h2>
        <!-- Label -->
        <p class="text-sm text-gray-700 font-medium">
            <?php esc_html_e('Migrating Entries:', 'forms-entries-manager'); ?> <span class="font-bold" x-text="migrated"></span> /
            <span x-text="total"></span>
        </p>

        <!-- Estimated Time -->
        <p class="text-sm text-gray-500 italic" x-show="estimatedTime">
            <?php esc_html_e('Estimated time left:', 'forms-entries-manager'); ?> <span x-text="estimatedTime"></span>
        </p>

        <!-- Progress Label -->
        <div class="mb-1 text-sm text-gray-700 font-medium flex justify-between">
            <span><?php esc_html_e( 'Progress:', 'forms-entries-manager' ) ?></span>
            <span x-text="`${progress}%`"></span>
        </div>

        <!-- Progress Bar -->
        <div class="relative w-full h-4 bg-gray-200 rounded overflow-hidden">
            <div :style="`width: ${progress}%`"
                class="absolute left-0 top-0 h-full bg-indigo-600 transition-all duration-300 ease-in-out">
            </div>
        </div>

    </div>

    <!-- Logs -->
    <template x-if="log.length">
        <div class="mt-4 space-y-2 bg-indigo-50 p-6 rounded-lg pb-8">
            <h2 class="text-lg font-semibold mb-2"><?php esc_html_e('Migration Log', 'forms-entries-manager'); ?></h2>
            <ul class="text-sm space-y-1 max-h-60 overflow-y-auto bg-gray-50 border border-gray-200 px-4 py-4 rounded p-4">
                <template x-for="(entry, index) in log" :key="index">
                    <li x-text="entry"></li>
                </template>
            </ul>
        </div>
    </template>
</div>