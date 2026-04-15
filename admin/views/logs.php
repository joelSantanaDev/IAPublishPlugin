<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap iap-admin">
    <h1>Logs de Atividade</h1>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 140px;">Data/Hora</th>
                <th style="width: 150px;">Integração</th>
                <th style="width: 120px;">Ação</th>
                <th style="width: 80px;">Status</th>
                <th>Mensagem</th>
                <th style="width: 100px;">Post</th>
                <th style="width: 80px;">Detalhes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">Nenhum log disponível</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td>
                            <?php
                            if ($log->integration_id) {
                                global $wpdb;
                                $integration = $wpdb->get_row($wpdb->prepare(
                                    "SELECT name FROM {$wpdb->prefix}iap_integrations WHERE id = %d",
                                    $log->integration_id
                                ));
                                echo $integration ? esc_html($integration->name) : 'N/A';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td>
                            <span class="iap-status iap-status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $message_lines = explode("\n", $log->message);
                            echo esc_html($message_lines[0]); 
                            ?>
                        </td>
                        <td>
                            <?php if ($log->post_id): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($log->post_id)); ?>" target="_blank">
                                    Ver Post #<?php echo esc_html($log->post_id); ?>
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($log->sources) || strlen($log->message) > 100): ?>
                                <button class="button button-small iap-view-log-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                    Ver Fontes
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($log->sources)): ?>
                        <tr class="iap-log-details" id="iap-log-details-<?php echo esc_attr($log->id); ?>" style="display: none;">
                            <td colspan="7" style="background: #f9f9f9; padding: 20px;">
                                <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                    <h3 style="margin-top: 0;">Detalhes do Log</h3>
                                    
                                    <div style="margin-bottom: 20px;">
                                        <strong>Mensagem Completa:</strong>
                                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; white-space: pre-wrap;"><?php echo esc_html($log->message); ?></pre>
                                    </div>
                                    
                                    <?php
                                    $sources = json_decode($log->sources, true);
                                    if ($sources && is_array($sources)):
                                    ?>
                                        <div>
                                            <strong>Notícias Fonte Utilizadas:</strong>
                                            <div style="margin-top: 10px;">
                                                <?php foreach ($sources as $index => $source): ?>
                                                    <div style="background: #fafafa; padding: 12px; margin-bottom: 10px; border-left: 3px solid #0073aa; border-radius: 3px;">
                                                        <div style="margin-bottom: 5px;">
                                                            <strong>Fonte <?php echo ($index + 1); ?>:</strong> 
                                                            <span style="color: #666;"><?php echo esc_html($source['feed_name']); ?></span>
                                                        </div>
                                                        <div style="margin-bottom: 5px;">
                                                            <strong>Título:</strong> 
                                                            <?php if (!empty($source['link'])): ?>
                                                                <a href="<?php echo esc_url($source['link']); ?>" target="_blank" style="text-decoration: none;">
                                                                    <?php echo esc_html($source['title']); ?> 
                                                                    <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
                                                                </a>
                                                            <?php else: ?>
                                                                <?php echo esc_html($source['title']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (isset($source['date'])): ?>
                                                            <div style="font-size: 12px; color: #999;">
                                                                Data: <?php echo esc_html($source['date']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('.iap-view-log-details').on('click', function() {
        const logId = $(this).data('log-id');
        const $details = $('#iap-log-details-' + logId);
        
        if ($details.is(':visible')) {
            $details.hide();
            $(this).text('Ver Fontes');
        } else {
            $('.iap-log-details').hide();
            $('.iap-view-log-details').text('Ver Fontes');
            $details.show();
            $(this).text('Ocultar');
        }
    });
});
</script>
</div>
