<?php

namespace App\AdvancedEntryManager\Admin;

use App\AdvancedEntryManager\Assets;
use App\AdvancedEntryManager\Admin\Options;
use App\AdvancedEntryManager\Admin\Menu;

/**
 * Class Admin
 * 
 * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Admin {

    /**
     * Constructor.
     *
     * Hooks into WordPress admin actions to initialize the admin menu,
     * enqueue assets, register settings, and hide update notices on plugin pages.
     */
    public function __construct() {
        new Assets();
        new Options();
        new Menu();
        new Admin_Notice();
    }
}