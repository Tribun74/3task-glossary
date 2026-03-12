/**
 * 3task Glossary - Admin JavaScript v2.1.0
 * Tab navigation and admin interactions
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * 3task Glossary Admin Handler
     */
    const AZGLAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initTabs();
            this.initConfirmations();
            this.initNotices();
            this.initHeartModal();
        },

        /**
         * Initialize tab navigation
         */
        initTabs: function() {
            const $tabs = $('.azgl-tab');
            const $tabContents = $('.azgl-tab-content');

            // Handle tab clicks
            $tabs.on('click', function(e) {
                e.preventDefault();

                const $tab = $(this);
                const tabId = $tab.data('tab');

                if (!tabId) {
                    return;
                }

                // Update active tab
                $tabs.removeClass('active');
                $tab.addClass('active');

                // Show corresponding content
                $tabContents.removeClass('active');
                $('#azgl-tab-' + tabId).addClass('active');

                // Update URL without page reload
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
            });

            // Handle browser back/forward
            $(window).on('popstate', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || 'glossaries';

                $tabs.removeClass('active');
                $tabs.filter('[data-tab="' + tab + '"]').addClass('active');

                $tabContents.removeClass('active');
                $('#azgl-tab-' + tab).addClass('active');
            });
        },

        /**
         * Initialize delete confirmations
         */
        initConfirmations: function() {
            // Confirm glossary removal
            $(document).on('click', '.azgl-remove-glossary', function(e) {
                const confirmMessage = $(this).data('confirm') || 'Are you sure you want to remove this glossary?';

                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Initialize admin notices auto-dismiss
         */
        initNotices: function() {
            // Auto-dismiss notices after 5 seconds
            setTimeout(function() {
                $('.azgl-notice.is-dismissible').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual dismiss
            $(document).on('click', '.azgl-notice .notice-dismiss', function() {
                $(this).closest('.azgl-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Initialize heart support modal
         */
        initHeartModal: function() {
            const $modal = $('#azgl-heart-modal');
            const $heartButton = $('#azgl-heart-support');
            const $closeButton = $modal.find('.azgl-modal-close');
            const $copyButtons = $modal.find('.copy-code-btn');

            // Open modal on heart button click
            $heartButton.on('click', function(e) {
                e.preventDefault();
                $modal.fadeIn(200);
                $('body').css('overflow', 'hidden');
            });

            // Close modal on X button click
            $closeButton.on('click', function() {
                $modal.fadeOut(200);
                $('body').css('overflow', '');
            });

            // Close modal on overlay click
            $modal.on('click', function(e) {
                if (e.target === this) {
                    $modal.fadeOut(200);
                    $('body').css('overflow', '');
                }
            });

            // Close modal on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.fadeOut(200);
                    $('body').css('overflow', '');
                }
            });

            // Copy code functionality
            $copyButtons.on('click', function() {
                const $btn = $(this);
                const textToCopy = $btn.data('copy');

                // Create temporary textarea
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(textToCopy).select();

                try {
                    document.execCommand('copy');
                    const originalText = $btn.text();
                    $btn.addClass('copied').text('Copied!');
                    setTimeout(function() {
                        $btn.removeClass('copied').text(originalText);
                    }, 2000);
                } catch (err) {
                    console.error('Copy failed:', err);
                }

                $temp.remove();
            });
        },

        /**
         * Show a temporary notice
         *
         * @param {string} message Notice message
         * @param {string} type Notice type (success, warning, error, info)
         */
        showNotice: function(message, type) {
            type = type || 'info';

            // Remove existing notices
            $('.azgl-temp-notice').remove();

            // Create notice
            const $notice = $('<div class="azgl-notice azgl-notice-' + type + ' azgl-temp-notice is-dismissible">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
                '</div>');

            // Insert notice
            $('.azgl-content').prepend($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Escape HTML for XSS prevention
         *
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Color Scheme Preview
     */
    const ColorSchemePreview = {

        /**
         * Available color schemes
         */
        schemes: ['emerald', 'ocean', 'sunset', 'berry', 'slate', 'auto'],

        /**
         * Initialize color scheme preview
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind change events
         */
        bindEvents: function() {
            $(document).on('change', 'input[name="color_scheme"]', function() {
                const scheme = $(this).val();
                ColorSchemePreview.updatePreview(scheme);
            });
        },

        /**
         * Update preview colors by switching CSS classes
         *
         * @param {string} scheme Color scheme name
         */
        updatePreview: function(scheme) {
            const $preview = $('#azgl-preview');
            const $nav = $preview.find('.azgl-nav');
            const $entries = $preview.find('.azgl-entries');

            // Remove all scheme classes
            this.schemes.forEach(function(s) {
                $nav.removeClass('azgl-scheme-' + s);
                $entries.removeClass('azgl-scheme-' + s);
            });

            // Add new scheme class
            $nav.addClass('azgl-scheme-' + scheme);
            $entries.addClass('azgl-scheme-' + scheme);
        }
    };

    /**
     * Navigation Style Preview
     */
    const NavStylePreview = {

        /**
         * Available navigation styles
         */
        styles: ['buttons', 'pills', 'minimal'],

        /**
         * Initialize navigation style preview
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind change events
         */
        bindEvents: function() {
            $(document).on('change', 'input[name="nav_style"]', function() {
                const style = $(this).val();
                NavStylePreview.updatePreview(style);
            });
        },

        /**
         * Update preview navigation style
         *
         * @param {string} style Navigation style name
         */
        updatePreview: function(style) {
            const $nav = $('#azgl-preview .azgl-nav');

            // Remove all style classes
            this.styles.forEach(function(s) {
                $nav.removeClass('azgl-nav-' + s);
            });

            // Add new style class
            $nav.addClass('azgl-nav-' + style);
        }
    };

    /**
     * Dark Mode Preview
     */
    const DarkModePreview = {

        /**
         * Initialize dark mode preview
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind change events
         */
        bindEvents: function() {
            $(document).on('change', 'select[name="dark_mode"]', function() {
                const mode = $(this).val();
                DarkModePreview.updatePreview(mode);
            });
        },

        /**
         * Update preview dark mode
         *
         * @param {string} mode Dark mode setting (auto, light, dark)
         */
        updatePreview: function(mode) {
            const $preview = $('#azgl-preview');

            // Remove existing mode classes
            $preview.removeClass('azgl-dark-mode azgl-light-mode');

            // Add appropriate class
            if (mode === 'dark') {
                $preview.addClass('azgl-dark-mode');
            } else if (mode === 'light') {
                $preview.addClass('azgl-light-mode');
            } else {
                // Auto: check system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    $preview.addClass('azgl-dark-mode');
                } else {
                    $preview.addClass('azgl-light-mode');
                }
            }
        }
    };

    /**
     * Form Helpers
     */
    const FormHelpers = {

        /**
         * Initialize form helpers
         */
        init: function() {
            this.initCheckboxes();
        },

        /**
         * Initialize checkbox styling
         */
        initCheckboxes: function() {
            // Add visual feedback on checkbox change
            $(document).on('change', '.azgl-checkbox-group input[type="checkbox"]', function() {
                const $group = $(this).closest('.azgl-checkbox-group');

                if ($(this).is(':checked')) {
                    $group.addClass('is-checked');
                } else {
                    $group.removeClass('is-checked');
                }
            });

            // Initialize state on load
            $('.azgl-checkbox-group input[type="checkbox"]:checked').each(function() {
                $(this).closest('.azgl-checkbox-group').addClass('is-checked');
            });
        }
    };

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        // Only initialize on our admin page
        if ($('.azgl-admin').length === 0) {
            return;
        }

        AZGLAdmin.init();
        ColorSchemePreview.init();
        NavStylePreview.init();
        DarkModePreview.init();
        FormHelpers.init();
    });

    // Expose for external use
    window.AZGLAdmin = AZGLAdmin;

})(jQuery);
