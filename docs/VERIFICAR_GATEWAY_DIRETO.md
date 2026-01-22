# 🔍 Verificar Gateway Diretamente

## ⚠️ Problema Persistente

Ainda está retornando arquivo estático ao invés do gateway. Precisamos verificar:

1. Se o gateway está respondendo no IP do Docker
2. Se a autenticação está configurada corretamente
3. Por que está servindo arquivo estático

---

## 📋 Comandos de Diagnóstico

Execute:

```bash
# 1. Testar gateway diretamente no IP do Docker
curl -I http://172.19.0.1:3000

# 2. Ver configuração atual do proxy_pass
grep "proxy_pass" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Ver se há root/index configurado (pode estar servindo arquivo estático)
grep -E "root|index" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 4. Ver configuração completa do location /
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf | grep -A 25 "location /"

# 5. Ver logs do Nginx para entender o que está acontecendo
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_error.log
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_access.log
```

---

## 🛠️ Possível Causa

O Nginx pode estar servindo um arquivo estático porque:
1. O proxy_pass não está funcionando
2. Há uma diretiva `root` ou `index` configurada
3. O gateway não está respondendo no IP do Docker

---

## ✅ Teste Direto do Gateway

```bash
# Testar se gateway responde
curl -v http://172.19.0.1:3000 2>&1 | head -30
```

Se o gateway responder, veremos a resposta real. Se não responder, precisamos verificar a rede Docker.

