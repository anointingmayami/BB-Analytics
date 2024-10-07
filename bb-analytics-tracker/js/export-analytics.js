jQuery(document).ready(function($) {
    $('#export_csv_button').on('click', function(e) {
        e.preventDefault();

        // Make an AJAX request to get the CSV data
        $.ajax({
            url: ajaxurl, // WordPress admin-ajax URL
            type: 'POST',
            data: {
                action: 'export_csv_data', // The AJAX action
            },
            success: function(response) {
                if (response.success) {
                    // Create a Blob from the CSV data
                    var blob = new Blob([response.data], { type: 'text/csv' });
                    
                    // Create a link element for download
                    var downloadLink = document.createElement('a');
                    downloadLink.href = window.URL.createObjectURL(blob);
                    downloadLink.download = 'analytics_data.csv'; // The name of the file to be downloaded

                    // Trigger the download
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink); // Clean up
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred: ' + error);
            }
        });
    });
});