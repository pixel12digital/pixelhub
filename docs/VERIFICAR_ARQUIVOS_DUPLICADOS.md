# 🔍 Verificar Arquivos Duplicados

## ⚠️ Problema

Ainda está servindo arquivo estático mesmo após desabilitar o agentes. Pode haver dois arquivos de configuração para wpp.

---

## 📋 Verificação

Execute:

```bash
# 1. Ver TODOS os arquivos relacionados a wpp
ls -la /etc/nginx/conf.d/*wpp* /etc/nginx/sites-enabled/*wpp* 2>/dev/null

# 2. Ver qual server block está realmente sendo usado
nginx -T 2>/dev/null | grep -B 10 -A 30 "wpp.pixel12digital.com.br" | grep -A 30 "listen.*8443"

# 3. Ver se há arquivo wpp.pixel12digital.com.br.conf (sem o 00-)
ls -la /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf 2>/dev/null

# 4. Ver conteúdo do arquivo 00-wpp
cat /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf | head -50

# 5. Testar com verbose para ver qual server block responde
curl -k -v https://wpp.pixel12digital.com.br:8443 2>&1 | grep -E "HTTP|server:|X-Server-Block" | head -10
```

---

## 🛠️ Possível Solução: Remover Arquivo Duplicado

Se houver dois arquivos, remova o antigo:

```bash
# 1. Ver se há arquivo sem o 00-
if [ -f /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf ]; then
    echo "Arquivo duplicado encontrado!"
    rm /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
    echo "Arquivo removido"
fi

# 2. Validar e recarregar
nginx -t && systemctl reload nginx

# 3. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

