<?php

namespace App\AdvancedEntryManager\Admin\Logs;

use App\AdvancedEntryManager\Utility\FileSystem;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom WP_List_Table for displaying log files.
 */
class Log_List_Table extends \WP_List_Table {

	private $fs;
	private $log_dir;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
		$this->fs      = new FileSystem();
		$this->log_dir = $this->get_log_directory();
	}

	protected function get_log_directory() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'forms-entries-manager-logs';
	}

	public function get_columns() {
		return array(
			'file_name'     => __( 'File Name', 'forms-entries-manager' ),
			'file_size'     => __( 'Size', 'forms-entries-manager' ),
			'date_modified' => __( 'Last Modified', 'forms-entries-manager' ),
		);
	}

	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$files = $this->get_log_files_data();

		// Handle sorting
		usort( $files, array( $this, 'sort_files' ) );

		$this->items = $files;
	}

	public function column_file_name( $item ) {
		// Correctly link to the single log view page.
		$view_url = add_query_arg(
			array(
				'page'     => 'forms-entries-manager-logs',
				'action'   => 'view_log',
				'file'     => urlencode( $item['file_name'] ),
				'_wpnonce' => wp_create_nonce( 'forms-entries-manager-view' ),
			),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $view_url ),
			esc_html( $item['file_name'] )
		);
	}

	public function column_file_size( $item ) {
		return size_format( $item['file_size'] );
	}

	public function column_date_modified( $item ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['date_modified'] );
	}

	protected function get_log_files_data() {
		if ( ! $this->fs->is_dir( $this->log_dir ) ) {
			return array();
		}

		$files = $this->fs->dirlist( $this->log_dir, false, false );
		$data  = array();

		if ( empty( $files ) ) {
			return array();
		}

		foreach ( $files as $file => $details ) {
			// Only process valid log files
			if ( $details['type'] === 'f' && substr( $file, -4 ) === '.log' ) {
				$data[] = array(
					'file_name'     => $file,
					'file_size'     => $details['size'],
					'date_modified' => $details['lastmodunix'],
				);
			}
		}
		return $data;
	}

	public function get_sortable_columns() {
		return array(
			'file_name'     => array( 'file_name', false ),
			'file_size'     => array( 'file_size', false ),
			'date_modified' => array( 'date_modified', true ),
		);
	}

	protected function sort_files( $a, $b ) {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'date_modified';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'desc';
		$result  = ( $orderby === 'file_name' ) ? strcmp( $a[ $orderby ], $b[ $orderby ] ) : $a[ $orderby ] - $b[ $orderby ];
		return ( $order === 'asc' ) ? $result : -$result;
	}

	public function no_items() {
		_e( 'No log files found.', 'forms-entries-manager' );
	}
}
