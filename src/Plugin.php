<?php

namespace App\AdvancedEntryManager;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Core\DB_Schema;
use App\AdvancedEntryManager\Api\Route;
use App\AdvancedEntryManager\Core\Submit_Entry;
use App\AdvancedEntryManager\Scheduler\Actions\Migrate_Batch_Action;
use App\AdvancedEntryManager\Scheduler\Actions\Export_Entries_Action;
use App\AdvancedEntryManager\Admin\Admin;

// Import All Routes' Callback Classes
use App\AdvancedEntryManager\Api\Callback\Bulk_Action;
use App\AdvancedEntryManager\Api\Callback\Get_Entries;
use App\AdvancedEntryManager\Api\Callback\Get_Forms;
use App\AdvancedEntryManager\Api\Callback\Update_Entries;
use App\AdvancedEntryManager\Api\Callback\Create_Entries;
use App\AdvancedEntryManager\Api\Callback\Export_Entries;
use App\AdvancedEntryManager\Api\Callback\Delete_Single_Entry;
use App\AdvancedEntryManager\Api\Callback\Migrate;

// Import All Core Classes
use App\AdvancedEntryManager\Assets;
use App\AdvancedEntryManager\Admin\Options;
use App\AdvancedEntryManager\Admin\Menu;
use App\AdvancedEntryManager\Core\Capabilities;
use App\AdvancedEntryManager\Admin\Admin_Notice;
use App\AdvancedEntryManager\Core\Handle_Cache;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;
use App\AdvancedEntryManager\Scheduler\Actions\Sync_Google_Sheet_Action;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Bootstrap Plugin for the Advanced Entries Manager plugin.
 * 
 * This file initializes the plugin, loads necessary assets, sets up database tables,
 * and registers API routes.
 * @package AdvancedEntriesManager
 * @author Md. Al Amin
 * @since 1.0.0
 */
class Plugin
{
    /**
     * Singleton instance of the plugin.
     * 
     * This is used to ensure that only one instance of the plugin is created.
     * It is a common design pattern in WordPress plugins to avoid conflicts and ensure
     * that the plugin's functionality is encapsulated within a single instance.
     */
    private static $instance = null;

    /**
     * Constructor is restricted to prevent direct instantiation.
     * Use the run() method to initialize the plugin.
     */
    private function __construct()
    {
        // Load early to ensure all components are ready
        add_action('plugins_loaded', [$this, 'early_init']);

        $this->load_core_classes();

        register_activation_hook(FEM_PLUGIN_BASE_FILE, function () {
            DB_Schema::create_table();

            (new Capabilities())->add_cap();

            if (!as_has_scheduled_action('femdaily_sync')) {
                as_schedule_recurring_action(strtotime('tomorrow 2am'), DAY_IN_SECONDS, 'femdaily_sync');
            }

            if (! as_next_scheduled_action('fem_every_five_minute_sync')) {
                as_schedule_recurring_action(time(), MINUTE_IN_SECONDS * 1, 'fem_every_five_minute_sync');
            }
        });

        register_deactivation_hook(FEM_PLUGIN_BASE_FILE, function () {
            (new Capabilities())->remove_cap();

            as_unschedule_all_actions('fem_every_five_minute_sync');
        });
    }

    /**
     * Static method to run the plugin.
     * 
     * This method sets up the plugin by loading necessary classes and initializing the database schema.
     * It should be called after the plugin is loaded.
     */
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load text domain for translations and create the database table.
     * 
     * This method is called early in the plugin lifecycle to ensure
     * that the text domain is loaded and the database table is created before
     * any other operations that depend on these features.
     */
    public function early_init()
    {
        load_plugin_textdomain( 'forms-entries-manager', false, dirname( FEM_PLUGIN_BASE ) . '/languages/' );
    }

    /**
     * Load the Plugin's Core Classes.
     * 
     * This method is called to load the core classes of the plugin,
     * including API routes, entry handlers, and admin functionalities.
     */
    public function load_core_classes()
    {
        /**
         * Route class will handle API endpoints along with loading callback for the plugin.
         * It registers routes for managing entries, exporting data, and other functionalities.
         */
        new Route(
            new Bulk_Action(),
            new Get_Entries(),
            new Get_Forms(),
            new Update_Entries(),
            new Create_Entries(),
            new Export_Entries(),
            new Delete_Single_Entry(),
            new Migrate()
        );

        /**
         * Submit_Entry class will manage the entries to save from WPFORMS submission to our table.
         * It usage respective hooks to catch the data.
         */
        new Submit_Entry();

        /**
         * Scheduler Actions for batch processing and exporting entries.
         * Migrate_Batch handles the migration of entries in batches.
         * Export_Entries_Action handles the export of entries to CSV or other formats.
         */
        new Migrate_Batch_Action(new Migrate());

        new Sync_Google_Sheet_Action(new Send_Data());

        new Handle_Cache();

        /**
         * Action Scheduler for exporting entries.
         */
        new Export_Entries_Action(new Export_Entries());

        // If in admin area, load the Admin class for managing entries and settings
        if (is_admin()) {
            new Admin(
                new Assets(),
                new Options(),
                new Menu(),
                new Admin_Notice(),
                new Send_Data()
            );
        }
    }
}
