<?php
// Ensure security by exiting if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Activation Hook - Create the Analytics Data Table
function wp_analytics_activation() {
    global $wpdb;

    // Define table name and charset
    $table_name = $wpdb->prefix . 'analytics_data';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create the analytics data table
     $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        link_url varchar(255) NOT NULL,
        click_count int(11) DEFAULT 0 NOT NULL,
        time_spent INT DEFAULT 0,  -- Add the time_spent column here
        PRIMARY KEY (id),
        KEY link_url (link_url)
    ) $charset_collate;";

    // Load the dbDelta function to handle table creation/upgrade
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Log any SQL errors to help with debugging
    if ($wpdb->last_error) {
        error_log('DB Error: ' . $wpdb->last_error);
    }

    // Enable tracking by default as an option
    add_option('wp_analytics_enable_tracking', 1);

    // Optionally display an admin message using admin notice
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Congratulations! The Business Builders Analytics is now live on your website. Your data is safe, encrypted, and ready to be transformed into actionable insights.</p>
        </div>
        <?php
    });
}

// Register the activation hook for the plugin
register_activation_hook(__FILE__, 'wp_analytics_activation');

// Enqueue jQuery UI Tooltips and Styles in the Admin Area
function wp_analytics_enqueue_tooltip_script() {
    wp_enqueue_script('jquery-ui-tooltip');
    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
}
add_action('admin_enqueue_scripts', 'wp_analytics_enqueue_tooltip_script');

// Add Tooltips for Dashboard Elements
function wp_analytics_dashboard_tooltips() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#link-performance-chart').tooltip({
                content: "Track the performance of your most popular links over time. These insights help optimize your content strategy."
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'wp_analytics_dashboard_tooltips');

// Register REST API Route for Retrieving Top Links
add_action('rest_api_init', function() {
    register_rest_route('wp-analytics/v1', '/top-links', array(
        'methods' => 'GET',
        'callback' => 'wp_analytics_get_top_links',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
});

// Callback Function for the Top Links API Endpoint
function wp_analytics_get_top_links() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';

    // Retrieve the top 10 links ordered by click count
    $results = $wpdb->get_results("SELECT link_url, click_count FROM $table_name ORDER BY click_count DESC LIMIT 10");

    // Return the data as a REST response
    $response = array(
        'status' => 'success',
        'message' => 'Data retrieved successfully. Use this to optimize your website performance.',
        'data' => $results,
        'branding' => array(
            'product_name' => 'The Business Builders Analytics',
            'vision' => 'Empowering businesses through actionable insights.'
        )
    );

    return rest_ensure_response($response);
}

// Enqueue Frontend Tracking Script and Localize AJAX URL
function wp_analytics_enqueue_tracking_script() {
    wp_enqueue_script(
        'wp-analytics-tracking', // Handle for the script
        plugin_dir_url(__FILE__) . 'assets/js/analytics-tracking.js', // Path to your JS file
        array('jquery'), // jQuery dependency
        null, // Version number (optional)
        true // Load in the footer
    );

    // Pass the AJAX URL and nonce to the script
    wp_localize_script('wp-analytics-tracking', 'wpAnalytics', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_analytics_nonce') // Create a nonce for security
    ));
}
add_action('wp_enqueue_scripts', 'wp_analytics_enqueue_tracking_script');
