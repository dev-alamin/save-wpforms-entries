<?php
// Run this inside WordPress context (e.g. via WP-CLI or admin hook)

global $wpdb;

$table = $wpdb->prefix . 'wpforms_db'; // your source table

$total_to_insert = 1000000;
$batch_size = 1000;

function generate_fake_entry_data($index) {
    $data = [
        'WPFormsDB_status'                    => 'read',
        'Year of Registration'                => '2020',
        'Make (eg Ford)'                      => 'Honda',
        'Model (eg Mustang)'                  => 'T-123',
        'Vehicle identification number (VIN)' => (string)(92873498327498632 + $index),
        'Your Name'                           => 'Al Amin Ahsan',
        'Email'                               => 'a100lamin@gmail.com',
        'Miles driven since purchase'         => '90000',
        'Miles driven per week'               => '300',
    ];

    return maybe_serialize($data);
}

$start_time = time();

for ($offset = 0; $offset < $total_to_insert; $offset += $batch_size) {
    $values = [];
    $placeholders = [];

    for ($i = 0; $i < $batch_size && ($offset + $i) < $total_to_insert; $i++) {
        $form_post_id = 1; // Assuming form_post_id 1, adjust as needed
        $form_value = generate_fake_entry_data($offset + $i);
        // Random date within last year
        $form_date = date('Y-m-d H:i:s', strtotime("-".rand(0, 365)." days"));

        $placeholders[] = "(%d, %s, %s)";
        $values[] = $form_post_id;
        $values[] = $form_value;
        $values[] = $form_date;
    }

    // Prepare the query
    $query = "INSERT INTO {$table} (form_post_id, form_value, form_date) VALUES " . implode(',', $placeholders);

    // Run the query with placeholders and values safely
    $prepared_query = $wpdb->prepare($query, ...$values);

    $wpdb->query($prepared_query);

    echo "Inserted batch " . ($offset / $batch_size + 1) . " of " . ceil($total_to_insert / $batch_size) . "\n";

    // Optional: sleep a bit to avoid server overload
    usleep(500000); // 0.5 sec
}

echo "Completed inserting {$total_to_insert} entries in " . (time() - $start_time) . " seconds.\n";
