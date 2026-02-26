jQuery(document).ready(function($) {
    // Content Indexing Tab Scripts
    
    // Handle reindex button
    $('#reindex-content-btn').click(function() {
        if (confirm(baachal_admin.reindex_confirm)) {
            var button = $(this);
            button.prop('disabled', true).text(baachal_admin.indexing_text);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'baachal_reindex_content',
                    nonce: baachal_admin.reindex_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Successfully indexed ' + response.data.count + ' items.');
                        location.reload(); // Refresh to update stats
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Ajax error occurred.');
                },
                complete: function() {
                    button.prop('disabled', false).text(baachal_admin.reindex_text);
                }
            });
        }
    });
    
    // Handle test search
    $('#test-search-btn').click(function() {
        var query = $('#test-query').val();
        if (!query) return;
        
        $('#search-results').html('<p>Searching...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'baachal_test_content_search',
                query: query,
                nonce: baachal_admin.test_search_nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '<h5>Search Results:</h5>';
                    if (response.data.results.length > 0) {
                        html += '<ul>';
                        response.data.results.forEach(function(result) {
                            html += '<li><strong>' + result.title + '</strong> (' + result.post_type + ')<br>';
                            html += '<small>' + result.excerpt + '</small><br>';
                            html += '<a href="' + result.url + '" target="_blank">' + result.url + '</a></li>';
                        });
                        html += '</ul>';
                    } else {
                        html += '<p>No results found.</p>';
                    }
                    $('#search-results').html(html);
                } else {
                    $('#search-results').html('<p style="color: red;">Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                $('#search-results').html('<p style="color: red;">Ajax error occurred.</p>');
            }
        });
    });
    
    $('#test-query').keypress(function(e) {
        if (e.which == 13) {
            $('#test-search-btn').click();
        }
    });
    
    // Product Search Tab Scripts
    
    // Handle clear cache button
    $('#clear-cache-btn').click(function() {
        if (!confirm(baachal_admin.clear_cache_confirm)) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text(baachal_admin.clearing_text);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_baachal_cache',
                nonce: baachal_admin.clear_cache_nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(baachal_admin.cache_cleared_text);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Ajax error occurred.');
            },
            complete: function() {
                button.prop('disabled', false).text(baachal_admin.clear_cache_text);
            }
        });
    });
    
    // Settings Tab Navigation
    $('.baachal-nav-tab').click(function(e) {
        e.preventDefault();
        
        // Remove active classes
        $('.baachal-nav-tab').removeClass('nav-tab-active');
        $('.baachal-tab-panel').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding panel
        var target = $(this).attr('href');
        $(target).addClass('active');
        
        // Update URL hash without scrolling
        if (history.pushState) {
            history.pushState(null, null, target);
        } else {
            location.hash = target;
        }
    });
    
    // Handle data-tab attribute as well for backward compatibility
    $('.nav-tab[data-tab]').click(function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        
        // Remove active classes
        $('.nav-tab').removeClass('nav-tab-active');
        $('.baachal-tab-panel').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding panel
        $('#tab-' + tabId).addClass('active');
        
        // Store active tab in localStorage
        localStorage.setItem('baachal_active_tab', tabId);
    });
    
    // Set initial tab based on URL hash, localStorage, or default to first tab
    var hash = window.location.hash;
    var savedTab = localStorage.getItem('baachal_active_tab');
    
    if (hash && $(hash).length) {
        $('.baachal-nav-tab[href="' + hash + '"]').click();
    } else if (savedTab && $('#tab-' + savedTab).length) {
        $('.nav-tab[data-tab="' + savedTab + '"]').click();
    } else {
        $('.nav-tab:first').click();
    }
    
    // Color preview functionality
    function updateColorPreview(input) {
        var $input = $(input);
        var $preview = $input.parent().find('.baachal-color-preview');
        if ($preview.length) {
            $preview.css('background-color', $input.val());
        }
    }
    
    // Initialize color previews
    $('input[type="color"]').each(function() {
        updateColorPreview(this);
    }).on('change', function() {
        updateColorPreview(this);
    });
    
    // AI Provider switching functionality
    $('#baachal_ai_provider').change(function() {
        var selectedProvider = $(this).val();
        
        // Hide all API key rows and model groups
        $('.baachal-api-key-row').hide();
        $('.baachal-model-group').hide();
        
        // Show the selected provider's API key row and model group
        $('.baachal-' + selectedProvider + '-key').show();
        $('.baachal-' + selectedProvider + '-models').show();
        
        // Only set default model if this is triggered by user interaction, not page load
        if ($(this).data('user-changed')) {
            var defaultModels = {
                'gemini': 'gemini-2.5-flash',
                'openai': 'gpt-5',
                'claude': 'claude-sonnet-4-5-20250929',
                'grok': 'grok-4-fast'
            };
            
            if (defaultModels[selectedProvider]) {
                $('#baachal_ai_model').val(defaultModels[selectedProvider]);
            }
        }
    });
    
    // Mark when user manually changes the provider
    $('#baachal_ai_provider').on('mousedown keydown', function() {
        $(this).data('user-changed', true);
    });
    
    // Initialize the provider display on page load without changing the model
    function initializeProviderDisplay() {
        var selectedProvider = $('#baachal_ai_provider').val();
        
        // Hide all API key rows and model groups
        $('.baachal-api-key-row').hide();
        $('.baachal-model-group').hide();
        
        // Show the selected provider's API key row and model group
        $('.baachal-' + selectedProvider + '-key').show();
        $('.baachal-' + selectedProvider + '-models').show();
    }
    
    // Initialize on page load
    initializeProviderDisplay();
    
    // Enhanced Color Picker Functionality
    function initializeColorPickers() {
        $('.baachal-color-picker-container').each(function() {
            var container = $(this);
            var colorInput = container.find('.baachal-color-input');
            var colorSwatch = container.find('.baachal-color-swatch');
            var hexInput = container.find('.baachal-color-hex');
            var presetColors = container.find('.baachal-preset-color');
            
            // Sync color input with swatch and hex input
            colorInput.on('input change', function() {
                var color = $(this).val();
                colorSwatch.css('background-color', color);
                hexInput.val(color.toUpperCase());
                updatePresetSelection(container, color);
            });
            
            // Sync hex input with color input and swatch
            hexInput.on('input', function() {
                var hex = $(this).val();
                if (isValidHex(hex)) {
                    colorInput.val(hex);
                    colorSwatch.css('background-color', hex);
                    updatePresetSelection(container, hex);
                }
            });
            
            // Handle preset color clicks
            presetColors.on('click', function() {
                var color = $(this).data('color');
                colorInput.val(color).trigger('change');
                colorSwatch.css('background-color', color);
                hexInput.val(color.toUpperCase());
                updatePresetSelection(container, color);
            });
            
            // Handle swatch clicks (open color picker)
            colorSwatch.on('click', function() {
                colorInput.click();
            });
            
            // Initialize preset selection
            updatePresetSelection(container, colorInput.val());
        });
    }
    
    function updatePresetSelection(container, color) {
        container.find('.baachal-preset-color').removeClass('selected');
        container.find('.baachal-preset-color[data-color="' + color.toLowerCase() + '"]').addClass('selected');
    }
    
    function isValidHex(hex) {
        return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(hex);
    }
    
    // Initialize color pickers
    initializeColorPickers();
});