<?php
/*
Plugin Name: DAD Analytics Tracker
Plugin URI: https://github.com/anointingmayami/DAD
Description: A plugin to track and rank the most popular links on your WordPress site.
Version: 1.0
Author: Anointing J. Mayami
Author URI: mayamifoundations.com
License: GPLv2 or later
*/

// Security: Ensure this file is not directly accessed
if (!defined('ABSPATH')) {
    exit;
}

// Include analytics functions
require_once(plugin_dir_path(__FILE__) . 'inc/analytics-functions.php');

// Register the JavaScript tracking script
function wp_analytics_tracker_enqueue_scripts() {
    wp_enqueue_script('wp-analytics-tracker', plugin_dir_url(__FILE__) . 'js/tracking-script.js', array('jquery'), null, true);

    // Localize the script to pass AJAX URL
    wp_localize_script('wp-analytics-tracker', 'wpAnalytics', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}

add_action('wp_enqueue_scripts', 'wp_analytics_tracker_enqueue_scripts');

// Add an admin menu for the analytics dashboard
function wp_analytics_add_admin_menu() {
    add_menu_page(
        'Analytics Tracker',   // Page title
        'Analytics Tracker',   // Menu title
        'manage_options',      // Capability
        'analytics-tracker',   // Menu slug
        'wp_analytics_display_dashboard' // Callback function
    );
}
add_action('admin_menu', 'wp_analytics_add_admin_menu');

// Display analytics data on the dashboard
function wp_analytics_display_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'analytics_data';

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY click_count DESC LIMIT 10");

    echo '<div class="wrap"><h1>Top Ranking Links</h1>';
    if ($results) {
        echo '<table class="widefat fixed"><thead><tr><th>Link URL</th><th>Click Count</th></tr></thead><tbody>';
        foreach ($results as $row) {
            echo '<tr><td>' . esc_html($row->link_url) . '</td><td>' . esc_html($row->click_count) . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No data available yet.</p>';
    }
    echo '</div>';
}

