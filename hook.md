## REST API Hooks

### swpfe_before_entry_delete
do_action( 'swpfe_before_entry_delete', int $entry_id, int $form_id );

Fires before a form entry is deleted from the custom entries table.

Parameters
Name	Type	Description
$entry_id	int	The ID of the entry to be deleted.
$form_id	int	The form ID associated with the entry.

Use Case
Log the entry before deletion

Prevent deletion by flagging via a custom condition

Trigger external sync/cleanup before local deletion

### swpfe_after_entry_delete
do_action( 'swpfe_after_entry_delete', int $entry_id, int $form_id );

Fires after a form entry has been successfully deleted from the database.

Parameters
Name	Type	Description
$entry_id	int	The ID of the deleted entry.
$form_id	int	The form ID associated with the deleted entry.

Use Case
Notify admin/user via email or webhook

Log deletion history

Sync deletion to external services like Google Sheets, Airtable, etc.

### swpfe_after_entry_update
/**
* Fires after an entry has been successfully updated.
*
* @param int             $id      Entry ID.
* @param array           $data    Data that was updated.
* @param WP_REST_Request $request Full REST request object.
*/
do_action('swpfe_after_entry_update', $id, $data, $request);

### swpfe_before_entry_update
/**
* Fires before an entry update is performed.
*
* @param int             $id      Entry ID.
* @param array           $data    Data to update (column => value).
* @param WP_REST_Request $request Full REST request object.
*/
do_action('swpfe_before_entry_update', $id, $data, $request);

### swpfe_before_entry_create
/**
* Fires before inserting a new entry.
*
* @param int             $form_id Form ID.
* @param array           $entry   Entry data (array).
* @param array           $params  Full request parameters.
* @param WP_REST_Request $request REST request object.
*/
do_action('swpfe_before_entry_create', $form_id, $entry, $params, $request);

### swpfe_after_entry_create
/**
* Fires after successfully inserting a new entry.
*
* @param int             $entry_id Inserted entry ID.
* @param int             $form_id  Form ID.
* @param array           $entry    Entry data.
* @param array           $params   Full request parameters.
* @param WP_REST_Request $request  REST request object.
*/
do_action('swpfe_after_entry_create', $wpdb->insert_id, $form_id, $entry, $params, $request);

### swpfe_get_forms
/**
* Filter the list of forms returned by get_forms().
*
* @param array $forms List of forms with entry counts.
*/
return rest_ensure_response( apply_filters( 'swpfe_get_forms', $forms ) );

### swpfe_get_entries_where
/**
* Filter the WHERE clause and parameters before the query is executed.
*
* @param string          $where  The WHERE clause.
* @param array           $params Query parameters.
* @param WP_REST_Request $request The current REST request.
*/
$where = apply_filters('swpfe_get_entries_where', $where, $params, $request);

### swpfe_get_entries_data
/**
    * Filter the entries data before returning the response.
    *
    * @param array           $data    The entries data array.
    * @param array           $results Raw DB results.
    * @param WP_REST_Request $request The current REST request.
    */
$data = apply_filters('swpfe_get_entries_data', $data, $results, $request);

### swpfe_get_entries_response
/**
    * Filter the full REST response before returning.
    *
    * @param WP_REST_Response $response The REST response object.
    * @param WP_REST_Request  $request  The current REST request.
    */
return apply_filters('swpfe_get_entries_response', $response, $request);

## swpfe_check_new_entries
/**
    * Filter the new entry result rows.
    *
    * @param array           $rows    The result set.
    * @param int             $form_id The form ID.
    * @param int             $last_id The last seen entry ID.
    * @param WP_REST_Request $request The original REST request.
    */
$rows = apply_filters('swpfe_check_new_entries', $rows, $form_id, $last_id, $request);

### aemfw_create_entries_table_sql
/**
    * Filter the SQL query for creating the entries table.
    * 
    * This allows developers to modify the SQL query before it is executed.
    * @param string $sql The SQL query to create the entries table.
    * @return string Modified SQL query.
    */
$sql = apply_filters('aemfw_create_entries_table_sql', $sql);