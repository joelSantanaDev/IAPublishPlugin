<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap iap-admin">
    <h1>Feeds RSS</h1>
    
    <button class="button button-primary" id="iap-add-feed">
        <span class="dashicons dashicons-plus-alt"></span> Novo Feed
    </button>
    
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Nome</th>
                <th>URL</th>
                <th>Status</th>
                <th>Última Atualização</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($feeds)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Nenhum feed configurado</td>
                </tr>
            <?php else: ?>
                <?php foreach ($feeds as $feed): ?>
                    <tr>
                        <td><strong><?php echo esc_html($feed->name); ?></strong></td>
                        <td><a href="<?php echo esc_url($feed->url); ?>" target="_blank"><?php echo esc_html($feed->url); ?></a></td>
                        <td>
                            <span class="iap-status iap-status-<?php echo esc_attr($feed->status); ?>">
                                <?php echo esc_html(ucfirst($feed->status)); ?>
                            </span>
                        </td>
                        <td><?php echo $feed->last_fetch ? esc_html($feed->last_fetch) : 'Nunca'; ?></td>
                        <td>
                            <button class="button button-small iap-edit-feed" data-id="<?php echo esc_attr($feed->id); ?>">
                                <span class="dashicons dashicons-edit"></span> Editar
                            </button>
                            <button class="button button-small iap-delete-feed" data-id="<?php echo esc_attr($feed->id); ?>">
                                <span class="dashicons dashicons-trash"></span> Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="iap-feed-modal" class="iap-modal" style="display: none;">
    <div class="iap-modal-content">
        <span class="iap-modal-close">&times;</span>
        <h2 id="iap-feed-modal-title">Novo Feed</h2>
        
        <form id="iap-feed-form">
            <input type="hidden" id="feed-id" name="id">
            
            <table class="form-table">
                <tr>
                    <th><label for="feed-name">Nome do Feed</label></th>
                    <td>
                        <input type="text" id="feed-name" name="name" class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="feed-url">URL do Feed RSS</label></th>
                    <td>
                        <input type="url" id="feed-url" name="url" class="regular-text" required>
                        <p class="description">URL completa do feed RSS (ex: https://exemplo.com/feed/)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="feed-status">Status</label></th>
                    <td>
                        <select id="feed-status" name="status">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Salvar Feed</button>
            </p>
        </form>
    </div>
</div>

<script>
var iapFeeds = <?php echo json_encode($feeds); ?>;
</script>
