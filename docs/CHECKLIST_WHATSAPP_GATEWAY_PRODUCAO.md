# Checklist: WhatsApp Gateway em Produção

Este documento lista todos os arquivos e configurações necessárias para o WhatsApp Gateway funcionar em produção.

## 🔍 Verificação Rápida

Execute o script de verificação em produção:
```
https://seu-dominio.com/public/check-whatsapp-gateway-production.php
```

## 📋 Checklist Completo

### 1. Arquivos Essenciais

Certifique-se de que os seguintes arquivos existem em produção:

#### Controllers
- [ ] `src/Controllers/WhatsAppGatewaySettingsController.php`
- [ ] `src/Controllers/WhatsAppGatewayTestController.php`

#### Integrations
- [ ] `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`

#### Views
- [ ] `views/settings/whatsapp_gateway.php`
- [ ] `views/settings/whatsapp_gateway_test.php`

### 2. Rotas no Router

Verifique se as seguintes rotas estão registradas em `public/index.php` (aproximadamente linhas 509-519):

```php
// Configurações do WhatsApp Gateway
$router->get('/settings/whatsapp-gateway', 'WhatsAppGatewaySettingsController@index');
$router->post('/settings/whatsapp-gateway', 'WhatsAppGatewaySettingsController@update');
$router->post('/settings/whatsapp-gateway/test-connection', 'WhatsAppGatewaySettingsController@testConnection');

// Testes do WhatsApp Gateway
$router->get('/settings/whatsapp-gateway/test', 'WhatsAppGatewayTestController@index');
$router->post('/settings/whatsapp-gateway/test/send', 'WhatsAppGatewayTestController@sendTest');
$router->get('/settings/whatsapp-gateway/test/channels', 'WhatsAppGatewayTestController@listChannels');
$router->get('/settings/whatsapp-gateway/test/events', 'WhatsAppGatewayTestController@getEvents');
$router->get('/settings/whatsapp-gateway/test/logs', 'WhatsAppGatewayTestController@getLogs');
$router->post('/settings/whatsapp-gateway/test/webhook', 'WhatsAppGatewayTestController@simulateWebhook');
```

- [ ] Rotas estão presentes e corretas

### 3. Menu de Navegação

Verifique se o menu está configurado em `views/layout/main.php` (aproximadamente linhas 470-471):

```php
<a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-gateway') !== false && strpos($currentUri, '/settings/whatsapp-gateway/test') === false) ? 'active' : '' ?>">WhatsApp Gateway</a>
<a href="<?= pixelhub_url('/settings/whatsapp-gateway/test') ?>" class="sub-item <?= (strpos($currentUri, '/settings/whatsapp-gateway/test') !== false) ? 'active' : '' ?>" style="padding-left: 60px; font-size: 13px;">→ Testes & Logs</a>
```

E também na linha 454-455 (para ativar/expandir o menu):

```php
$configuracoesActive = $isActive(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
$configuracoesExpanded = $shouldExpand(['/billing/service-types', '/settings/hosting-providers', '/settings/whatsapp-templates', '/settings/contract-clauses', '/settings/company', '/diagnostic/financial', '/settings/asaas', '/settings/ai', '/settings/whatsapp-gateway', '/settings/communication-events', '/owner/shortcuts']);
```

- [ ] Menu "WhatsApp Gateway" está presente na seção "INTEGRAÇÕES"
- [ ] Link "→ Testes & Logs" está presente
- [ ] `/settings/whatsapp-gateway` está incluído nos arrays de `$configuracoesActive` e `$configuracoesExpanded`

### 4. Dependências

As seguintes classes devem estar disponíveis:

- [ ] `PixelHub\Core\CryptoHelper` (para criptografia do secret)
- [ ] `PixelHub\Core\Env` (para variáveis de ambiente)
- [ ] `PixelHub\Core\Auth` (para autenticação)

### 5. Variáveis de Ambiente (.env)

Variáveis opcionais (podem ser configuradas via interface):

- [ ] `WPP_GATEWAY_BASE_URL` (padrão: `https://wpp.pixel12digital.com.br`)
- [ ] `WPP_GATEWAY_SECRET` (será criptografado automaticamente)
- [ ] `PIXELHUB_WHATSAPP_WEBHOOK_URL` (opcional)
- [ ] `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` (opcional)

## 🔧 Como Sincronizar Arquivos

### Opção 1: Via Git (Recomendado)

Se estiver usando Git:

```bash
# No ambiente local
git add .
git commit -m "Adiciona WhatsApp Gateway"
git push origin main

# No servidor de produção
cd /caminho/do/projeto
git pull origin main
```

### Opção 2: Via FTP/SFTP

Faça upload manual dos seguintes arquivos:

```
src/Controllers/WhatsAppGatewaySettingsController.php
src/Controllers/WhatsAppGatewayTestController.php
src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php
views/settings/whatsapp_gateway.php
views/settings/whatsapp_gateway_test.php
public/index.php (se tiver mudanças nas rotas)
views/layout/main.php (se tiver mudanças no menu)
```

### Opção 3: Via rsync

```bash
rsync -avz --exclude='.git' \
  src/Controllers/WhatsAppGateway*.php \
  src/Integrations/WhatsAppGateway/ \
  views/settings/whatsapp_gateway*.php \
  usuario@servidor:/caminho/do/projeto/
```

## 🐛 Troubleshooting

### O menu não aparece

1. **Limpe o cache do navegador**: Ctrl+F5 ou Cmd+Shift+R
2. **Limpe o cache do PHP**: Se estiver usando opcache, reinicie o servidor ou limpe o opcache
3. **Verifique permissões**: Certifique-se de que os arquivos têm permissões corretas (644 para arquivos, 755 para diretórios)
4. **Verifique logs de erro**: Veja se há erros no log do PHP ou do servidor web

### Erro 404 ao acessar a rota

1. **Verifique se as rotas estão no index.php**: Confirme que as rotas estão registradas
2. **Verifique o .htaccess**: Se estiver usando Apache, verifique se o .htaccess está redirecionando corretamente
3. **Verifique BASE_PATH**: Confirme que a constante BASE_PATH está definida corretamente

### Controller não encontrado

1. **Verifique o autoload**: Certifique-se de que o autoload está funcionando (Composer ou manual)
2. **Verifique namespaces**: Confirme que os namespaces estão corretos (`PixelHub\Controllers`)
3. **Verifique permissões**: Certifique-se de que o servidor pode ler os arquivos

### Secret não está sendo salvo

1. **Verifique CryptoHelper**: Certifique-se de que a classe está disponível
2. **Verifique permissões do .env**: O servidor precisa ter permissão de escrita no arquivo .env
3. **Verifique logs**: Veja se há erros ao salvar

## ✅ Checklist Final

Antes de considerar a sincronização completa:

- [ ] Todos os arquivos foram enviados para produção
- [ ] Rotas estão registradas em `public/index.php`
- [ ] Menu está configurado em `views/layout/main.php`
- [ ] Script de verificação (`check-whatsapp-gateway-production.php`) mostra 0 erros
- [ ] Cache foi limpo (navegador e servidor)
- [ ] Acesso a `/settings/whatsapp-gateway` funciona sem erro 404
- [ ] O menu "WhatsApp Gateway" aparece na interface

## 📞 Próximos Passos

Após confirmar que tudo está sincronizado:

1. Acesse `/settings/whatsapp-gateway` em produção
2. Configure a Base URL do gateway
3. Configure o Secret (será criptografado automaticamente)
4. Teste a conexão usando o botão "Testar Conexão"
5. Configure o Webhook (opcional)

---

**Última atualização**: Janeiro 2025

