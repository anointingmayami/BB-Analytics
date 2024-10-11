<?php
// Hook to initialize the REST API routes
add_action('rest_api_init', function () {
    // Register the sync endpoint
    register_rest_route('wp-analytics/v1', '/sync', array(
        'methods' => 'POST',
        'callback' => 'sync_top_links_with_flask_api',
        'permission_callback' => '__return_true',
    ));
});

// Hook into WordPress Cron to sync top links every minute
add_action('wp', 'wp_analytics_schedule_sync_event');

function wp_analytics_schedule_sync_event() {
    if (!wp_next_scheduled('wp_analytics_cron_sync_event')) {
        wp_schedule_event(time(), 'every_minute', 'wp_analytics_cron_sync_event');
    }
}

// Add custom schedule for every minute
add_filter('cron_schedules', 'wp_analytics_add_cron_schedule');

function wp_analytics_add_cron_schedule($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60, // 60 seconds
        'display'  => __('Every Minute')
    );
    return $schedules;
}

// Hook for the cron sync event
add_action('wp_analytics_cron_sync_event', 'sync_top_links_with_flask_api');

function sync_top_links_with_flask_api() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';

    // Fetch top 10 links based on click count
    $results = $wpdb->get_results("SELECT id, link_url, click_count, time_spent FROM $table_name ORDER BY click_count DESC LIMIT 10", ARRAY_A);

    if ($results) {
        $data = array('top_links' => $results);

        echo $data;

        // Send the data to Flask API
        $response = wp_remote_post('https://thebusinessbuilders.org/datacenter.io/analytics/sync', array(
            'body'    => json_encode($data),
            'headers' => array('Content-Type' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            // Update sync status in the options table
            update_option('wp_analytics_last_sync_status', 'Failed');
            update_option('wp_analytics_last_sync_time', current_time('mysql'));
            return;
        }

        // If successful, update sync status and time
        update_option('wp_analytics_last_sync_status', 'Successful');
        update_option('wp_analytics_last_sync_time', current_time('mysql'));
    } else {
        update_option('wp_analytics_last_sync_status', 'Failed');
        update_option('wp_analytics_last_sync_time', current_time('mysql'));
    }
}

// Add a custom dashboard widget to show sync status and time
add_action('wp_dashboard_setup', 'wp_analytics_add_dashboard_widget');

function wp_analytics_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'wp_analytics_sync_widget',       // Widget slug
        'Analytics Data Sync Status',     // Widget title
        'wp_analytics_display_sync_status'// Callback function to display content
    );
}
