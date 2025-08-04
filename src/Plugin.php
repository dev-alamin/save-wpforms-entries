<?php
namespace App\AdvancedEntryManager;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Core\DB_Schema;
use App\AdvancedEntryManager\Api\Route;
use App\AdvancedEntryManager\Core\Entry_Handler;
use App\AdvancedEntryManager\Scheduler\Actions\Migrate_Batch;
use App\AdvancedEntryManager\Scheduler\Actions\Export_Entries_Action;
use App\AdvancedEntryManager\Admin\Admin;

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
        add_action('plugins_loaded',[ $this, 'early_init'] );

        $this->load_core_classes();

        register_activation_hook(__FILE__, function () {
            DB_Schema::create_table();
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
        load_plugin_textdomain('advanced-entries-manager-for-wpforms', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
        new Route();

        /**
         * Entry_Handler class will manage the entries to save from WPFORMS submission to our table.
         * It usage respective hooks to catch the data.
         */
        new Entry_Handler();

        /**
         * Scheduler Actions for batch processing and exporting entries.
         * Migrate_Batch handles the migration of entries in batches.
         * Export_Entries_Action handles the export of entries to CSV or other formats.
         */
        new Migrate_Batch();

        /**
         * Action Scheduler for exporting entries.
         */
        new Export_Entries_Action();

        // If in admin area, load the Admin class for managing entries and settings
        if ( is_admin() ) {
            new Admin();
        }
    }
}