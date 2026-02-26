/**
 * MxChat Pro & Extensions Page JavaScript
 * Handles navigation, license management, and mobile interactions
 */
(function($) {
    'use strict';

    // ==========================================================================
    // Mobile Detection
    // ==========================================================================
    function isMobile() {
        return window.innerWidth <= 782;
    }

    // ==========================================================================
    // Section Navigation
    // ==========================================================================
    function showSection(targetId) {
        // Hide all sections
        $('.mxch-section').removeClass('active');

        // Show target section
        $('#' + targetId).addClass('active');

        // Update sidebar nav active states
        $('.mxch-nav-link, .mxch-addon-nav-link').removeClass('active');
        $('[data-target="' + targetId + '"]').addClass('active');

        // Update mobile nav active states
        $('.mxch-mobile-nav-link').removeClass('active');
        $('.mxch-mobile-nav-link[data-target="' + targetId + '"]').addClass('active');

        // Close mobile menu if open
        closeMobileMenu();

        // Handle mobile detail panel
        if (isMobile() && targetId.startsWith('addon-')) {
            showMobileDetailPanel(targetId);
        }

        // Scroll to top of content
        $('.mxch-content').scrollTop(0);
    }

    // ==========================================================================
    // Mobile Menu
    // ==========================================================================
    function openMobileMenu() {
        $('.mxch-mobile-menu').addClass('open');
        $('.mxch-mobile-overlay').addClass('open');
        $('body').addClass('mxch-mobile-menu-open');
    }

    function closeMobileMenu() {
        $('.mxch-mobile-menu').removeClass('open');
        $('.mxch-mobile-overlay').removeClass('open');
        $('body').removeClass('mxch-mobile-menu-open');
    }

    // ==========================================================================
    // Mobile Detail Panel
    // ==========================================================================
    function showMobileDetailPanel(targetId) {
        if (isMobile()) {
            $('#' + targetId).addClass('mobile-active');
            $('body').addClass('mxch-mobile-panel-open');
        }
    }

    function hideMobileDetailPanel() {
        $('.mxch-addon-detail').removeClass('mobile-active');
        $('body').removeClass('mxch-mobile-panel-open');
    }

    // ==========================================================================
    // License Activation - HANDLED BY activation-script.js
    // ==========================================================================
    // License activation is handled by activation-script.js which provides
    // better status feedback and error recovery with domain linking.
    // Do not add duplicate handlers here.

    // ==========================================================================
    // License Deactivation - HANDLED BY activation-script.js
    // ==========================================================================
    // License deactivation is handled by activation-script.js.
    // Do not add duplicate handlers here.

    // ==========================================================================
    // Domain Linking - HANDLED BY activation-script.js
    // ==========================================================================
    // Domain linking is handled by activation-script.js.
    // Do not add duplicate handlers here.

    // ==========================================================================
    // Initialize
    // ==========================================================================
    $(document).ready(function() {
        // Sidebar navigation clicks
        $(document).on('click', '.mxch-nav-link[data-target], .mxch-addon-nav-link[data-target]', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            if (target) {
                showSection(target);
            }
        });

        // Extension card clicks (overview grid)
        $(document).on('click', '.mxch-extension-card', function(e) {
            // Don't trigger if clicking on a button inside
            if ($(e.target).closest('button, a').length) {
                return;
            }
            var addon = $(this).data('addon');
            if (addon) {
                showSection('addon-' + addon);
            }
        });

        // View addon button clicks
        $(document).on('click', '.mxch-view-addon-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var target = $(this).data('target');
            if (target) {
                showSection(target);
            }
        });

        // Back button clicks (supports both .mxch-back-btn and .mxch-back-link)
        $(document).on('click', '.mxch-back-btn, .mxch-back-link', function(e) {
            e.preventDefault();
            var target = $(this).data('target') || 'overview';
            hideMobileDetailPanel();
            showSection(target);
        });

        // Mobile menu toggle
        $(document).on('click', '.mxch-mobile-menu-btn', function(e) {
            e.preventDefault();
            openMobileMenu();
        });

        // Mobile menu close
        $(document).on('click', '.mxch-mobile-menu-close, .mxch-mobile-overlay', function(e) {
            e.preventDefault();
            closeMobileMenu();
        });

        // Mobile nav link clicks
        $(document).on('click', '.mxch-mobile-nav-link', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            if (target) {
                showSection(target);
            }
        });

        // License activation, deactivation, and domain linking are handled by
        // activation-script.js - do not add duplicate handlers here.

        // Activate license button in sidebar (when inactive)
        $(document).on('click', '.mxch-activate-license-btn', function(e) {
            e.preventDefault();
            showSection('overview');
            // Focus on email field after section loads
            setTimeout(function() {
                $('#mxchat_pro_email').focus();
            }, 300);
        });

        // Handle window resize
        $(window).on('resize', function() {
            if (!isMobile()) {
                hideMobileDetailPanel();
                closeMobileMenu();
            }
        });

        // Handle escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
                if (isMobile()) {
                    hideMobileDetailPanel();
                    showSection('overview');
                }
            }
        });
    });

})(jQuery);
