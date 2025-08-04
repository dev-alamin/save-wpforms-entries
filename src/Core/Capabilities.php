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
namespace App\AdvancedEntryManager\Core;

class Capabilities {
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
            'can_create_aemfw_entries',
            'can_edit_aemfw_entries',
            'can_delete_aemfw_entries',
            'can_view_aemfw_entries',
            'can_manage_aemfw_entries',
        ];

        foreach( $capabilities as $cap ) {
            if( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }

    /**
     * Removes custom capabilities from the 'administrator' role.
     *
     * This method is called when the plugin is deactivated to clean up capabilities.
     *
     * @return void
     */
    public function remove_cap() {
        $role = get_role( 'administrator' );

        if( ! $role ) {
            return;
        }

        $capabilities = [
            'can_create_aemfw_entries',
            'can_edit_aemfw_entries',
            'can_delete_aemfw_entries',
            'can_view_aemfw_entries',
            'can_manage_aemfw_entries',
        ];

        foreach( $capabilities as $cap ) {
            if( $role->has_cap( $cap ) ) {
                $role->remove_cap( $cap );
            }
        }
    }
}