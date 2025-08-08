<?php

namespace App\AdvancedEntryManager\Admin;

use App\AdvancedEntryManager\Assets;
use App\AdvancedEntryManager\Admin\Options;
use App\AdvancedEntryManager\Admin\Menu;
use App\AdvancedEntryManager\Admin\Admin_Notice;
use App\AdvancedEntryManager\GoogleSheet\Admin_UI;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;

/**
 * Class Admin
 * 
 * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Admin {

    /**
     * Assets instance.
     * @var Assets
     */
    protected $assets;

    /**
     * Options instance.
     * @var Options
     */
    protected $options;

    /**
     * Menu instance.
     * @var Menu
     */
    protected $menu;

    /**
     * Admin_Notice instance.
     * @var Admin_Notice
     */
    protected $admin_notice;

    /**
     * Admin_UI instance
     * @var Admin_UI
     */
    protected $admin_ui;

    /**
     * Send_Data instance
     * @var mixed
     */
    protected $send_data;

    /**
     * Constructor.
     *
     * Hooks into WordPress admin actions to initialize the admin menu,
     * enqueue assets, register settings, and hide update notices on plugin pages.
     */
    public function __construct(
    Assets $assets,
    Options $options,
    Menu $menu,
    Admin_Notice $admin_notice,
    Send_Data $send_data
    ) {
        $this->assets = $assets;
        $this->options = $options;
        $this->menu = $menu;
        $this->admin_notice = $admin_notice;
        $this->send_data = $send_data;
    }
}