<?php
namespace SWPFE;

class DB_Handler {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'swpfe_entries';
	}

	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			entry LONGTEXT NOT NULL,
			status VARCHAR(20) DEFAULT 'unread',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
