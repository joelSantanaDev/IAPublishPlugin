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
                <th>Data/Hora</th>
                <th>Integração</th>
                <th>Ação</th>
                <th>Status</th>
                <th>Mensagem</th>
                <th>Post</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Nenhum log disponível</td>
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
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php if ($log->post_id): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($log->post_id)); ?>" target="_blank">
                                    Ver Post #<?php echo esc_html($log->post_id); ?>
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
