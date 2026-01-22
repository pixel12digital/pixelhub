# Comandos Rápidos - Proteção Gateway WhatsApp

## ⚡ Execução Rápida do Script

```bash
# 1. Transferir script para VPS (do seu computador)
# Use SCP, WinSCP ou copie o conteúdo

# 2. Na VPS - Dar permissão
chmod +x script_proteger_gateway_ssl.sh

# 3. Executar
sudo ./script_proteger_gateway_ssl.sh
```

---

## 🔍 Comandos de Diagnóstico (Antes do Script)

Execute estes comandos para entender o estado atual:

```bash
# Status do Nginx
systemctl status nginx

# Sintaxe do Nginx
nginx -t

# Encontrar configuração do domínio
grep -r "wpp.pixel12digital.com.br" /etc/nginx/

# Verificar certificados
certbot certificates

# Testar conexão SSL
curl -vI https://wpp.pixel12digital.com.br

# Ver logs de erro
tail -50 /var/log/nginx/error.log

# Verificar porta 443
ss -tlnp | grep :443
```

---

## 🛠️ Comandos Manuais (Se Preferir Não Usar o Script)

### 1. Criar Certificado SSL

```bash
certbot certonly --nginx -d wpp.pixel12digital.com.br
```

### 2. Criar Autenticação Básica

```bash
# Instalar htpasswd (se não tiver)
apt-get install apache2-utils  # Debian/Ubuntu
yum install httpd-tools        # CentOS/RHEL

# Criar arquivo de autenticação
htpasswd -c /etc/nginx/.htpasswd_wpp admin
# Digite a senha quando solicitado
```

### 3. Criar Configuração Nginx Manualmente

```bash
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

Cole a configuração (ver exemplo no `GUIA_PROTECAO_GATEWAY.md`)

### 4. Validar e Aplicar

```bash
nginx -t
systemctl reload nginx
```

---

## ✅ Comandos de Verificação (Após Configuração)

```bash
# Testar HTTPS
curl -I https://wpp.pixel12digital.com.br

# Testar com autenticação
curl -u usuario:senha -I https://wpp.pixel12digital.com.br

# Verificar certificado
openssl s_client -connect wpp.pixel12digital.com.br:443 -servername wpp.pixel12digital.com.br

# Ver logs em tempo real
tail -f /var/log/nginx/wpp.pixel12digital.com.br_error.log
tail -f /var/log/nginx/wpp.pixel12digital.com.br_access.log
```

---

## 🔧 Comandos de Manutenção

```bash
# Renovar certificado
certbot renew --cert-name wpp.pixel12digital.com.br
systemctl reload nginx

# Alterar senha
htpasswd /etc/nginx/.htpasswd_wpp.pixel12digital.com.br usuario

# Ver status do Nginx
systemctl status nginx

# Recarregar Nginx (sem downtime)
systemctl reload nginx

# Reiniciar Nginx (com downtime)
systemctl restart nginx
```

---

## 🚨 Comandos de Troubleshooting

```bash
# Ver erros do Nginx
tail -100 /var/log/nginx/error.log

# Ver configurações ativas
nginx -T | grep -A 20 "wpp.pixel12digital"

# Verificar processos
ps aux | grep nginx

# Verificar portas
netstat -tlnp | grep nginx
ss -tlnp | grep nginx

# Testar configuração específica
nginx -t -c /etc/nginx/nginx.conf
```

---

## 📋 Checklist Rápido

- [ ] Nginx rodando: `systemctl status nginx`
- [ ] Sintaxe OK: `nginx -t`
- [ ] Certificado existe: `certbot certificates`
- [ ] Porta 443 aberta: `ss -tlnp | grep :443`
- [ ] Gateway rodando: `ss -tlnp | grep :3000` (ou porta do gateway)
- [ ] HTTPS funcionando: `curl -I https://wpp.pixel12digital.com.br`
- [ ] Autenticação funcionando: `curl -u user:pass -I https://wpp.pixel12digital.com.br`

---

**Dica**: Use o script completo (`script_proteger_gateway_ssl.sh`) para automatizar tudo isso!

