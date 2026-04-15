<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap iap-admin">
    <h1>Debug - Logs da API</h1>
    
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
        <h2>Como visualizar os logs:</h2>
        
        <h3>Opção 1: Debug Log do WordPress</h3>
        <p>Os logs estão sendo gravados no arquivo de debug do WordPress. Para visualizar:</p>
        <ol>
            <li>Certifique-se que o debug está ativado no <code>wp-config.php</code>:
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px;">define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
            </li>
            <li>Os logs estarão em: <code><?php echo ABSPATH; ?>wp-content/debug.log</code></li>
            <li>Procure por linhas que começam com <code>=== IAP API Response</code></li>
        </ol>
        
        <h3>Opção 2: Visualizar aqui (últimas 100 linhas)</h3>
        <button id="iap-load-debug-log" class="button button-primary">Carregar Debug Log</button>
        <button id="iap-clear-debug-log" class="button">Limpar Debug Log</button>
        
        <div id="iap-debug-content" style="margin-top: 20px; display: none;">
            <h4>Conteúdo do debug.log:</h4>
            <textarea readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 15px;"></textarea>
        </div>
    </div>
    
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
        <h2>Listar Modelos Disponíveis (Gemini)</h2>
        <p>Veja quais modelos estão disponíveis na sua API Key do Google:</p>
        
        <table class="form-table">
            <tr>
                <th><label for="list-api-key">API Key do Google</label></th>
                <td>
                    <input type="password" id="list-api-key" class="regular-text">
                    <button id="iap-list-models" class="button button-primary" style="margin-left: 10px;">Listar Modelos</button>
                </td>
            </tr>
        </table>
        
        <div id="iap-models-result" style="display: none; margin-top: 20px;">
            <h3>Modelos Disponíveis:</h3>
            <div id="iap-models-content" style="background: #f5f5f5; padding: 15px; border-radius: 3px; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
    
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
        <h2>Teste Manual da API</h2>
        <p>Use este formulário para testar diretamente a conexão com a API do Gemini:</p>
        
        <form id="iap-test-api-form">
            <table class="form-table">
                <tr>
                    <th><label for="test-provider">Provedor</label></th>
                    <td>
                        <select id="test-provider" name="provider">
                            <option value="google">Google (Gemini)</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="groq">Groq</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="test-api-key">API Key</label></th>
                    <td>
                        <input type="password" id="test-api-key" name="api_key" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="test-model">Modelo</label></th>
                    <td>
                        <input type="text" id="test-model" name="model" class="regular-text" placeholder="gemini-2.0-flash">
                        <p class="description" id="test-model-hint">Deixe em branco para usar o padrão</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Testar Conexão</button>
            </p>
        </form>
        
        <div id="iap-test-result" style="display: none; margin-top: 20px;">
            <h3>Resultado do Teste:</h3>
            <div id="iap-test-result-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#iap-load-debug-log').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Carregando...');
        
        $.post(iapAjax.ajax_url, {
            action: 'iap_get_debug_log',
            nonce: iapAjax.nonce
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            if (response.success) {
                $('#iap-debug-content textarea').val(response.data.content);
                $('#iap-debug-content').show();
            } else {
                alert('Erro: ' + response.data.message);
            }
        });
    });
    
    $('#iap-clear-debug-log').on('click', function() {
        if (!confirm('Tem certeza que deseja limpar o debug log?')) {
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Limpando...');
        
        $.post(iapAjax.ajax_url, {
            action: 'iap_clear_debug_log',
            nonce: iapAjax.nonce
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            if (response.success) {
                $('#iap-debug-content textarea').val('');
                alert('Debug log limpo com sucesso!');
            } else {
                alert('Erro: ' + response.data.message);
            }
        });
    });
    
    $('#iap-list-models').on('click', function() {
        const apiKey = $('#list-api-key').val();
        
        if (!apiKey) {
            alert('Por favor, insira sua API Key do Google');
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Listando...');
        
        $.post(iapAjax.ajax_url, {
            action: 'iap_list_gemini_models',
            nonce: iapAjax.nonce,
            api_key: apiKey
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            const $result = $('#iap-models-content');
            
            if (response.success) {
                $result.html('<pre style="margin: 0;">' + response.data.models + '</pre>');
            } else {
                $result.html('<div class="iap-notice iap-notice-error">' + response.data.message + '</div>');
            }
            
            $('#iap-models-result').show();
        });
    });
    
    // Atualizar placeholder do modelo baseado no provedor
    const modelPlaceholders = {
        'google': 'gemini-2.0-flash',
        'openai': 'gpt-4o-mini',
        'anthropic': 'claude-3-5-haiku-20241022',
        'groq': 'llama-3.3-70b-versatile'
    };
    
    const modelHints = {
        'google': 'Exemplos: gemini-2.0-flash, gemini-2.5-flash, gemini-2.5-pro',
        'openai': 'Exemplos: gpt-4o-mini, gpt-4o, gpt-4-turbo',
        'anthropic': 'Exemplos: claude-3-5-haiku-20241022, claude-3-5-sonnet-20241022',
        'groq': 'Exemplos: llama-3.3-70b-versatile, mixtral-8x7b-32768'
    };
    
    $('#test-provider').on('change', function() {
        const provider = $(this).val();
        $('#test-model').attr('placeholder', modelPlaceholders[provider] || '');
        $('#test-model-hint').text(modelHints[provider] || 'Deixe em branco para usar o padrão');
    }).trigger('change');
    
    $('#iap-test-api-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Testando...');
        
        $.post(iapAjax.ajax_url, {
            action: 'iap_test_ai_connection',
            nonce: iapAjax.nonce,
            provider: $('#test-provider').val(),
            api_key: $('#test-api-key').val(),
            model: $('#test-model').val()
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            const $result = $('#iap-test-result-content');
            
            if (response.success) {
                $result.html('<div class="iap-notice iap-notice-success">' + response.data.message + '</div>');
            } else {
                $result.html('<div class="iap-notice iap-notice-error"><strong>Erro:</strong> ' + response.data.message + '</div>');
            }
            
            $('#iap-test-result').show();
            
            // Recarregar log automaticamente após o teste
            setTimeout(function() {
                $('#iap-load-debug-log').click();
            }, 500);
        });
    });
});
</script>
