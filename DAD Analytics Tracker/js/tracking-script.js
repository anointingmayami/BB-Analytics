(function($) {
    $(document).ready(function() {
        // Attach click event to all links
        $('a').on('click', function(event) {
            var clickedLink = $(this).attr('href');

            // Send AJAX request to backend to track this click
            $.post(wpAnalytics.ajax_url, {
                action: 'track_link_click',
                link_url: clickedLink
            });
        });
    });
})(jQuery);
