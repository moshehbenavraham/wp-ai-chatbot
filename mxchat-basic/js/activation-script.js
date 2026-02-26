/**
 * Enhanced MxChat License Activation JS
 * This handles BOTH local activation AND domain tracking
 * Plus deactivation functionality
 * VERSION: Increased timeouts for better user experience
 */
jQuery(document).ready(function($) {
    // Get configuration from hidden fields
    const AJAX_URL = $('#mxchat-ajax-url').val() || ajaxurl;
    const NONCE = $('#mxchat-nonce').val();
    const API_URL = $('#mxchat-api-url').val() || 'https://mxchat.ai';
    
    // Elements
    const form = $('#mxchat-activation-form');
    const spinner = $('#mxchat-activation-spinner');
    const submitButton = $('#activate_license_button');
    const licenseStatus = $('#mxchat-license-status');
    
    // Create or get status message element
    var $statusMessage = $('<div id="mxchat-activation-status" style="margin-top: 12px; padding: 12px 16px; border-radius: 8px; font-size: 14px; display: none;"></div>');
    if ($('#mxchat-activation-status').length === 0) {
        spinner.after($statusMessage);
    } else {
        $statusMessage = $('#mxchat-activation-status');
    }

    function showStatus(message, type) {
        var bgColor = type === 'success' ? 'rgba(16, 185, 129, 0.1)' :
                      type === 'error' ? 'rgba(239, 68, 68, 0.1)' :
                      'rgba(120, 115, 245, 0.1)';
        var textColor = type === 'success' ? '#059669' :
                        type === 'error' ? '#dc2626' :
                        '#7873f5';
        var icon = type === 'success' ? '✓' :
                   type === 'error' ? '✗' :
                   '<span class="dashicons dashicons-update spin" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>';

        $statusMessage.html(icon + ' ' + message)
            .css({
                'background': bgColor,
                'color': textColor,
                'display': 'block'
            });
    }

    // Activation handling
    if (form.length && licenseStatus.length && submitButton.length) {
        function handleActivationResponse(response) {
            if (response.success) {
                licenseStatus.text('Active');
                licenseStatus.removeClass('inactive').addClass('active');

                // Show linking status - keep spinner visible
                showStatus('License verified! Linking domain...', 'loading');

                // Track domain BEFORE showing success
                trackDomainActivation()
                    .done(function(trackResponse) {
                        console.log('Domain tracking successful:', trackResponse);
                        spinner.hide();
                        showStatus('License activated and domain linked successfully!', 'success');

                        // Wait before reload
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Domain tracking failed:', textStatus, errorThrown);
                        spinner.hide();

                        // Still show success but warn about domain linking
                        showStatus('License activated! Domain linking failed - you can link it manually after reload.', 'success');

                        // Wait before reload
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    });
            } else {
                spinner.hide();
                licenseStatus.text('Inactive');
                showStatus(response.data || 'Activation failed. Please check your input.', 'error');
                submitButton.prop('disabled', false);
            }
        }

        function checkActivationStatus() {
            const email = $('#mxchat_pro_email').val();
            const key = $('#mxchat_activation_key').val();
            
            $.ajax({
                type: 'POST',
                url: AJAX_URL,
                data: {
                    action: 'mxchat_check_license_status',
                    email: email,
                    key: key,
                    security: NONCE
                },
                timeout: 45000, // Increased from default to 45 seconds
                success: function(response) {
                    if (response.is_active) {
                        licenseStatus.text('Active');
                        licenseStatus.removeClass('inactive').addClass('active');
                        form.hide();
                        
                        // Try to track domain for timeout scenario
                        trackDomainActivation()
                            .done(function(trackResponse) {
                                console.log('Domain tracking completed after timeout check:', trackResponse);
                                alert('Your license has been activated and domain linked successfully!');
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            })
                            .fail(function() {
                                console.log('Domain tracking failed after timeout check');
                                alert('Your license has been activated successfully!\n\nNote: Domain linking may have failed. Check the "Link Domain" button after reload if needed.');
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            });
                    } else {
                        alert('License activation timed out. Please try again or contact support if the problem persists.');
                        submitButton.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Unable to verify license status. Please refresh the page to check if activation was successful.');
                    submitButton.prop('disabled', false);
                }
            });
        }

        function trackDomainActivation() {
            const email = $('#mxchat_pro_email').val();
            const key = $('#mxchat_activation_key').val();
            const domain = $('#mxchat_domain').val();
            
            console.log('Attempting to track domain:', domain);
            
            return $.ajax({
                type: 'POST',
                url: API_URL + '/mxchat-api/link-domain', // Changed to use same endpoint as manual linking
                data: {
                    email: email, // Changed parameter name to match manual linking
                    license_key: key, // Changed parameter name to match manual linking
                    domain: domain
                },
                timeout: 30000, // Increased from 10 seconds to 30 seconds
                xhrFields: {
                    withCredentials: false // Ensure CORS compatibility
                },
                crossDomain: true
            });
        }

        form.on('submit', function(event) {
            event.preventDefault();
            spinner.show();
            submitButton.prop('disabled', true);
            showStatus('Validating license key...', 'loading');

            var formData = {
                action: 'mxchat_handle_activate_license',
                mxchat_pro_email: $('#mxchat_pro_email').val(),
                mxchat_activation_key: $('#mxchat_activation_key').val(),
                security: NONCE
            };

            $.ajax({
                type: 'POST',
                url: AJAX_URL,
                data: formData,
                timeout: 120000, // Increased from 70 seconds to 2 minutes (120 seconds)
                success: function(response) {
                    handleActivationResponse(response);
                },
                error: function(jqXHR, textStatus) {
                    // On any error, check if the license was actually saved successfully
                    // This handles cases where the response format caused a parse error
                    // but the activation actually worked
                    showStatus('Verifying activation status...', 'loading');
                    checkActivationStatus();
                }
            });
        });
    }
    
    // Domain linking for existing activations
    $('#link-domain-button').on('click', function() {
        const button = $(this);
        const status = $('#domain-link-status');
        const originalText = button.text();
        
        // Get stored values from the page
        const email = $('input#mxchat_pro_email').val() || 
                     $('p:contains("Email:")').text().split(':')[1]?.trim() ||
                     '';
        const license_key = $('input#mxchat_activation_key').val() || 
                           $('code:contains("-")').text().trim() ||
                           '';
        const domain = $('#mxchat_domain').val();
        
        if (!email || !license_key) {
            status.html('<span style="color: red;">✗ Missing email or license key</span>');
            return;
        }
        
        button.prop('disabled', true).text('Linking...');
        status.html('<span style="color: #666;">Processing...</span>');
        
        $.ajax({
            type: 'POST',
            url: API_URL + '/mxchat-api/link-domain',
            data: {
                email: email,
                license_key: license_key,
                domain: domain
            },
            timeout: 30000, // Increased from 10 seconds to 30 seconds
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ Domain linked successfully!</span>');
                    button.hide();
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    status.html('<span style="color: red;">✗ ' + (response.data || 'Failed to link domain') + '</span>');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Domain linking error:', textStatus, errorThrown);
                status.html('<span style="color: red;">✗ Connection error. Please try again.</span>');
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // License deactivation
    $('#deactivate-license-button').on('click', function() {
        const button = $(this);
        const status = $('#deactivate-status');
        const originalText = button.text();
        
        // Confirmation dialog
        const confirmMessage = 'Are you sure you want to deactivate this license?\n\n' +
                             'This will:\n' +
                             '• Disable pro features on this site\n' +
                             '• Unlink this domain from your account\n' +
                             '• Allow you to activate on a different site\n\n' +
                             'You can reactivate anytime with the same license key.';
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        button.prop('disabled', true).text('Deactivating...');
        status.html('<span style="color: #666;">Processing...</span>');
        
        $.ajax({
            type: 'POST',
            url: AJAX_URL,
            data: {
                action: 'mxchat_deactivate_license',
                security: NONCE
            },
            timeout: 45000, // Increased from 15 seconds to 45 seconds
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    
                    // Show success message
                    alert('License deactivated successfully!\n\nYou can now activate this license on another site.');
                    
                    // Reload page to show deactivated state
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    status.html('<span style="color: red;">✗ ' + (response.data || 'Deactivation failed') + '</span>');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(jqXHR, textStatus) {
                if (textStatus === 'timeout') {
                    status.html('<span style="color: orange;">⚠ Deactivation timed out. Please check your account dashboard.</span>');
                } else {
                    status.html('<span style="color: red;">✗ Connection error. Please try again.</span>');
                }
                button.prop('disabled', false).text(originalText);
            }
        });
    });
});