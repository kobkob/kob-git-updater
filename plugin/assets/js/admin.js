/**
 * Kob Git Updater Admin JavaScript
 * 
 * Handles UI interactions and AJAX requests for the admin interface.
 */
(function($) {
    'use strict';

    const GitUpdaterAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add repository form toggle
            $(document).on('click', '[data-toggle="add-repo-form"]', this.toggleAddRepoForm);
            
            // Repository removal confirmation
            $(document).on('click', '.remove-repository', this.confirmRepositoryRemoval);
            
            // Test connection with loading state
            $(document).on('click', '#test-connection-btn', this.handleTestConnection);
            
            // Form validation
            $(document).on('submit', '#add-repo-form form', this.validateAddRepoForm);
            
            // Auto-fill slug based on repository name
            $(document).on('input', 'input[name="repo"]', this.autoFillSlug);
        },

        /**
         * Toggle add repository form visibility
         */
        toggleAddRepoForm: function(e) {
            e.preventDefault();
            const form = document.getElementById('add-repo-form');
            if (form) {
                form.classList.toggle('hidden');
                
                // Focus first input when opening
                if (!form.classList.contains('hidden')) {
                    const firstInput = form.querySelector('input[name="owner"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
            }
        },

        /**
         * Auto-fill slug field based on repository name
         */
        autoFillSlug: function() {
            const repoInput = $(this);
            const slugInput = $('input[name="slug"]');
            
            if (slugInput.val() === '' || slugInput.data('auto-filled')) {
                const slug = repoInput.val().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').trim('-');
                slugInput.val(slug).data('auto-filled', true);
            }
        },

        /**
         * Validate add repository form
         */
        validateAddRepoForm: function(e) {
            const form = $(this);
            let isValid = true;
            const errors = [];

            // Clear previous error states
            form.find('.error').removeClass('error');

            // Validate owner
            const owner = form.find('input[name="owner"]').val().trim();
            if (!owner) {
                errors.push('Owner is required');
                form.find('input[name="owner"]').addClass('error');
                isValid = false;
            } else if (!/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,37}[a-zA-Z0-9])?$/.test(owner)) {
                errors.push('Invalid owner format');
                form.find('input[name="owner"]').addClass('error');
                isValid = false;
            }

            // Validate repository name
            const repo = form.find('input[name="repo"]').val().trim();
            if (!repo) {
                errors.push('Repository name is required');
                form.find('input[name="repo"]').addClass('error');
                isValid = false;
            } else if (!/^[a-zA-Z0-9._-]+$/.test(repo)) {
                errors.push('Invalid repository name format');
                form.find('input[name="repo"]').addClass('error');
                isValid = false;
            }

            // Validate slug
            const slug = form.find('input[name="slug"]').val().trim();
            if (!slug) {
                errors.push('WordPress slug is required');
                form.find('input[name="slug"]').addClass('error');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                this.showErrors(errors);
            }

            return isValid;
        },

        /**
         * Show validation errors
         */
        showErrors: function(errors) {
            // Remove existing error notices
            $('.giu-error-notice').remove();

            if (errors.length > 0) {
                const errorHtml = `
                    <div class="notice notice-error giu-error-notice">
                        <p><strong>Please fix the following errors:</strong></p>
                        <ul>${errors.map(error => `<li>${error}</li>`).join('')}</ul>
                    </div>
                `;
                $('.wrap .bg-white').first().before(errorHtml);
            }
        },

        /**
         * Confirm repository removal
         */
        confirmRepositoryRemoval: function(e) {
            const repositoryName = $(this).closest('tr').find('td:first-child .text-sm').text().trim();
            const message = giuAdmin.strings.confirmRemove.replace('%s', repositoryName);
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Handle test connection with loading state
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const originalText = button.text();
            
            // Show loading state
            button.prop('disabled', true)
                  .text(giuAdmin.strings.testingConnection)
                  .addClass('updating-message');
            
            // Submit the form after a brief delay to show loading state
            setTimeout(function() {
                document.getElementById('test-connection-form').submit();
            }, 500);
        },

        /**
         * Initialize tooltips for help text
         */
        initializeTooltips: function() {
            // Add tooltips to info icons
            $('[data-tooltip]').each(function() {
                const $this = $(this);
                const tooltip = $this.data('tooltip');
                
                $this.attr('title', tooltip);
            });
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            const successHtml = `
                <div class="notice notice-success is-dismissible giu-success-notice">
                    <p>${message}</p>
                </div>
            `;
            $('.wrap .bg-white').first().before(successHtml);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const errorHtml = `
                <div class="notice notice-error is-dismissible giu-error-notice">
                    <p>${message}</p>
                </div>
            `;
            $('.wrap .bg-white').first().before(errorHtml);
        },

        /**
         * Handle repository status updates
         */
        updateRepositoryStatus: function(repositoryKey, status) {
            const row = $(`tr[data-repository-key="${repositoryKey}"]`);
            if (row.length) {
                const statusCell = row.find('td:nth-child(5)'); // Status column
                // Update status display
                statusCell.html(this.getStatusBadge(status));
            }
        },

        /**
         * Get status badge HTML
         */
        getStatusBadge: function(status) {
            const badges = {
                'checking': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Checking...</span>',
                'update_available': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Update Available</span>',
                'up_to_date': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Up to Date</span>',
                'error': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Error</span>'
            };
            
            return badges[status] || badges['error'];
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GitUpdaterAdmin.init();
    });

    // Add custom CSS for error states
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .error {
                border-color: #dc3232 !important;
                box-shadow: 0 0 0 1px #dc3232 !important;
            }
            .giu-error-notice ul {
                margin: 0.5em 0 0 1em;
            }
            .giu-error-notice li {
                list-style-type: disc;
            }
        `)
        .appendTo('head');

})(jQuery);