<?php
/*
Plugin Name: BB Analytics Tracker
Plugin URI: https://github.com/anointingmayami/DAD
Description: A plugin to track and rank the most popular links on your WordPress site.
Version: 1.0
Author: The Business Builders
Author URI: https://thebusinessbuilders.org/
License: GPLv2 or later
*/
// Security: Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Include analytics functions
require_once(plugin_dir_path(__FILE__) . 'inc/analytics-functions.php');
require_once(plugin_dir_path(__FILE__) . 'inc/analytics-export-functions.php');
require_once(plugin_dir_path(__FILE__) . 'inc/export-analytics-api.php');

require_once(plugin_dir_path(__FILE__) . 'inc/php-jwt-main/src/JWT.php');
require_once(plugin_dir_path(__FILE__) . 'inc/php-jwt-main/src/Key.php');

function wp_analytics_enqueue_scripts() {
    // Enqueue the JavaScript file
    wp_enqueue_script('wp-analytics-js', plugin_dir_url(__FILE__) . 'js/export-analytics.js', array('jquery'), null, true);
    
    // Localize script with an array
    wp_localize_script('wp-analytics-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php') // Pass the AJAX URL to JavaScript
    ));
}
add_action('admin_enqueue_scripts', 'wp_analytics_enqueue_scripts');

// Hook to trigger plugin activation
register_activation_hook(__FILE__, 'wp_analytics_activation');

// Enqueue tracking scripts for the frontend
function wp_analytics_tracker_enqueue_scripts() {
    wp_enqueue_script(
        'wp-analytics-tracker', 
        plugin_dir_url(__FILE__) . 'js/tracking-script.js', 
        array('jquery'), 
        null, 
        true
    );

    // Localize script to pass the AJAX URL to the JavaScript file
    wp_localize_script('wp-analytics-tracker', 'wpAnalytics', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'wp_analytics_tracker_enqueue_scripts');

// Enqueue custom admin styles for the dashboard
function wp_analytics_enqueue_admin_styles() {
    // Check if we're on the specific admin page
    $screen = get_current_screen();
    if ($screen->id === 'toplevel_page_bb-analytics-tracker') { // Adjust the ID based on your page
        wp_enqueue_style(
            'wp-analytics-dashboard-styles',
            plugin_dir_url(__FILE__) . 'css/style.css' // Adjust the path if necessary
        );
    }
}
add_action('admin_enqueue_scripts', 'wp_analytics_enqueue_admin_styles');

// Add AJAX handler for tracking page loads
function wp_analytics_track_page_load() {
    global $wpdb;

    // Get the current page URL
    $page_url = isset($_POST['page_url']) ? sanitize_text_field($_POST['page_url']) : '';
    $time_spent = isset($_POST['time_spent']) ? (int) $_POST['time_spent'] : 0;

    // Validate the page URL
    if (empty($page_url)) {
        wp_send_json_error('Invalid page URL.');
    }

    // Define the analytics table name
    $table_name = $wpdb->prefix . 'analytics_data';

    // Check if the URL already exists in the database
    $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE link_url = %s", $page_url));
    // $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE link_url = %s", $page_url));
    
    if ($existing_link) {
        // If the link exists, increment the page load count
        $wpdb->update(
            $table_name,
            array('click_count' => $existing_link->click_count + 1,
            'time_spent' => $existing_link->time_spent + $time_spent
        ),
            array('link_url' => $page_url)
        );
        sync();
    } else {
        // If the link doesn't exist, insert a new row with a page load count of 1
        $wpdb->insert(
            $table_name,
            array(
                'link_url' => $page_url,
                'click_count' => 1,
                'time_spent' => $time_spent
            )
        );
        sync();
    }

    // Send a success response back to the JavaScript
    wp_send_json_success('Page load tracked successfully.');
}

// Hook the function to the AJAX action for both logged-in and non-logged-in users
add_action('wp_ajax_track_page_load', 'wp_analytics_track_page_load');
add_action('wp_ajax_nopriv_track_page_load', 'wp_analytics_track_page_load');
add_action('wp_ajax_track_time_spent', 'wp_analytics_track_page_load');
add_action('wp_ajax_nopriv_track_time_spent', 'wp_analytics_track_page_load');

// Add admin menu for the analytics dashboard
function wp_analytics_add_admin_menu() {
    add_menu_page(
        'BB Analytics',                     // Page title
        'BB Analytics',                     // Menu title
        'manage_options',                   // Capability
        'bb-analytics-tracker',             // Menu slug
        'wp_analytics_display_dashboard'    // Callback function to render dashboard
    );

    // Add settings page as a submenu under the BB Analytics menu
    add_submenu_page(
        'bb-analytics-tracker',             // Parent slug
        'BB Analytics Settings',             // Page title
        'Settings',                          // Menu title
        'manage_options',                    // Capability
        'wp-analytics-settings',             // Menu slug
        'wp_analytics_settings_page'         // Callback function to render settings page
    );
}
add_action('admin_menu', 'wp_analytics_add_admin_menu');

// Display analytics data in the admin dashboard
function wp_analytics_display_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';

    // Pagination setup
    $limit = 10; // Results per page
    $page = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
    $offset = ($page - 1) * $limit;

    // Fetch total number of links for pagination
    $total_links = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_links / $limit);

    // Fetch top-ranking links for the current page
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY click_count DESC LIMIT %d OFFSET %d", $limit, $offset));

    echo '<div class="wrap">';
    
    echo '<h1>The Business Builders Analytics - Dashboard</h1>';
    echo '<p>Empowering businesses through actionable insights with simplicity and security.</p>';

    wp_analytics_display_sync_status();

    // Display search form
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="bb-analytics-tracker">';
    echo '<input type="text" name="search" placeholder="Search Links..." />';
    echo '<input type="submit" value="Search" class="button">';
    echo '</form>';

    // CSV Export Button
    echo '<button id="export_csv_button" class="button-primary" style="margin-top: 18px;">Export Data to CSV</button>';

    if ($results) {
        // Display top-ranking links
        echo '<h2>Top Ranking Links</h2>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>Link URL</th><th>Page Views</th><th>Time Spent (seconds)</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->link_url) . '</td>';
            echo '<td>' . esc_html($row->click_count) . '</td>';
            echo '<td>' . esc_html($row->time_spent) . ' Seconds</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Centered Pagination Controls
        echo '<div class="tablenav">';
        echo '<div class="tablenav-pages" style="text-align: center;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $current = $i === $page ? ' current' : '';
            echo '<a class="page-numbers' . $current . '" href="?page=bb-analytics-tracker&paged=' . $i . '">' . $i . '</a> ';
        }
        echo '</div></div>';
    } else {
        echo '<p>No data available yet. Start by getting some traffic!</p>';
    }

    echo '</div>';

}

