# 🔍 Verificar Configuração Completa

## ⚠️ Problema: Autenticação não está bloqueando

A autenticação está configurada, mas não está funcionando. Precisamos ver a configuração completa.

---

## 📋 Comandos para Diagnóstico

Execute estes comandos:

```bash
# 1. Ver configuração COMPLETA do location /
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf | grep -A 30 "location /"

# 2. Ver se há múltiplas configurações para o mesmo domínio
grep -r "wpp.pixel12digital.com.br" /etc/nginx/ --include="*.conf"

# 3. Ver se há location / em outros lugares que possam estar sobrescrevendo
grep -r "location /" /etc/nginx/conf.d/ | grep -v "#"

# 4. Ver configuração completa do arquivo
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 5. Verificar se o gateway interno está respondendo (pode estar retornando direto)
curl -I http://127.0.0.1:3000
```

---

## 🛠️ Possível Causa

A autenticação pode estar sendo ignorada se:
1. O `proxy_pass` está sendo executado antes da autenticação
2. Há outra configuração sobrescrevendo
3. A ordem das diretivas está errada

---

## ✅ Solução: Verificar Ordem das Diretivas

A ordem correta no Nginx deve ser:

```nginx
location / {
    # IP whitelist (se houver)
    
    # Autenticação básica (ANTES do proxy_pass)
    auth_basic "Acesso Restrito - Gateway WhatsApp";
    auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
    
    # Proxy (DEPOIS da autenticação)
    proxy_pass http://127.0.0.1:3000;
    ...
}
```

---

## 🔧 Correção Manual

Se a ordem estiver errada, vamos corrigir:

```bash
# 1. Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Ver configuração atual
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

Depois, vamos ajustar a ordem das diretivas se necessário.

