<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap iap-admin">
    <h1>Configurações Gerais</h1>
    
    <div class="iap-settings-container">
        <form id="iap-settings-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="global-prompt">Prompt Global</label>
                    </th>
                    <td>
                        <textarea 
                            id="global-prompt" 
                            name="global_prompt" 
                            rows="20" 
                            class="large-text code"
                            style="font-family: monospace; width: 100%;"
                        ><?php echo esc_textarea($global_prompt); ?></textarea>
                        
                        <p class="description">
                            <strong>Este é o prompt base usado por todas as integrações.</strong><br>
                            Personalize de acordo com o nicho do seu site (finanças, tecnologia, esportes, etc).<br><br>
                            
                            <strong>Dicas:</strong><br>
                            • Defina o tom de voz (jornalístico, informal, técnico, etc)<br>
                            • Especifique o tamanho mínimo do conteúdo<br>
                            • Indique quais tags HTML usar<br>
                            • Mantenha o formato de resposta (TÍTULO, META_TÍTULO, etc) para o plugin funcionar corretamente<br><br>
                            
                            <strong>Cada integração pode ter um prompt adicional</strong> que complementa este prompt global.
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Salvar Configurações</button>
                <button type="button" id="restore-default-prompt" class="button">Restaurar Padrão</button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#iap-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true).text('Salvando...');
        
        $.post(iapAjax.ajax_url, {
            action: 'iap_save_settings',
            nonce: iapAjax.nonce,
            global_prompt: $('#global-prompt').val()
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
            } else {
                alert('Erro: ' + response.data.message);
            }
        }).always(function() {
            $submitBtn.prop('disabled', false).text('Salvar Configurações');
        });
    });
    
    $('#restore-default-prompt').on('click', function() {
        if (!confirm('Tem certeza que deseja restaurar o prompt padrão? Suas alterações serão perdidas.')) {
            return;
        }
        
        const defaultPrompt = `Instruções:
- Crie um título original e atraente
- Sugira 5 tags relevantes (palavras-chave principais do tema)
- Escreva o conteúdo com mínimo 600 palavras em HTML
- Use títulos HTML(H1,H2,H3) para seções, <p> para parágrafos, <ul>/<ol> para listas, <strong> para destaques. Preciso que a semântica pro SEO seja importante.
- Tom jornalístico profissional
- Combine as fontes de forma coerente caso você consuma mais de 1 feed
- Conteúdo 100% original, mas não viaje tanto na criatividade.

⚠️ IMPORTANTE - Formato de Resposta:

TÍTULO: [apenas o título, sem repetir no conteúdo]

META_TÍTULO: [versão otimizada do título para SEO, máximo 60 caracteres]

META_DESCRIÇÃO: [resumo atraente do conteúdo para SEO, máximo 160 caracteres]

FOCUS_KEYWORD: [palavra-chave principal do artigo, 1-3 palavras]

TAGS: [tag1, tag2, tag3, tag4, tag5]

CONTEÚDO:
[Comece direto com o HTML do conteúdo. NÃO repita o título aqui. NÃO inclua as tags aqui. Apenas o corpo do artigo em HTML]`;
        
        $('#global-prompt').val(defaultPrompt);
    });
});
</script>

<style>
.iap-settings-container {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.iap-settings-container textarea {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 10px;
}

.iap-settings-container .description {
    margin-top: 10px;
    line-height: 1.6;
}
</style>
