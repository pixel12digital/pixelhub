# Guia: Proteger Gateway WhatsApp + Corrigir SSL

## Objetivo

Este guia fornece um script completo para:
1. ✅ Diagnosticar e corrigir erro SSL (ERR_SSL_PROTOCOL_ERROR)
2. ✅ Proteger o gateway contra acesso público não autorizado
3. ✅ Implementar autenticação básica + IP whitelist (opcional)
4. ✅ Garantir que apenas pessoas autorizadas possam acessar
5. ✅ Não interferir com AzuraCast

---

## 📋 Pré-requisitos

- Acesso root/sudo na VPS
- Nginx instalado e rodando
- Certbot instalado (para certificados SSL)
- Gateway do WhatsApp rodando em uma porta local (ex: 3000, 8080)

---

## 🚀 Como Usar o Script

### Passo 1: Baixar/Copiar o Script

O script está em: `docs/script_proteger_gateway_ssl.sh`

### Passo 2: Transferir para a VPS

```bash
# No seu computador local (Windows)
# Use SCP, WinSCP, ou copie o conteúdo e cole na VPS
```

### Passo 3: Dar Permissão de Execução

```bash
# Na VPS
chmod +x script_proteger_gateway_ssl.sh
```

### Passo 4: Executar o Script

```bash
sudo ./script_proteger_gateway_ssl.sh
```

---

## 📝 O que o Script Faz

### Fase 1: Diagnóstico
- ✅ Verifica status do Nginx
- ✅ Valida sintaxe das configurações
- ✅ Localiza configuração existente para o domínio
- ✅ Verifica certificados SSL
- ✅ Verifica porta 443
- ✅ Analisa logs de erro

### Fase 2: Coleta de Informações
- 📝 Solicita IPs permitidos (whitelist - opcional)
- 📝 Solicita usuário e senha para autenticação básica
- 📝 Identifica porta do gateway interno

### Fase 3: Certificado SSL
- 🔒 Cria ou renova certificado Let's Encrypt
- 🔒 Verifica validade do certificado

### Fase 4: Autenticação Básica
- 🔐 Cria arquivo `.htpasswd` com credenciais
- 🔐 Configura permissões corretas

### Fase 5: Configuração do Nginx
- ⚙️ Cria configuração completa e segura
- ⚙️ Implementa IP whitelist (se configurado)
- ⚙️ Configura autenticação básica
- ⚙️ Configura proxy reverso para o gateway
- ⚙️ Adiciona headers de segurança
- ⚙️ Configura SSL moderno (TLS 1.2/1.3)

### Fase 6: Validação
- ✅ Testa sintaxe do Nginx
- ✅ Recarrega Nginx sem downtime

### Fase 7: Testes Finais
- ✅ Testa conexão HTTPS
- ✅ Verifica certificado SSL
- ✅ Valida porta 443

---

## 🔒 Segurança Implementada

### 1. Autenticação Básica
- Usuário e senha obrigatórios para acessar
- Arquivo `.htpasswd` protegido

### 2. IP Whitelist (Opcional)
- Permite restringir acesso apenas a IPs específicos
- Suporta IPs individuais e ranges CIDR
- Exemplo: `192.168.1.100` ou `200.150.100.0/24`

### 3. SSL/TLS Moderno
- TLS 1.2 e 1.3 apenas
- Cipher suites seguros
- HSTS (HTTP Strict Transport Security)
- Headers de segurança (X-Frame-Options, etc.)

### 4. Proteção contra Ataques
- Headers de segurança configurados
- Timeout adequado para WebSocket
- Buffering desabilitado para streaming

---

## 📊 Exemplo de Uso Interativo

```
[INFO] Configuração de IP Whitelist
[INFO] Digite os IPs que terão acesso ao gateway (um por linha, Enter vazio para finalizar):
[INFO] Exemplo: 192.168.1.100 ou 200.150.100.0/24 (CIDR)
[INFO] Deixe vazio se não quiser restrição por IP (apenas autenticação básica)
IP (ou Enter para finalizar): 192.168.1.100
[LOG] IP adicionado: 192.168.1.100
IP (ou Enter para finalizar): 200.150.100.0/24
[LOG] IP adicionado: 200.150.100.0/24
IP (ou Enter para finalizar): [Enter]

[LOG] Configuração de Autenticação Básica
Usuário para autenticação básica: admin
Senha para autenticação básica: [senha oculta]

[LOG] Porta do gateway: 3000
```

---

## 🔧 Configuração Gerada

O script cria uma configuração Nginx completa:

```nginx
# Redirecionamento HTTP → HTTPS
server {
    listen 80;
    server_name wpp.pixel12digital.com.br;
    return 301 https://$server_name$request_uri;
}

# Configuração HTTPS com segurança
server {
    listen 443 ssl http2;
    server_name wpp.pixel12digital.com.br;
    
    # SSL moderno
    ssl_certificate /etc/letsencrypt/live/wpp.pixel12digital.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wpp.pixel12digital.com.br/privkey.pem;
    
    # IP Whitelist (se configurado)
    deny all;
    allow 192.168.1.100;
    allow 200.150.100.0/24;
    
    # Autenticação básica
    auth_basic "Acesso Restrito - Gateway WhatsApp";
    auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
    
    # Proxy para gateway
    location / {
        proxy_pass http://127.0.0.1:3000;
        # ... configurações de proxy
    }
}
```