// Register settings on admin init
function wp_analytics_register_settings() {
    register_setting('wp_analytics_settings_group', 'wp_analytics_enable_tracking');
    register_setting('wp_analytics_settings_group', 'wp_analytics_anonymize_data');
    register_setting('wp_analytics_settings_group', 'wp_analytics_data_retention');
}
add_action('admin_init', 'wp_analytics_register_settings');

// Render the settings page for analytics preferences
function wp_analytics_settings_page() {
    ?>
    <div class="wrap">
        <h1>The Business Builders Analytics - Settings</h1>
        <p>Configure your analytics preferences securely. We ensure that all collected data is compliant with the latest privacy regulations, and we respect your users' privacy.</p>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_analytics_settings_group'); // Register settings
            do_settings_sections('wp_analytics_settings_group'); // Display settings fields
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Data Collection</th>
                    <td><input type="checkbox" name="wp_analytics_enable_tracking" value="1" <?php checked(1, get_option('wp_analytics_enable_tracking'), true); ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anonymize Data</th>
                    <td><input type="checkbox" name="wp_analytics_anonymize_data" value="1" <?php checked(1, get_option('wp_analytics_anonymize_data'), true); ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Data Retention Period</th>
                    <td><input type="number" name="wp_analytics_data_retention" value="<?php echo esc_attr(get_option('wp_analytics_data_retention', 30)); ?>" /> days</td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <p>We respect your privacy. All data collected is encrypted and stored securely. You can control how long your data is kept.</p>
    </div>
    <?php
}

// Function to display the sync status in the dashboard widget
function wp_analytics_display_sync_status() {
    // Get the last sync status and time from the options table
    $last_sync_status = get_option('wp_analytics_last_sync_status', 'Never Synced');
    $last_sync_time = get_option('wp_analytics_last_sync_time', 'Never');

    echo '<h4>Last Sync Status: ' . esc_html($last_sync_status) . '</h4>';
    echo '<p>Last Sync Time: ' . esc_html($last_sync_time) . '</p>';
}

function sync() {
    // Ensure it is not an auto-save and check for post type
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    // You can add conditions based on your requirements
        sync_top_links_with_flask_api(); // Trigger sync immediately after update
}

