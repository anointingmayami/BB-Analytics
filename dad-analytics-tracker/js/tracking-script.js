jQuery(document).ready(function($) {
    var pageUrl = window.location.href; // Get the current page URL
    var startTime = new Date().getTime(); // Record the start time

    // Function to send page load tracking
    function trackPageLoad() {
        $.ajax({
            url: wpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'track_page_load', // AJAX action identifier
                page_url: pageUrl // URL of the current page
            },
            success: function(response) {
                console.log('Page load tracked successfully.');
            },
            error: function() {
                console.log('Failed to track page load.');
            }
        });
    }

    // Track page load
    trackPageLoad();

    // Track time spent on page before leaving
    window.addEventListener('beforeunload', function() {
        var timeSpent = Math.floor((new Date().getTime() - startTime) / 1000); // Calculate time spent in seconds

        // You can send this data if needed
        $.ajax({
            url: wpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'track_time_spent', // Create a new AJAX action for this
                page_url: pageUrl,
                time_spent: timeSpent // Time spent on the page
            },
            success: function(response) {
                console.log('Time spent tracked successfully.');
            },
            error: function() {
                console.log('Failed to track time spent.');
            }
        });
    });
});
