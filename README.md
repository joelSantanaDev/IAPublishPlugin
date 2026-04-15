# IA Publish Plugin

Plugin WordPress para alimentação automática de notícias com Inteligência Artificial a partir de feeds RSS.

## 📋 Descrição

O IA Publish Plugin permite criar notícias originais automaticamente usando IA, baseando-se em múltiplas fontes de feeds RSS. O plugin combina informações de diferentes feeds e utiliza provedores de IA para gerar conteúdo único e relevante.

## ✨ Funcionalidades

- **Múltiplos Provedores de IA**: Suporte para OpenAI, Anthropic (Claude), Google (Gemini) e Groq
- **Gerenciamento de Feeds RSS**: Adicione e gerencie feeds de diferentes fontes
- **Integrações Personalizadas**: Configure integrações específicas por categoria
- **Execução Automática**: Agende a geração automática de conteúdo (horária, diária, etc.)
- **Logs Detalhados**: Acompanhe todas as ações e gerações de conteúdo
- **Interface Intuitiva**: Painel administrativo completo no WordPress

## 🚀 Instalação

1. Faça upload da pasta `IAPublishPlugin` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse 'IA Publish' no menu administrativo

## ⚙️ Configuração

### 1. Adicionar Feeds RSS

1. Acesse **IA Publish > Feeds RSS**
2. Clique em **Novo Feed**
3. Preencha o nome e URL do feed
4. Salve

Feeds padrão já incluídos:
- InfoMoney
- InvestNews
- Valor Econômico
- G1 Economia

### 2. Criar uma Integração

1. Acesse **IA Publish > Integrações**
2. Clique em **Nova Integração**
3. Configure:
   - **Nome**: Identificação da integração
   - **Categoria**: Categoria WordPress onde os posts serão criados
   - **Provedor de IA**: Escolha entre OpenAI, Anthropic, Google ou Groq
   - **API Key**: Sua chave de API do provedor escolhido
   - **Modelo**: Modelo específico (ex: gpt-4o-mini, claude-3-5-haiku-20241022)
   - **Temperature**: Controle de criatividade (0-2)
   - **Max Tokens**: Tamanho máximo da resposta
   - **Feeds RSS**: Selecione os feeds que serão usados como fonte
   - **Status**: Ativo/Inativo
   - **Frequência**: Horária, duas vezes ao dia ou diariamente

### 3. Testar Conexão

Antes de salvar, use o botão **Testar Conexão** para verificar se suas credenciais de IA estão corretas.

### 4. Executar Integração

- **Manual**: Clique em "Executar" na lista de integrações
- **Automática**: O plugin executará automaticamente conforme a frequência configurada

## 🔑 Provedores de IA Suportados

### OpenAI
- Modelos: gpt-4o, gpt-4o-mini, gpt-4-turbo, etc.
- API: https://platform.openai.com/api-keys

### Anthropic (Claude)
- Modelos: claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022, etc.
- API: https://console.anthropic.com/

### Google (Gemini)
- Modelos: gemini-2.0-flash-exp, gemini-1.5-pro, etc.
- API: https://aistudio.google.com/app/apikey

### Groq
- Modelos: llama-3.3-70b-versatile, mixtral-8x7b-32768, etc.
- API: https://console.groq.com/keys

## 📊 Estrutura do Banco de Dados

O plugin cria 3 tabelas:

- `wp_iap_integrations`: Armazena configurações de integrações
- `wp_iap_feeds`: Gerencia feeds RSS
- `wp_iap_logs`: Registra todas as ações e execuções

## 🔒 Segurança

- API Keys são armazenadas de forma segura no banco de dados
- Validação de permissões (apenas administradores)
- Sanitização de todos os inputs
- Nonces para proteção AJAX

## 📝 Logs

Acesse **IA Publish > Logs** para visualizar:
- Histórico de execuções
- Posts criados
- Erros e mensagens
- Status de cada ação

## 🛠️ Desenvolvimento

### Estrutura de Arquivos

```
IAPublishPlugin/
├── admin/
│   ├── class-iap-admin.php
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── views/
│       ├── integrations.php
│       ├── feeds.php
│       └── logs.php
├── includes/
│   ├── class-iap-activator.php
│   ├── class-iap-deactivator.php
│   ├── class-iap-core.php
│   ├── class-iap-loader.php
│   ├── class-iap-ai-manager.php
│   ├── class-iap-feed-manager.php
│   └── class-iap-integration-manager.php
├── ia-publish-plugin.php
└── README.md
```

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

## 📄 Licença

GPL v2 or later

## 👨‍💻 Autor

Joel Santana - [GitHub](https://github.com/joelSantanaDev)

## 🔄 Changelog

### 1.0.0
- Lançamento inicial
- Suporte para 4 provedores de IA
- Sistema de feeds RSS
- Interface administrativa completa
- Sistema de logs
- Execução automática agendada
