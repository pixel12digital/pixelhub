# 🔧 Corrigir Autenticação Básica

## ⚠️ Problema Identificado

Os testes mostram:
- ❌ **Sem autenticação**: Retorna `200` (deveria retornar `401`)
- ✅ **Com autenticação**: Retorna `200` (correto)
- ⚠️ **Logs vazios**: Arquivo pode não estar sendo criado

**A autenticação básica não está bloqueando acesso não autorizado!**

---

## 🔍 Diagnóstico

Execute estes comandos para verificar:

```bash
# 1. Verificar se arquivo de autenticação existe
ls -la /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 2. Ver conteúdo da configuração do Nginx
grep -A 5 "auth_basic" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Verificar se arquivo de autenticação tem conteúdo
cat /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 4. Verificar permissões do arquivo
ls -la /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
```

---

## 🛠️ Correção

### Opção 1: Recriar arquivo de autenticação

```bash
# 1. Remover arquivo antigo (se existir)
rm -f /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 2. Criar novo arquivo de autenticação
htpasswd -bc /etc/nginx/.htpasswd_wpp.pixel12digital.com.br "Los@ngo#081081" "SUA_SENHA_AQUI"

# 3. Ajustar permissões
chmod 644 /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
chown root:www-data /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 4. Verificar configuração do Nginx
grep -A 3 "auth_basic" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 5. Recarregar Nginx
nginx -t && systemctl reload nginx

# 6. Testar novamente
curl -k -I https://wpp.pixel12digital.com.br:8443
```

**Deve retornar `401 Unauthorized` agora!**

---

### Opção 2: Verificar e corrigir configuração do Nginx

```bash
# 1. Ver configuração atual
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf | grep -A 10 "location /"

# 2. Verificar se auth_basic está configurado corretamente
# Deve ter estas linhas:
#   auth_basic "Acesso Restrito - Gateway WhatsApp";
#   auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
```

Se não estiver, adicione manualmente ou recrie a configuração.

---

## 📋 Comandos Completos de Correção

Execute na ordem:

```bash
# 1. Verificar arquivo de autenticação
echo "=== Verificando arquivo de autenticação ==="
ls -la /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
echo ""

# 2. Ver configuração do Nginx
echo "=== Verificando configuração do Nginx ==="
grep -A 5 "auth_basic" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
echo ""

# 3. Recriar autenticação (SUBSTITUA SUA_SENHA pela senha real)
echo "=== Recriando autenticação ==="
htpasswd -bc /etc/nginx/.htpasswd_wpp.pixel12digital.com.br "Los@ngo#081081" "SUA_SENHA"
chmod 644 /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
chown root:www-data /etc/nginx/.htpasswd_wpp.pixel12digital.com.br
echo ""

# 4. Validar e recarregar
echo "=== Validando e recarregando ==="
nginx -t
systemctl reload nginx
echo ""

# 5. Testar
echo "=== Testando (deve retornar 401) ==="
curl -k -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Resultado Esperado

Após corrigir:

```bash
# Sem autenticação - DEVE retornar 401
curl -k -I https://wpp.pixel12digital.com.br:8443
# HTTP/2 401

# Com autenticação - DEVE retornar 200
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
# HTTP/2 200
```

---

## 🔍 Verificar Logs Após Correção

```bash
# Ver logs de acesso (deve mostrar tentativas)
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_access.log

# Ver logs de erro
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_error.log
```

---

## ⚠️ Se Ainda Não Funcionar

Verifique se há outra configuração sobrescrevendo:

```bash
# Ver todas as configurações que mencionam o domínio
grep -r "wpp.pixel12digital.com.br" /etc/nginx/

# Ver se há location / sem autenticação em outro lugar
grep -r "location /" /etc/nginx/conf.d/ | grep -v "#"
```

