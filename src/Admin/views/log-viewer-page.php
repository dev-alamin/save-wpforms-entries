<div class="wrap">
	<h1><?php esc_html_e( 'Forms Entries Manager Logs', 'forms-entries-manager' ); ?></h1>
	
	<?php
	// Instantiate the list table class
	$log_list_table = new \App\AdvancedEntryManager\Admin\Logs\Log_List_Table();

	// Prepare the items for display
	$log_list_table->prepare_items();
	?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
		<?php
		// Display the table
		$log_list_table->display();
		?>
	</form>
</div>