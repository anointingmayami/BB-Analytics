<?php
// Hook into AJAX action for CSV download
add_action('wp_ajax_export_csv_data', 'export_csv_data');

// Handle CSV download via AJAX
function export_csv_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';
    
    // Fetch data to export
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY click_count DESC");

    if ($results) {
        // Create CSV content
        $csv_output = '';
        $csv_output .= "ID,Link URL,Click Count,Time Spent (seconds)\n";

        foreach ($results as $row) {
            $csv_output .= $row->id . "," . $row->link_url . "," . $row->click_count . "," . $row->time_spent . "\n";
        }

        // Return CSV data as JSON response for AJAX
        wp_send_json_success($csv_output);
    } else {
        wp_send_json_error('No data available');
    }

    wp_die(); // Required to properly end the AJAX request in WordPress
}
