<?php

namespace App\AdvancedEntryManager\Admin;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Assets;
use App\AdvancedEntryManager\Admin\Options;
use App\AdvancedEntryManager\Admin\Menu;
use App\AdvancedEntryManager\Admin\Admin_Notice;
use App\AdvancedEntryManager\GoogleSheet\Admin_UI;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;
use App\AdvancedEntryManager\Admin\Logs\HandleLogAction;

/**
 * Class Admin
 * * Handles all admin-related functionalities including
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
     * HandleLogAction instance
     * @var HandleLogAction
     */
    protected $handle_log_action;

    /**
     * Initializes and manages all admin-related functionalities by instantiating and connecting various services.
     *
     * @param Assets       $assets       The assets handler.
     * @param Options      $options      The options handler.
     * @param Menu         $menu         The menu handler.
     * @param Admin_Notice $admin_notice The admin notice handler.
     * @param Send_Data    $send_data    The data sender for Google Sheets.
     */
    public function __construct(
    Assets $assets,
    Options $options,
    Menu $menu,
    Admin_Notice $admin_notice,
    Send_Data $send_data,
    HandleLogAction $handle_log_action
    ) {
        $this->assets = $assets;
        $this->options = $options;
        $this->menu = $menu;
        $this->admin_notice = $admin_notice;
        $this->send_data = $send_data;
        $this->handle_log_action = $handle_log_action;
    }
}