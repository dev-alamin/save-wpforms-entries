<?php
// Run this file using: wp eval-file seed.php --allow-root
global $wpdb;

$table        = $wpdb->prefix . 'aemfw_entries';
$form_id      = 131;
$batch_size   = 500;
$total        = 1000000;
$status_options = ['read', 'unread'];

// Sample UK/US realistic names and emails
$names = [
    'James Smith', 'Mary Johnson', 'Robert Brown', 'Patricia Taylor', 'John Williams',
    'Jennifer Davis', 'Michael Miller', 'Linda Wilson', 'William Moore', 'Elizabeth Anderson',
    'David Thomas', 'Susan Jackson', 'Richard White', 'Jessica Harris', 'Joseph Martin',
    'Sarah Thompson', 'Charles Garcia', 'Karen Martinez', 'Thomas Robinson', 'Nancy Clark'
];

$emails = [
    'james.smith@example.com', 'mary.johnson@example.co.uk', 'robert.brown@mail.com', 'patricia.taylor@ukmail.com', 'john.williams@gmail.com',
    'jennifer.davis@yahoo.com', 'michael.miller@outlook.com', 'linda.wilson@hotmail.com', 'william.moore@aol.com', 'elizabeth.anderson@icloud.com',
    'david.thomas@live.com', 'susan.jackson@mail.co.uk', 'richard.white@gmail.co.uk', 'jessica.harris@protonmail.com', 'joseph.martin@mailinator.com',
    'sarah.thompson@example.org', 'charles.garcia@fastmail.com', 'karen.martinez@inbox.com', 'thomas.robinson@mail.com', 'nancy.clark@zoho.com'
];

$comments = [
    'Works as expected.', 'Great form experience!', 'Test comment for debugging.',
    'Just checking functionality.', 'Hope this reaches you well.', 'Simple and effective input.',
    'Loved the user interface!', 'Could be improved in responsiveness.',
    'Submission went through smoothly.', 'Error message appeared briefly.',
    'Form validation is great.', 'Need more help options.', 'Very user-friendly design.',
    'Thank you for quick response.', 'Highly recommend this service.', 'Data submission looks good.',
    'Please add more fields.', 'Encountered a small bug.', 'Satisfied with the results.', 'Great customer support.'
];

$attending = [
    'yes', 'no'
];

$guests = [
    '+2', '+3', '+4', '+5 or more'
];

for ($i = 0; $i < $total; $i += $batch_size) {
    $values = [];
    $placeholders = [];

    for ($j = 0; $j < $batch_size; $j++) {
        $status = $status_options[array_rand($status_options)];
        $name   = $names[array_rand($names)];
        
        // Generate unique email
        $email_base = $emails[array_rand($emails)];
        $email_parts = explode('@', $email_base);
        $email = $email_parts[0] . "+{$i}_{$j}@" . $email_parts[1];

        $comment = $comments[array_rand($comments)];
        // $attend = $attending[array_rand($attending)];
        // $guest = $guests[array_rand($guests)];

        $entry_data = maybe_serialize([
            'Name'                 => $name,
            'Email'                => $email,
            'Comment Or Message'   => $comment,
        ]);

        $entry_data = maybe_serialize([
            'Name' => $name,
            'Email' => $email,
            'Any comments or questions?' => $comment,
            // 'Will you be attending?' => $attend, 
            // 'How many additional guests are you bringing?' => $guest
        ]);

        $created_at  = date('Y-m-d H:i:s', strtotime("-" . rand(0, 365) . " days"));
        $printed_at  = date('Y-m-d H:i:s', strtotime("-" . rand(0, 365) . " days"));
        $resent_at   = null;
        $note        = null;

        // Maintain DB column order
        $values[] = $form_id;
        $values[] = $name;
        $values[] = $email;
        $values[] = $entry_data;
        $values[] = $status;
        $values[] = $created_at;
        $values[] = rand(0, 1); // is_favorite
        $values[] = 0;          // exported_to_csv
        $values[] = 0;          // synced_to_gsheet
        $values[] = $printed_at;
        $values[] = $resent_at;
        $values[] = $note;

        $placeholders[] = "(%d, %s, %s, %s, %s, %s, %d, %d, %d, %s, %s, %s)";
    }

    $sql = "INSERT INTO $table 
        (form_id, name, email, entry, status, created_at, is_favorite, exported_to_csv, synced_to_gsheet, printed_at, resent_at, note) 
        VALUES " . implode(', ', $placeholders);

    $wpdb->query($wpdb->prepare($sql, ...$values));
    echo "Inserted batch: " . ($i + 1) . " to " . ($i + $batch_size) . "\n";
}

echo "✅ Seeding complete.\n";
