<?php
// Ensure security
if (!defined('ABSPATH')) {
    exit;
}

// Create custom database table on plugin activation
function wp_analytics_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        link_url varchar(255) NOT NULL,
        click_count int(11) DEFAULT 0 NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'wp_analytics_create_table');

// Handle AJAX request to track link clicks
function wp_analytics_track_link_click() {
    if (isset($_POST['link_url'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'analytics_data';
        $link_url = sanitize_text_field($_POST['link_url']);

        // Check if link already exists in the table
        $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE link_url = %s", $link_url));

        if ($existing_link) {
            // Increment click count
            $wpdb->update(
                $table_name,
                array('click_count' => $existing_link->click_count + 1),
                array('id' => $existing_link->id)
            );
        } else {
            // Insert new link with click count 1
            $wpdb->insert(
                $table_name,
                array(
                    'link_url' => $link_url,
                    'click_count' => 1
                )
            );
        }
    }
    wp_die(); // Important to terminate the request
}
add_action('wp_ajax_track_link_click', 'wp_analytics_track_link_click');
add_action('wp_ajax_nopriv_track_link_click', 'wp_analytics_track_link_click'); // For non-logged-in users