---

## 📁 Arquivos Criados

- **Configuração Nginx**: `/etc/nginx/conf.d/wpp.pixel12digital.com.br.conf` (ou similar)
- **Autenticação**: `/etc/nginx/.htpasswd_wpp.pixel12digital.com.br`
- **Logs**: 
  - `/var/log/nginx/wpp.pixel12digital.com.br_access.log`
  - `/var/log/nginx/wpp.pixel12digital.com.br_error.log`
- **Backup**: `/root/backup_nginx_YYYYMMDD_HHMMSS/`
- **Log do Script**: `/root/gateway_ssl_fix_YYYYMMDD_HHMMSS.log`

---

## ✅ Verificação Pós-Instalação

### 1. Testar Acesso HTTPS

```bash
curl -I https://wpp.pixel12digital.com.br
```

Deve retornar `401 Unauthorized` (esperado - precisa de autenticação)

### 2. Testar com Autenticação

```bash
curl -u usuario:senha -I https://wpp.pixel12digital.com.br
```

Deve retornar `200 OK` ou `302 Found`

### 3. Verificar Certificado SSL

```bash
openssl s_client -connect wpp.pixel12digital.com.br:443 -servername wpp.pixel12digital.com.br
```

### 4. Verificar Logs

```bash
tail -f /var/log/nginx/wpp.pixel12digital.com.br_error.log
tail -f /var/log/nginx/wpp.pixel12digital.com.br_access.log
```

---

## 🔄 Manutenção

### Renovar Certificado Manualmente

```bash
certbot renew --cert-name wpp.pixel12digital.com.br
systemctl reload nginx
```

### Adicionar Novo IP à Whitelist

1. Editar configuração do Nginx:
```bash
sudo nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

2. Adicionar linha `allow NOVO_IP;` antes de `deny all;`

3. Recarregar Nginx:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Alterar Senha de Autenticação

```bash
sudo htpasswd /etc/nginx/.htpasswd_wpp.pixel12digital.com.br usuario
```

### Remover Autenticação (NÃO RECOMENDADO)

1. Editar configuração do Nginx
2. Remover linhas:
   - `auth_basic "Acesso Restrito - Gateway WhatsApp";`
   - `auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;`
3. Recarregar Nginx

---

## 🚨 Troubleshooting

### Erro: "nginx: [emerg] bind() to 0.0.0.0:443 failed"

**Causa**: Porta 443 já está em uso

**Solução**:
```bash
# Verificar o que está usando a porta
sudo ss -tlnp | grep :443

# Se for outro serviço, pare-o ou configure para outra porta
```

### Erro: "certbot: error: unrecognized arguments"

**Causa**: Versão antiga do certbot

**Solução**:
```bash
# Atualizar certbot
sudo apt-get update && sudo apt-get install --only-upgrade certbot
```

### Erro: "502 Bad Gateway"

**Causa**: Gateway não está rodando na porta configurada

**Solução**:
```bash
# Verificar se gateway está rodando
sudo ss -tlnp | grep :3000

# Verificar logs do Nginx
sudo tail -50 /var/log/nginx/wpp.pixel12digital.com.br_error.log
```

### Erro: "SSL certificate problem"

**Causa**: Certificado inválido ou expirado

**Solução**:
```bash
# Renovar certificado
sudo certbot renew --cert-name wpp.pixel12digital.com.br --force-renewal
sudo systemctl reload nginx
```

### Autenticação não funciona

**Causa**: Permissões incorretas no arquivo `.htpasswd`

**Solução**:
```bash
# Corrigir permissões
sudo chmod 644 /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
sudo chown root:www-data /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
sudo systemctl reload nginx
```

---

## 🔙 Restaurar Backup

Se algo der errado, você pode restaurar o backup:

```bash
# Listar backups
ls -la /root/backup_nginx_*/

# Restaurar configuração
sudo cp /root/backup_nginx_YYYYMMDD_HHMMSS/arquivo.conf.backup /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Validar e recarregar
sudo nginx -t && sudo systemctl reload nginx
```

---

## 📞 Suporte

Se encontrar problemas:

1. Verifique os logs do script: `/root/gateway_ssl_fix_*.log`
2. Verifique logs do Nginx: `/var/log/nginx/wpp.pixel12digital.com.br_error.log`
3. Execute diagnóstico manual (ver `DIAGNOSTICO_SSL_VPS.md`)
4. Compartilhe os logs e mensagens de erro

---

## ⚠️ Importante

- **Não compartilhe** as credenciais de autenticação
- **Mantenha** o certificado SSL renovado automaticamente
- **Monitore** os logs regularmente para tentativas de acesso não autorizado
- **Use IP whitelist** em produção para máxima segurança
- **Não remova** a autenticação básica sem ter outra camada de segurança

---

## 🎯 Resultado Esperado

Após executar o script:

✅ Gateway acessível apenas via HTTPS  
✅ Autenticação básica obrigatória  
✅ IP whitelist ativa (se configurado)  
✅ Certificado SSL válido e renovando automaticamente  
✅ Headers de segurança configurados  
✅ AzuraCast não afetado  
✅ Logs detalhados para monitoramento  

---

**Última atualização**: 2025-01-31

