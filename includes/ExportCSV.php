<?php

namespace App\AdvancedEntryManger;
use App\AdvancedEntryManger\DB_Handler;

/**
 * ExportCSV class
 * 
 * Handles the export of WPForms entries to CSV format.
 * This class provides methods to generate a CSV file from the entries stored in the custom database table.
 * 
 * @package    Save_WPForms_Entries
 * @subpackage Export
 * @author     Al Amin
 * @since      1.0.0
 */
class ExportCSV
{
    /**
     * Generate a CSV file from the entries in the custom database table.
     * @param string $filename The name of the CSV file to be generated.
     * @return void
     */
    public function generate_csv($filename = 'wpforms_entries.csv')
    {
        global $wpdb;
        $table = DB_Handler::table_name(); // Get the custom entries table name
        $entries = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        if (empty($entries)) {
            error_log('No entries found to export.');
            return;
        }

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write CSV header
        fputcsv($output, array_keys($entries[0]));

        // Write each entry to the CSV file
        foreach ($entries as $entry) {
            fputcsv($output, $entry);
        }

        // Close output stream
        fclose($output);
        exit;
    }
}