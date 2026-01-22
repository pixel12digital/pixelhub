# Guia Rápido: Verificar WhatsApp Gateway em Produção

## ✅ Solução Rápida

### Opção 1: Verificar via Rota do Sistema (Recomendado)

Acesse diretamente no navegador:
```
https://hub.pixel12digital.com.br/settings/whatsapp-gateway/check
```

Esta rota está integrada ao sistema e funciona mesmo se os arquivos ainda não estiverem sincronizados.

### Opção 2: Verificar Manualmente

Se a rota acima não funcionar, significa que os arquivos não estão em produção. Siga os passos abaixo:

## 📋 Checklist de Arquivos para Sincronizar

Certifique-se de que os seguintes arquivos existem em produção:

### Controllers
- [ ] `src/Controllers/WhatsAppGatewaySettingsController.php`
- [ ] `src/Controllers/WhatsAppGatewayTestController.php`

### Integrations
- [ ] `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`

### Views
- [ ] `views/settings/whatsapp_gateway.php`
- [ ] `views/settings/whatsapp_gateway_test.php`

### Configurações
- [ ] `public/index.php` (deve ter as rotas nas linhas 509-519)
- [ ] `views/layout/main.php` (deve ter o menu nas linhas 470-471)

## 🚀 Como Sincronizar

### Via cPanel Git (Recomendado)

1. Acesse o cPanel da HostMídia
2. Vá em **Tools** → **Git™ Version Control**
3. Clique em **Manage Repository** para `hub.pixel12digital.com.br`
4. Clique em **"Update from Remote"** (ícone de nuvem com seta para baixo)
5. Clique em **"Deploy HEAD Commit"**
6. Aguarde confirmação de sucesso

### Via FTP/SFTP

Faça upload manual dos arquivos listados acima para o servidor.

## 🔍 Verificação Pós-Deploy

Após sincronizar os arquivos:

1. **Limpe o cache do navegador**: Ctrl+F5 ou Cmd+Shift+R
2. **Acesse a rota de verificação**:
   ```
   https://hub.pixel12digital.com.br/settings/whatsapp-gateway/check
   ```
3. **Verifique se aparecem 0 erros**
4. **Acesse o menu**: Vá em **Configurações** → **INTEGRAÇÕES** → **WhatsApp Gateway**
5. **Se ainda não aparecer**: Limpe o cache do PHP (opcache) ou reinicie o servidor web

## ⚠️ Problemas Comuns

### Menu não aparece após deploy

**Solução:**
1. Limpar cache do navegador (Ctrl+F5)
2. Verificar se o arquivo `views/layout/main.php` tem as linhas 470-471
3. Verificar permissões dos arquivos (644 para arquivos, 755 para diretórios)

### Erro 404 ao acessar `/settings/whatsapp-gateway`

**Solução:**
1. Verificar se as rotas estão em `public/index.php` (linhas 509-519)
2. Verificar se os controllers existem em `src/Controllers/`

### Erro "Controller não encontrado"

**Solução:**
1. Verificar se o autoload está funcionando
2. Verificar se os namespaces estão corretos (`PixelHub\Controllers`)

---

**Última atualização**: Janeiro 2025

