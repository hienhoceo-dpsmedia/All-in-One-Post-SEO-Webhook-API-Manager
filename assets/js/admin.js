// Admin JavaScript for AIPSWAM Plugin
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        aipswamAdminInit();
    });

    function aipswamAdminInit() {
        // Initialize tabs
        initTabs();

        // Test webhook connection
        $('#aipswam-test-webhook').on('click', function(e) {
            e.preventDefault();
            testWebhookConnection();
        });

        // Manual webhook trigger
        $('#aipswam-trigger-manual').on('click', function(e) {
            e.preventDefault();
            triggerManualWebhook();
        });

        // Load posts for manual trigger
        loadPostsForManualTrigger();
    }

    function initTabs() {
        // Handle tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();

            var target = $(this).attr('href');

            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Update active pane
            $('.tab-pane').removeClass('active');
            $(target).addClass('active');
        });

        // Handle hash in URL
        if (window.location.hash) {
            var hash = window.location.hash;
            $('.nav-tab[href="' + hash + '"]').click();
        }
    }

    function testWebhookConnection() {
        $('#aipswam-test-webhook').prop('disabled', true).text('Testing...');

        $.ajax({
            url: aipswamAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aipswam_test_webhook',
                nonce: aipswamAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#aipswam-test-result')
                        .removeClass('error')
                        .addClass('success')
                        .html('✓ ' + response.data.message +
                              '<br>Response Code: ' + response.data.response_code +
                              '<br><small>Response: ' + JSON.stringify(response.data.response_body) + '</small>');
                } else {
                    $('#aipswam-test-result')
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                $('#aipswam-test-result')
                    .removeClass('success')
                    .addClass('error')
                    .html('✗ Connection test failed: ' + error);
            },
            complete: function() {
                $('#aipswam-test-webhook').prop('disabled', false).text('Test Webhook Connection');
            }
        });
    }

    function loadPostsForManualTrigger() {
        $.ajax({
            url: aipswamAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aipswam_get_posts',
                nonce: aipswamAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">' + (aipswamAdmin.strings ? aipswamAdmin.strings.selectPost : 'Select a post...') + '</option>';
                    $.each(response.data, function(i, post) {
                        options += '<option value="' + post.id + '">' +
                                  post.title + ' (' + post.type + ' - ' + post.status + ')</option>';
                    });
                    $('#aipswam-post-select').html(options);
                }
            }
        });
    }

    function triggerManualWebhook() {
        var postId = $('#aipswam-post-select').val();

        if (!postId) {
            alert('Please select a post first.');
            return;
        }

        $('#aipswam-trigger-manual').prop('disabled', true).text('Sending...');

        $.ajax({
            url: aipswamAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aipswam_trigger_manual',
                post_id: postId,
                nonce: aipswamAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#aipswam-trigger-result')
                        .removeClass('error')
                        .addClass('success')
                        .html('✓ ' + response.data.message);
                } else {
                    $('#aipswam-trigger-result')
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                $('#aipswam-trigger-result')
                    .removeClass('success')
                    .addClass('error')
                    .html('✗ Trigger failed: ' + error);
            },
            complete: function() {
                $('#aipswam-trigger-manual').prop('disabled', false).text('Send Webhook');
            }
        });
    }

    function copyToClipboard(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

})(jQuery);