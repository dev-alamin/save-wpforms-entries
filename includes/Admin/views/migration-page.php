<div x-data="migrationHandler()" x-init="init()"
    class="wrap swpfe-admin-page min-h-screen !max-w-7xl !m-auto bg-white px-8 py-10 text-[15px] font-inter text-gray-800 space-y-10">

    <!-- Header -->
    <div class="mb-8 bg-slate-700 !text-white px-4 py-2 rounded-lg">
        <h1 class="!text-3xl !font-extrabold !tracking-tight !flex !items-center !text-white !gap-3">
            🚀 <span><?php esc_html_e('Migrate from WPFormsDB', 'advanced-entries-manager-for-wpforms'); ?></span>
        </h1>
        <p class="!text-gray-200 !mt-1">
            <?php esc_html_e('Easily migrate your old WPFormsDB entries to take full advantage of our advanced features.', 'advanced-entries-manager-for-wpforms'); ?>
        </p>
    </div>

    <!-- Input + Start -->
    <div class="bg-indigo-50 border border-indigo-200 p-6 rounded-lg space-y-4">
        <p>
            <strong>Total Entries:</strong>
            <span x-text="totalEntries"></span>
        </p>

        <?php
        echo '<div class="wrap"><h2>Migration Overview</h2>';
        \App\AdvancedEntryManager\Utility\Helper::swpfe_render_migration_summary_table();
        echo '</div>';
        ?>

        <template x-if="totalEntries > 100000">
            <p class="text-sm text-red-600">
                ⚠️ You have a large dataset. We recommend using a batch size of 100 or less to avoid timeouts.
            </p>
        </template>

<div class="flex items-center gap-4">
  <template x-if="totalEntries > 100000">
    <label class="block">
      <span class="text-gray-700 text-sm">Batch Size:</span>
      <input type="number" min="10" max="100" x-model.number="batchSize"
        class="mt-1 block w-32 rounded border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </label>
  </template>

  <!-- Show Start only if not migrating and not complete and no migration in progress -->
  <button @click="startMigration"
    x-show="!migrating && !complete && !migrationInProgress"
    class="px-6 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow">
    <?php esc_html_e('Start Migration', 'advanced-entries-manager-for-wpforms'); ?>
  </button>

  <!-- Show See Progress button if migration is in progress but not actively showing progress UI -->
  <button @click="seeProgress"
    x-show="!migrating && !complete && migrationInProgress"
    class="px-6 py-2 text-sm font-semibold bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow">
    See Progress
  </button>

  <button @click="stopMigration"
    x-show="migrating"
    class="px-6 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded shadow">
    <?php esc_html_e('Stop Migration', 'advanced-entries-manager-for-wpforms'); ?>
  </button>

  <span x-show="complete" class="text-green-600 font-medium">
    <?php esc_html_e('Migration Completed!', 'advanced-entries-manager-for-wpforms'); ?>
  </span>
</div>

    </div>

    <!-- Progress Wrapper -->
    <div x-show="migrating" class="mt-4 space-y-2">

        <!-- Label -->
        <p class="text-sm text-gray-700 font-medium">
            Migrating Entries: <span class="font-bold" x-text="migrated"></span> /
            <span x-text="total"></span>
        </p>

        <!-- Estimated Time -->
        <p class="text-sm text-gray-500 italic" x-show="estimatedTime">
            Estimated time left: <span x-text="estimatedTime"></span>
        </p>

        <!-- Progress Bar -->
        <div class="relative w-full h-4 bg-gray-200 rounded overflow-hidden">
            <div :style="`width: ${progress}%`"
                class="absolute h-full bg-indigo-600 transition-all duration-300">
            </div>
        </div>

    </div>

    <!-- Logs -->
    <template x-if="log.length">
        <div class="mt-6">
            <h2 class="text-lg font-semibold mb-2"><?php esc_html_e('Migration Log', 'advanced-entries-manager-for-wpforms'); ?></h2>
            <ul class="text-sm space-y-1 max-h-60 overflow-y-auto bg-gray-50 border rounded p-4">
                <template x-for="(entry, index) in log" :key="index">
                    <li x-text="entry"></li>
                </template>
            </ul>
        </div>
    </template>
</div>
