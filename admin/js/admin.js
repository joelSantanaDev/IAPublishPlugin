(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Integrations Page
        if ($('#iap-add-integration').length) {
            initIntegrationsPage();
        }
        
        // Feeds Page
        if ($('#iap-add-feed').length) {
            initFeedsPage();
        }
    });
    
    function initIntegrationsPage() {
        const $modal = $('#iap-integration-modal');
        const $form = $('#iap-integration-form');
        
        $('#iap-add-integration').on('click', function() {
            $form[0].reset();
            $('#integration-id').val('');
            $('#iap-modal-title').text('Nova Integração');
            $modal.show();
        });
        
        $('.iap-edit-integration').on('click', function() {
            const id = $(this).data('id');
            const integration = iapIntegrations.find(i => i.id == id);
            
            if (integration) {
                $('#integration-id').val(integration.id);
                $('#integration-name').val(integration.name);
                $('#integration-category').val(integration.category_id);
                $('#integration-ai-provider').val(integration.ai_provider);
                
                const config = JSON.parse(integration.ai_config);
                $('#integration-api-key').val(config.api_key || '');
                $('#integration-model').val(config.model || '');
                $('#integration-temperature').val(config.temperature || 0.7);
                $('#integration-max-tokens').val(config.max_tokens || 2000);
                
                const feedIds = JSON.parse(integration.feed_ids);
                $('input[name="feed_ids[]"]').prop('checked', false);
                feedIds.forEach(feedId => {
                    $('input[name="feed_ids[]"][value="' + feedId + '"]').prop('checked', true);
                });
                
                $('#integration-custom-prompt').val(integration.custom_prompt || '');
                $('#integration-items-count').val(integration.feed_items_count || 3);
                $('#integration-feed-order').val(integration.feed_order || 'recent');
                $('#integration-status').val(integration.status);
                $('#integration-schedule').val(integration.schedule_frequency);
                
                $('#iap-modal-title').text('Editar Integração');
                $modal.show();
            }
        });
        
        $('.iap-modal-close').on('click', function() {
            $modal.hide();
        });
        
        $(window).on('click', function(e) {
            if ($(e.target).is('.iap-modal')) {
                $modal.hide();
            }
        });
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'iap_save_integration',
                nonce: iapAjax.nonce,
                id: $('#integration-id').val(),
                name: $('#integration-name').val(),
                category_id: $('#integration-category').val(),
                ai_provider: $('#integration-ai-provider').val(),
                api_key: $('#integration-api-key').val(),
                model: $('#integration-model').val(),
                temperature: $('#integration-temperature').val(),
                max_tokens: $('#integration-max-tokens').val(),
                feed_ids: $('input[name="feed_ids[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                custom_prompt: $('#integration-custom-prompt').val(),
                feed_items_count: $('#integration-items-count').val(),
                feed_order: $('#integration-feed-order').val(),
                status: $('#integration-status').val(),
                schedule_frequency: $('#integration-schedule').val()
            };
            
            $.post(iapAjax.ajax_url, formData, function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $modal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
        
        $('.iap-delete-integration').on('click', function() {
            if (!confirm('Tem certeza que deseja excluir esta integração?')) {
                return;
            }
            
            const id = $(this).data('id');
            
            $.post(iapAjax.ajax_url, {
                action: 'iap_delete_integration',
                nonce: iapAjax.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
        
        $('#iap-test-connection').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).html('Testando... <span class="iap-loading"></span>');
            
            $.post(iapAjax.ajax_url, {
                action: 'iap_test_ai_connection',
                nonce: iapAjax.nonce,
                provider: $('#integration-ai-provider').val(),
                api_key: $('#integration-api-key').val(),
                model: $('#integration-model').val()
            }, function(response) {
                $btn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
        
        $('.iap-run-integration').on('click', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const originalHtml = $btn.html();
            
            if (!confirm('Deseja executar esta integração agora?')) {
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Executando...');
            
            $.post(iapAjax.ajax_url, {
                action: 'iap_run_integration',
                nonce: iapAjax.nonce,
                id: id
            }, function(response) {
                $btn.prop('disabled', false).html(originalHtml);
                
                if (response.success) {
                    showNotice('success', response.data.message + ' - Post ID: ' + response.data.post_id);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
    }
    
    function initFeedsPage() {
        const $modal = $('#iap-feed-modal');
        const $form = $('#iap-feed-form');
        
        $('#iap-add-feed').on('click', function() {
            $form[0].reset();
            $('#feed-id').val('');
            $('#iap-feed-modal-title').text('Novo Feed');
            $modal.show();
        });
        
        $('.iap-edit-feed').on('click', function() {
            const id = $(this).data('id');
            const feed = iapFeeds.find(f => f.id == id);
            
            if (feed) {
                $('#feed-id').val(feed.id);
                $('#feed-name').val(feed.name);
                $('#feed-url').val(feed.url);
                $('#feed-status').val(feed.status);
                
                $('#iap-feed-modal-title').text('Editar Feed');
                $modal.show();
            }
        });
        
        $('.iap-modal-close').on('click', function() {
            $modal.hide();
        });
        
        $(window).on('click', function(e) {
            if ($(e.target).is('.iap-modal')) {
                $modal.hide();
            }
        });
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'iap_save_feed',
                nonce: iapAjax.nonce,
                id: $('#feed-id').val(),
                name: $('#feed-name').val(),
                url: $('#feed-url').val(),
                status: $('#feed-status').val()
            };
            
            $.post(iapAjax.ajax_url, formData, function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $modal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
        
        $('.iap-delete-feed').on('click', function() {
            if (!confirm('Tem certeza que deseja excluir este feed?')) {
                return;
            }
            
            const id = $(this).data('id');
            
            $.post(iapAjax.ajax_url, {
                action: 'iap_delete_feed',
                nonce: iapAjax.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('error', response.data.message);
                }
            });
        });
    }
    
    function showNotice(type, message) {
        const $notice = $('<div class="iap-notice iap-notice-' + type + '">' + message + '</div>');
        $('.iap-admin h1').after($notice);
        
        setTimeout(() => {
            $notice.fadeOut(() => $notice.remove());
        }, 5000);
    }

})(jQuery);
