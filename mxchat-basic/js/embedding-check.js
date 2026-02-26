document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('embedding_failed')) {
        alert('Failed to generate embedding for the content. Please try submitting again.');
    }

    // Sitemap Form Loader
    var sitemapForm = document.getElementById('mxchat-sitemap-form');
    
    if (sitemapForm) {
        var loadingSpinner = document.getElementById('mxchat-sitemap-loading');
        var loadingText = document.getElementById('mxchat-loading-text');
        var sitemapUrlField = document.getElementById('sitemap_url');
        var submitButton = sitemapForm.querySelector('input[type="submit"]');

        sitemapForm.addEventListener('submit', function() {
            sitemapUrlField.style.display = 'none';
            submitButton.style.display = 'none';

            loadingSpinner.style.display = 'flex';
            loadingText.style.display = 'block';
        });
    }

    // Submit Content Form Loader
    var contentForm = document.getElementById('mxchat-content-form');
    
    if (contentForm) {
        var contentLoadingSpinner = document.getElementById('mxchat-content-loading');
        var contentLoadingText = document.getElementById('mxchat-content-loading-text');
        var articleContentField = document.getElementById('article_content');
        var articleUrlField = document.getElementById('article_url');
        var contentSubmitButton = contentForm.querySelector('input[type="submit"]');

        contentForm.addEventListener('submit', function() {
            // Hide form fields
            articleContentField.style.display = 'none';
            articleUrlField.style.display = 'none';
            contentSubmitButton.style.display = 'none';

            // Show loading spinner and text
            contentLoadingSpinner.style.display = 'flex';
            contentLoadingText.style.display = 'block';
        });
    }
});
