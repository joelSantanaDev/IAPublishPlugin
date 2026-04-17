# Changelog - IA Publish Plugin

## [1.1.0] - 2026-04-17

### ✨ Novas Funcionalidades

#### SEO Automático com Rank Math
- **Meta Título**: Gerado automaticamente (máx 60 caracteres)
- **Meta Descrição**: Extraída do conteúdo (máx 160 caracteres)
- **Focus Keyword**: Palavras-chave principais do título
- **Tags Automáticas**: Até 10 tags relevantes por post
- **Alt Text para Imagens**: Otimizado para SEO e acessibilidade
- **Open Graph**: Imagens para Facebook e Twitter
- **Pillar Content**: Posts marcados como conteúdo pilar no Rank Math

#### Sistema de Tags Inteligente
- Extração de palavras-chave do título (3 tags)
- Extração de palavras-chave do conteúdo (5 tags)
- Extração de tags das notícias fonte (2 tags por fonte)
- Filtro de stop words em português
- Remoção de duplicatas e ordenação por relevância
- Limite de 10 tags por post

#### Agendamento Individual por Integração
- Cada integração tem seu próprio cron job
- Respeita configuração individual (hourly, twicedaily, daily)
- Agendamento criado/atualizado ao salvar integração
- Agendamento removido ao deletar ou desativar integração
- Logs detalhados de agendamento

### 🔧 Melhorias

#### Imagens
- Retorna ID da imagem ao importar
- Adiciona título, legenda e descrição
- Alt text otimizado para SEO
- Limpeza de query strings para alta qualidade

#### Logs
- Logs de tags geradas
- Logs de SEO aplicado
- Logs de agendamento criado/removido
- Informação de keywords no log

### 🐛 Correções

#### Agendamento
- **Antes**: Todas integrações executavam a cada hora (fixo)
- **Agora**: Cada integração respeita sua frequência configurada
- Removido agendamento global do activator
- Implementado sistema de hooks individuais

### 📝 Arquivos Modificados

#### `includes/class-iap-integration-manager.php`
- Adicionado `generate_tags()` - Geração automática de tags
- Adicionado `extract_keywords_from_text()` - Extração de palavras-chave
- Adicionado `add_seo_meta()` - Meta dados Rank Math
- Adicionado `generate_meta_title()` - Meta título SEO
- Adicionado `generate_meta_description()` - Meta descrição SEO
- Adicionado `extract_focus_keyword()` - Focus keyword automático
- Adicionado `add_image_seo()` - SEO para imagens
- Adicionado `generate_image_alt()` - Alt text otimizado
- Adicionado `schedule_integration()` - Agendamento individual
- Modificado `import_featured_image()` - Retorna ID da imagem
- Modificado `run_integration()` - Gera tags e aplica SEO
- Modificado `delete_integration()` - Remove agendamento

#### `includes/class-iap-core.php`
- Adicionado `register_integration_hooks()` - Registra hooks individuais
- Adicionado `run_single_integration()` - Executa integração específica
- Modificado `define_public_hooks()` - Suporte a hooks dinâmicos

#### `includes/class-iap-activator.php`
- Removido agendamento global fixo
- Adicionado comentário explicativo sobre agendamento individual

### 🎯 Funcionalidades Completas

#### Posts Gerados Incluem:
- ✅ Título otimizado
- ✅ Conteúdo em HTML (Markdown convertido)
- ✅ Categoria configurada
- ✅ Tags automáticas (até 10)
- ✅ Imagem destacada importada
- ✅ Meta título SEO (Rank Math)
- ✅ Meta descrição SEO (Rank Math)
- ✅ Focus keyword (Rank Math)
- ✅ Alt text nas imagens
- ✅ Open Graph (Facebook/Twitter)
- ✅ Robots meta (index, follow)
- ✅ Pillar content (Rank Math)
- ✅ Rastreamento de fontes nos logs
- ✅ Anti-duplicatas (itens processados)

#### Configurações por Integração:
- ✅ Nome e categoria
- ✅ Provedor de IA (OpenAI, Anthropic, Google, Groq)
- ✅ Configurações de IA (model, temperature, max_tokens)
- ✅ Feeds RSS selecionados
- ✅ Prompt personalizado
- ✅ Quantidade de notícias (1-10)
- ✅ Ordem de seleção (recent/random)
- ✅ Status (active/inactive)
- ✅ Frequência (hourly/twicedaily/daily)

### 📊 Compatibilidade

- WordPress 5.0+
- PHP 7.4+
- Rank Math SEO (opcional, mas recomendado)
- Provedores de IA suportados:
  - OpenAI (GPT-4, GPT-4o, etc.)
  - Anthropic (Claude 3.5, etc.)
  - Google Gemini (2.0 Flash, 1.5 Pro, etc.)
  - Groq (Llama 3.3, Mixtral, etc.)

### 🔄 Migração

Se você já tinha o plugin instalado:

1. **Desative e reative o plugin** para aplicar migrações
2. **Ou execute o SQL** do arquivo `UPDATE.sql`
3. **Edite suas integrações** para configurar:
   - Quantidade de notícias
   - Ordem de seleção
   - Frequência de execução

### 📝 Notas

- Tags são geradas automaticamente, mas podem ser editadas manualmente
- SEO só é aplicado se Rank Math estiver instalado
- Imagens são importadas para a biblioteca de mídia do WordPress
- Agendamento usa o WP-Cron (requer tráfego no site ou cron real)

---

## [1.0.0] - 2026-04-16

### 🎉 Lançamento Inicial

- Suporte para 4 provedores de IA
- Sistema de feeds RSS
- Interface administrativa completa
- Sistema de logs detalhados
- Execução automática agendada
- Prompt customizável por integração
- Controle de quantidade de notícias
- Seleção de ordem (recente/aleatório)
- Sistema anti-duplicatas
- Importação automática de imagens
- Conversão Markdown para HTML
- Rastreamento de fontes nos logs
- Página de debug integrada
- Migração automática de banco de dados
