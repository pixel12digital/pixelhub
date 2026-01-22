# 🔍 Verificar Qual Server Block Está Sendo Usado

## ⚠️ Problema Persistente

Ainda está servindo arquivo estático mesmo após recriar configuração. Precisamos verificar qual server block está realmente sendo usado.

---

## 📋 Diagnóstico Avançado

Execute:

```bash
# 1. Ver TODOS os server blocks na porta 8443
nginx -T 2>/dev/null | grep -B 5 -A 30 "listen.*8443"

# 2. Ver qual server block responde sem Host header
curl -k -v https://212.85.11.238:8443 2>&1 | grep -E "HTTP|server|location"

# 3. Ver qual server block responde com Host correto
curl -k -v -H "Host: wpp.pixel12digital.com.br" https://212.85.11.238:8443 2>&1 | grep -E "HTTP|server|location"

# 4. Ver se há configuração global servindo arquivos
grep -r "root.*html" /etc/nginx/nginx.conf /etc/nginx/conf.d/ 2>/dev/null | grep -v "#"

# 5. Ver arquivo de configuração do agentes (que tem root configurado)
grep -A 50 "agentes.pixel12digital.com.br" /etc/nginx/conf.d/*.conf 2>/dev/null | head -60

# 6. Ver logs em tempo real enquanto testa
tail -f /var/log/nginx/access.log &
TAIL_PID=$!
curl -k -I https://wpp.pixel12digital.com.br:8443
kill $TAIL_PID
```

---

## 🛠️ Possível Solução: Verificar Ordem de Carregamento

O Nginx pode estar usando o server block do `agentes` porque:
1. Foi carregado primeiro (ordem alfabética)
2. Tem configuração mais específica
3. Está sobrescrevendo o nosso

---

## 🔧 Solução: Renomear Arquivo para Carregar Primeiro

```bash
# Ver ordem atual
ls -la /etc/nginx/conf.d/*.conf

# Renomear para carregar ANTES do agentes (alfabeticamente)
mv /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# Validar e recarregar
nginx -t && systemctl reload nginx
```

---

## ✅ Alternativa: Verificar se Server Block Correto Está Sendo Usado

```bash
# Adicionar header customizado para identificar
sed -i '/add_header X-XSS-Protection/a\    add_header X-Server-Block "wpp-gateway" always;' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Recarregar e testar
nginx -t && systemctl reload nginx
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep X-Server-Block
```

Se aparecer `X-Server-Block: wpp-gateway`, nosso server block está sendo usado. Se não aparecer, outro server block está respondendo.

