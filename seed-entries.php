<?php
// Run via WP-CLI or plugin context
global $wpdb;

$table = $wpdb->prefix . 'swpfe_entries';
$form_id = 2;
$status_options = ['read', 'unread'];
$batch_size = 1000;
$total = 1000000; // 1 million
// $total = 100;

// Sample data sets
$names = ['Rafiul Islam', 'Sadia Jahan', 'Tanvir Ahmed', 'Nusrat Nila', 'Fahim Hossain', 'Ayesha Khanum'];
$emails = ['rafiul@example.com', 'sadia@example.com', 'tanvir@example.com', 'nila@example.com', 'fahim@example.com', 'ayesha@example.com'];
$comments = [
    'Works as expected',
    'Great form experience!',
    'Test comment for debugging',
    'Just checking functionality',
    'Hope this reaches you well',
    'Simple and effective input'
];

for ($i = 0; $i < $total; $i += $batch_size) {
    $values = [];
    $placeholders = [];

    for ($j = 0; $j < $batch_size; $j++) {
        $status = $status_options[array_rand($status_options)];

        $name = $names[array_rand($names)];
        $email_prefix = explode('@', $emails[array_rand($emails)]);
        $email = $email_prefix[0] . "{$i}_{$j}@" . $email_prefix[1];
        $comment = $comments[array_rand($comments)];

        $entry_data = maybe_serialize([
            "Name" => $name,
            "Email" => $email,
            "Comment Or Message" => $comment
        ]);

        $created_at = date('Y-m-d H:i:s', strtotime("-" . rand(0, 365) . " days"));
        $printed_at = date('Y-m-d H:i:s', strtotime("-" . rand(0, 365) . " days"));
        $resent_at = '1970-01-01 00:00:00'; // default

        $values[] = $form_id;
        $values[] = $entry_data;
        $values[] = $status;
        $values[] = $created_at;
        $values[] = 1; // is_favorite
        $values[] = 0; // exported_to_csv
        $values[] = 0; // synced_to_gsheet
        $values[] = $printed_at;
        $values[] = $resent_at;
        $values[] = ""; // note

        $placeholders[] = "(%d, %s, %s, %s, %d, %d, %d, %s, %s, %s)";
    }

    $sql = "INSERT INTO $table 
        (form_id, entry, status, created_at, is_favorite, exported_to_csv, synced_to_gsheet, printed_at, resent_at, note) 
        VALUES " . implode(", ", $placeholders);

    $wpdb->query($wpdb->prepare($sql, ...$values));
    echo "Inserted batch: " . ($i + 1) . " to " . ($i + $batch_size) . "\n";
}

echo "Seeding complete.\n";
