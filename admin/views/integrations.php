<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap iap-admin">
    <h1>Integrações de IA</h1>
    
    <button class="button button-primary" id="iap-add-integration">
        <span class="dashicons dashicons-plus-alt"></span> Nova Integração
    </button>
    
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Provedor IA</th>
                <th>Feeds</th>
                <th>Status</th>
                <th>Última Execução</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($integrations)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">Nenhuma integração configurada</td>
                </tr>
            <?php else: ?>
                <?php foreach ($integrations as $integration): ?>
                    <?php
                    $category = get_category($integration->category_id);
                    $feed_ids = json_decode($integration->feed_ids, true);
                    $feed_count = is_array($feed_ids) ? count($feed_ids) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($integration->name); ?></strong></td>
                        <td><?php echo $category ? esc_html($category->name) : 'N/A'; ?></td>
                        <td><?php echo esc_html($ai_providers[$integration->ai_provider] ?? $integration->ai_provider); ?></td>
                        <td><?php echo $feed_count; ?> feed(s)</td>
                        <td>
                            <span class="iap-status iap-status-<?php echo esc_attr($integration->status); ?>">
                                <?php echo esc_html(ucfirst($integration->status)); ?>
                            </span>
                        </td>
                        <td><?php echo $integration->last_run ? esc_html($integration->last_run) : 'Nunca'; ?></td>
                        <td>
                            <button class="button button-small iap-run-integration" data-id="<?php echo esc_attr($integration->id); ?>">
                                <span class="dashicons dashicons-controls-play"></span> Executar
                            </button>
                            <button class="button button-small iap-edit-integration" data-id="<?php echo esc_attr($integration->id); ?>">
                                <span class="dashicons dashicons-edit"></span> Editar
                            </button>
                            <button class="button button-small iap-delete-integration" data-id="<?php echo esc_attr($integration->id); ?>">
                                <span class="dashicons dashicons-trash"></span> Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="iap-integration-modal" class="iap-modal" style="display: none;">
    <div class="iap-modal-content">
        <span class="iap-modal-close">&times;</span>
        <h2 id="iap-modal-title">Nova Integração</h2>
        
        <form id="iap-integration-form">
            <input type="hidden" id="integration-id" name="id">
            
            <table class="form-table">
                <tr>
                    <th><label for="integration-name">Nome da Integração</label></th>
                    <td>
                        <input type="text" id="integration-name" name="name" class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-category">Categoria</label></th>
                    <td>
                        <select id="integration-category" name="category_id" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-ai-provider">Provedor de IA</label></th>
                    <td>
                        <select id="integration-ai-provider" name="ai_provider" required>
                            <option value="">Selecione um provedor</option>
                            <?php foreach ($ai_providers as $key => $name): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-api-key">API Key</label></th>
                    <td>
                        <input type="password" id="integration-api-key" name="api_key" class="regular-text" required>
                        <p class="description">Sua chave de API será armazenada de forma segura</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-model">Modelo</label></th>
                    <td>
                        <input type="text" id="integration-model" name="model" class="regular-text" placeholder="Ex: gpt-4o-mini">
                        <p class="description">Deixe em branco para usar o modelo padrão</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-temperature">Temperature</label></th>
                    <td>
                        <input type="number" id="integration-temperature" name="temperature" step="0.1" min="0" max="2" value="0.7">
                        <p class="description">Controla a criatividade (0-2)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-max-tokens">Max Tokens</label></th>
                    <td>
                        <input type="number" id="integration-max-tokens" name="max_tokens" value="2000">
                        <p class="description">Tamanho máximo da resposta</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label>Feeds RSS</label></th>
                    <td>
                        <div id="integration-feeds">
                            <?php foreach ($feeds as $feed): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="feed_ids[]" value="<?php echo esc_attr($feed->id); ?>">
                                    <?php echo esc_html($feed->name); ?> (<?php echo esc_html($feed->url); ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-status">Status</label></th>
                    <td>
                        <select id="integration-status" name="status">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="integration-schedule">Frequência</label></th>
                    <td>
                        <select id="integration-schedule" name="schedule_frequency">
                            <option value="hourly">A cada hora</option>
                            <option value="twicedaily">Duas vezes ao dia</option>
                            <option value="daily">Diariamente</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" id="iap-test-connection" class="button">Testar Conexão</button>
                <button type="submit" class="button button-primary">Salvar Integração</button>
            </p>
        </form>
    </div>
</div>

<script>
var iapIntegrations = <?php echo json_encode($integrations); ?>;
var iapCategories = <?php echo json_encode($categories); ?>;
var iapProviders = <?php echo json_encode($ai_providers); ?>;
var iapFeeds = <?php echo json_encode($feeds); ?>;
</script>
