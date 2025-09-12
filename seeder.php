<?php
/**
 * Script to insert 2 million rows of test data into the forms database tables.
 *
 * IMPORTANT: This script should ONLY be run on a development or staging environment.
 * Running it on a live production site can cause irreversible data corruption.
 *
 * To run this script, place it in the root directory of your WordPress installation
 * and access it via your browser or a command-line tool like `curl`.
 * Example: http://your-site.com/seeder.php
 */
global $wpdb;

// Define table names.
$submissions_table = $wpdb->prefix . 'forms_em_submissions';
$data_table        = $wpdb->prefix . 'forms_em_data';

// --- Configuration ---
$total_rows = 1000000;
$batch_size = 5000; // Adjust for your server's performance.
$form_id    = 131;  // The specific form ID to use for all entries.

echo "<h1>Inserting {$total_rows} Test Entries for Form #{$form_id}...</h1>";
echo "Starting... This may take a while. Please be patient.<br>";

// Prepare dummy data for insertion.
$names    = ['John Doe', 'Jane Smith', 'Alice Johnson', 'Bob Williams', 'Chris Davis'];
$products = ['IPhone 13 Pro', 'MacBook Air M2', 'iPad Pro', 'Apple Watch Series 8', 'AirPods Pro 2'];

try {
    // Disable logging to improve performance for bulk inserts.
    $wpdb->suppress_errors(true);
    $wpdb->show_errors = false;

    $progress_last_update = microtime(true);
    $start_time = time();

    for ( $i = 0; $i < $total_rows; $i += $batch_size ) {
        // Prepare submissions batch query.
        $submissions_query_parts = [];
        $submissions_values = [];

        for ( $j = 0; $j < $batch_size; $j++ ) {
            $submissions_query_parts[] = '(%d, %s, %s, %s, %s, %s)'; // Added %d for form_id
            $submissions_values = array_merge($submissions_values, [
                $form_id, // The new form_id value
                $names[array_rand($names)],
                strtolower( str_replace(' ', '.', $names[array_rand($names)]) ) . mt_rand(1, 1000) . '@example.com',
                'read',
                current_time('mysql', 1),
                current_time('mysql', 1)
            ]);
        }
        $submissions_query = "INSERT INTO `{$submissions_table}` (`form_id`, `name`, `email`, `status`, `created_at`, `updated_at`) VALUES " . implode(', ', $submissions_query_parts);
        $result = $wpdb->query( $wpdb->prepare($submissions_query, ...$submissions_values) );
        if ( $result === false ) {
            throw new Exception("Submission batch insertion failed. Error: " . $wpdb->last_error);
        }

        // Get the range of IDs just inserted.
        $first_inserted_id = $wpdb->insert_id;
        
        // Prepare data batch query using the correct IDs.
        $data_query_parts = [];
        $data_values = [];
        
        for ( $j = 0; $j < $batch_size; $j++ ) {
            $current_submission_id = $first_inserted_id + $j;
            $current_time = current_time('mysql', 1);

            // Generate consistent data for the current submission.
            $random_name    = $names[array_rand($names)];
            $random_email   = strtolower( str_replace(' ', '.', $random_name) ) . mt_rand(1, 1000) . '@example.com';
            $random_product = $products[array_rand($products)];

            // Insert Name
            $data_query_parts[] = '(%d, %s, %s, %s, %s)';
            $data_values = array_merge($data_values, [$current_submission_id, 'Name', $random_name, $current_time, $current_time]);

            // Insert Email
            $data_query_parts[] = '(%d, %s, %s, %s, %s)';
            $data_values = array_merge($data_values, [$current_submission_id, 'Email', $random_email, $current_time, $current_time]);

            // Insert Query
            $data_query_parts[] = '(%d, %s, %s, %s, %s)';
            $data_values = array_merge($data_values, [$current_submission_id, 'Query', 'This is a test query for ' . $random_product, $current_time, $current_time]);

            // Insert Select Product
            $data_query_parts[] = '(%d, %s, %s, %s, %s)';
            $data_values = array_merge($data_values, [$current_submission_id, 'Select Product', $random_product, $current_time, $current_time]);
        }

        $data_query = "INSERT INTO `{$data_table}` (`submission_id`, `field_key`, `field_value`, `created_at`, `updated_at`) VALUES " . implode(', ', $data_query_parts);
        $result = $wpdb->query( $wpdb->prepare($data_query, ...$data_values) );
        if ( $result === false ) {
            throw new Exception("Data batch insertion failed. Error: " . $wpdb->last_error);
        }

        // Print progress
        $elapsed = microtime(true) - $progress_last_update;
        $progress_last_update = microtime(true);
        $processed_count = $i + $batch_size;
        $percentage = round(($processed_count / $total_rows) * 100, 2);
        echo "Inserted {$processed_count} of {$total_rows} entries ({$percentage}%). Last batch took " . round($elapsed, 2) . " seconds.<br>";
        ob_flush();
        flush();
    }

    $end_time = time();
    $duration = $end_time - $start_time;
    echo "<h2>Done!</h2>";
    echo "Successfully inserted {$total_rows} entries in {$duration} seconds.<br>";

} catch (Exception $e) {
    echo "<h2>Error!</h2>";
    echo "An error occurred: " . $e->getMessage() . "<br>";
}

// Restore error handling and logging.
$wpdb->suppress_errors(false);
$wpdb->show_errors = true;