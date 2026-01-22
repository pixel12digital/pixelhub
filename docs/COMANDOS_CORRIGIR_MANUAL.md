# 🔧 Comandos para Corrigir Configuração Manualmente

Como o script não está disponível, execute estes comandos **na ordem**:

---

## 1. Fazer Backup da Configuração Atual

```bash
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)
```

---

## 2. Editar Configuração para Usar Porta 8443

```bash
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**Altere estas linhas:**

**De:**
```nginx
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
```

**Para:**
```nginx
    listen 8443 ssl http2;
    listen [::]:8443 ssl http2;
```

**E também altere o redirecionamento HTTP:**

**De:**
```nginx
        return 301 https://$server_name$request_uri;
```

**Para:**
```nginx
        return 301 https://$server_name:8443$request_uri;
```

**Salve o arquivo:** `Ctrl+O`, `Enter`, `Ctrl+X`

---

## 3. Validar Sintaxe

```bash
nginx -t
```

**Deve mostrar:** `syntax is ok` e `test is successful`

---

## 4. Recarregar Nginx

```bash
systemctl reload nginx
```

---

## 5. Verificar se Está Escutando na Porta 8443

```bash
ss -tlnp | grep :8443
```

**Deve mostrar:** Nginx escutando na porta 8443

---

## 6. Testar Acesso

```bash
# Testar sem autenticação (deve pedir)
curl -k -I https://wpp.pixel12digital.com.br:8443

# Testar com autenticação
curl -k -u Los@ngo#081081:SUA_SENHA -I https://wpp.pixel12digital.com.br:8443
```

**Nota:** O `-k` ignora verificação de certificado (temporário para teste)

---

## 7. Ver Logs (Se Houver Erro)

```bash
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_error.log
```

---

## ✅ Alternativa: Usar sed para Alterar Automaticamente

Se preferir não editar manualmente, execute:

```bash
# Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# Alterar porta 443 para 8443
sed -i 's/listen 443 ssl http2;/listen 8443 ssl http2;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i 's/listen \[::\]:443 ssl http2;/listen [::]:8443 ssl http2;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Alterar redirecionamento HTTP
sed -i 's/return 301 https:\/\/\$server_name\$request_uri;/return 301 https:\/\/$server_name:8443$request_uri;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Validar
nginx -t

# Se OK, recarregar
systemctl reload nginx
```

---

## 🎯 Resultado Esperado

Após executar:
- ✅ Nginx escutando na porta 8443
- ✅ Gateway acessível em `https://wpp.pixel12digital.com.br:8443`
- ✅ Autenticação funcionando
- ✅ SSL funcionando
- ✅ AzuraCast continua na porta 443 (não afetado)

---

## ⚠️ Se Ainda Der Erro de Certificado

O erro `SSL certificate problem` pode ser porque:
1. O certificado não está sendo encontrado
2. O Nginx não recarregou corretamente

**Verificar:**
```bash
# Ver se certificado existe
ls -la /etc/letsencrypt/live/wpp.pixel12digital.com.br/

# Ver configuração atual
grep -A 5 "ssl_certificate" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Reiniciar Nginx (se reload não funcionou)
systemctl restart nginx
```

