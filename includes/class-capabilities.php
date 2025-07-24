<?php
/**
 * Class Capabilities
 *
 * Handles the addition of custom capabilities to the 'administrator' role
 * for managing WPForms entries within the plugin.
 *
 * @package advanced-entries-manager
 * @since 1.0.0
 */
namespace SWPFE;

class Capabilities {
    public function __construct()
    {
        add_action( 'admin_init', [ $this, 'add_cap' ] );
    }
    /**
     * Adds custom WPForms entry management capabilities to the administrator role.
     *
     * Checks if the 'administrator' role exists and assigns the following capabilities:
     * - can_create_wpf_entries
     * - can_edit_wpf_entries
     * - can_delete_wpf_entries
     * - can_view_wpf_entries
     * - can_manage_wpf_entries
     *
     * @return void
     */
    public function add_cap(){
        $role = get_role( 'administrator' );

        if( ! $role ) {
            return;
        }

        $capabilities = [
            'can_create_wpf_entries',
            'can_edit_wpf_entries',
            'can_delete_wpf_entries',
            'can_view_wpf_entries',
            'can_manage_wpf_entries',
        ];

        foreach( $capabilities as $cap ) {
            if( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }
}