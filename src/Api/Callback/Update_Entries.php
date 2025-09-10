<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;

/**
 * Class Update_Entries
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Update_Entries {
	/**
     * Updates an existing entry in the database.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response
     */
    public function update_entry( WP_REST_Request $request ) {
        global $wpdb;

        // Get and validate essential parameters.
        $params = $request->get_json_params();
        $id     = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
        
        if ( ! $id ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __( 'Missing or invalid entry ID.', 'forms-entries-manager' ),
                ],
                400
            );
        }

        // Separate metadata from entry fields.
        list( $submission_data, $entry_fields ) = $this->parse_update_data( $params );

        if ( empty( $submission_data ) && empty( $entry_fields ) ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __( 'No valid fields provided for update.', 'forms-entries-manager' ),
                ],
                400
            );
        }

        // Perform updates and handle the response.
        $result = $this->perform_updates( $id, $submission_data, $entry_fields );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => $result->get_error_message(),
                ],
                $result->get_error_code()
            );
        }

        return new WP_REST_Response(
            [
                'success'        => true,
                'message'        => __( 'Entry updated successfully.', 'forms-entries-manager' ),
                'updated_fields' => array_merge( array_keys( $submission_data ), array_keys( $entry_fields ) ),
                'entry_id'       => $id,
            ],
            200
        );
    }
    
    /**
     * Parses the request parameters and separates them into submission data and entry fields.
     *
     * @param array $params The raw request parameters.
     * @return array An array containing submission data and entry fields.
     */
    private function parse_update_data( array $params ) {
        $submission_data = [];
        $entry_fields    = [];
        
        $submission_keys = [
            'status', 
            'is_favorite', 
            'note', 
            'exported_to_csv', 
            'synced_to_gsheet', 
            'printed_at', 
            'resent_at',
            'is_spam',
            'name',
            'email'
        ];

        foreach ( $params as $key => $value ) {
            if ( in_array( $key, $submission_keys ) ) {
                $submission_data[ $key ] = $this->sanitize_submission_field( $key, $value );
            } elseif ( $key === 'entry' && is_array( $value ) ) {
                $entry_fields = $value;
            }
        }
        
        return [ $submission_data, $entry_fields ];
    }
    
    /**
     * Sanitizes a submission field based on its key.
     *
     * @param string $key The field key.
     * @param mixed  $value The value to sanitize.
     * @return mixed The sanitized value.
     */
    private function sanitize_submission_field( $key, $value ) {
        switch ( $key ) {
            case 'status':
                return sanitize_text_field( $value );
            case 'is_favorite':
            case 'exported_to_csv':
            case 'synced_to_gsheet':
            case 'is_spam':
                return absint( $value );
            case 'note':
                $max_length = 1000;
                return mb_substr( sanitize_textarea_field( $value ), 0, $max_length );
            case 'printed_at':
            case 'resent_at':
                return ! empty( $value ) ? wp_date( 'Y-m-d H:i:s', strtotime( $value ) ) : null;
            case 'name':
                return sanitize_text_field( $value );
            case 'email':
                return sanitize_email( $value );
            default:
                return $value;
        }
    }

    /**
     * Performs the database updates on the two tables.
     *
     * @param int   $id             The submission ID.
     * @param array $submission_data Data for the submissions table.
     * @param array $entry_fields   Data for the entries table.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    private function perform_updates( $id, $submission_data, $entry_fields ) {
        global $wpdb;
        $submissions_table = Helper::get_submission_table();
        $entries_table     = Helper::get_data_table();

        try {
            $wpdb->query( 'START TRANSACTION' );

            // Update submissions table. This part is already correct.
            if ( ! empty( $submission_data ) ) {
                $format = array_map(function($value) {
                    return is_int($value) ? '%d' : '%s';
                }, $submission_data);
                
                $updated_rows = $wpdb->update(
                    $submissions_table,
                    $submission_data,
                    [ 'id' => $id ],
                    $format,
                    [ '%d' ]
                );

                if ( false === $updated_rows ) {
                    throw new \Exception( 'Database update to submissions table failed.' );
                }
            }
            
            // Update entries table. This is the corrected section.
            if ( ! empty( $entry_fields ) ) {
                foreach ( $entry_fields as $field_key => $field_value ) {
                    $formatted_value = sanitize_text_field( $field_value );
                    
                    // First, check if the entry field already exists.
                    $exists = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `$entries_table` WHERE submission_id = %d AND field_key = %s",
                            $id,
                            $field_key
                        )
                    );

                    if ( $exists ) {
                        // If it exists, update the existing row.
                        $wpdb->update(
                            $entries_table,
                            [ 'field_value' => $formatted_value ],
                            [ 'id' => $exists ]
                        );
                    } else {
                        // If it does not exist, insert a new row.
                        $wpdb->insert(
                            $entries_table,
                            [
                                'submission_id' => $id,
                                'field_key'     => $field_key,
                                'field_value'   => $formatted_value,
                                'created_at'    => current_time( 'mysql' ),
                            ]
                        );
                    }
                }
            }
            
            $wpdb->query( 'COMMIT' );
            return true;

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new \WP_Error( 500, $e->getMessage() );
        }
    }

	/**
	 * The callback function to handle the unsync request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_unsync_request( WP_REST_Request $request ) {
		$entry_id = absint( $request['id'] );
		if ( ! $entry_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing entry ID.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$send_data = new Send_Data();
		$result    = $send_data->unsync_entry_from_sheet( $entry_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(), // Assuming WP_Error already has human-readable text
				),
				500
			); // Internal Server Error
		} elseif ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to unsync entry from Google Sheet.', 'forms-entries-manager' ),
				),
				500
			); // Internal Server Error
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Entry successfully unsynced from Google Sheet.', 'forms-entries-manager' ),
			),
			200
		); // OK
	}

	/**
	 * The callback function to handle the unsync request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_sync_request( WP_REST_Request $request ) {
		$is_authorized = Helper::is_google_authorized();

		if ( ! $is_authorized ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You have not authorize google, please do it from settings page.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$entry_id = absint( $request['id'] );

		if ( ! $entry_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing entry ID.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$send_data = new Send_Data();

		$send = $send_data->process_single_entry( array( 'entry_id' => $entry_id ) );

		if ( $send ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Entry successfully sync to Google Sheet.', 'forms-entries-manager' ),
				),
				200
			); // OK
		} else {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Failed to sync entry to Google Sheet. Please check the logs.', 'forms-entries-manager' ),
				),
				500
			); // Internal Server Error
		}
	}
}
