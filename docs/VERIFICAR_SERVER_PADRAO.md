# 🔍 Verificar Server Block Padrão

## ⚠️ Problema: Logs Vazios

Os logs estão vazios, o que significa que as requisições podem estar sendo atendidas por outro server block (padrão).

---

## 📋 Comandos de Diagnóstico

Execute:

```bash
# 1. Ver todos os server blocks configurados
nginx -T 2>/dev/null | grep -A 20 "server {" | head -100

# 2. Ver se há server block padrão (default_server)
nginx -T 2>/dev/null | grep -B 5 -A 20 "default_server"

# 3. Ver configuração do nginx.conf completa
cat /etc/nginx/nginx.conf

# 4. Ver todos os arquivos de configuração incluídos
find /etc/nginx -name "*.conf" -type f

# 5. Ver qual server block está respondendo (testar com server_name específico)
curl -k -v -H "Host: wpp.pixel12digital.com.br:8443" https://212.85.11.238:8443 2>&1 | grep -E "HTTP|server|location"

# 6. Ver logs gerais do Nginx (não específicos do domínio)
tail -30 /var/log/nginx/error.log
tail -30 /var/log/nginx/access.log
```

---

## 🛠️ Possível Solução: Adicionar default_server

Se houver outro server block padrão, precisamos garantir que nosso server block seja o padrão para a porta 8443:

```bash
# Editar configuração
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**Alterar:**
```nginx
    listen 8443 ssl http2 default_server;
    listen [::]:8443 ssl http2 default_server;
```

---

## 🔧 Solução Alternativa: Verificar Ordem de Carregamento

O Nginx carrega configurações em ordem alfabética. Verifique:

```bash
# Ver ordem dos arquivos
ls -la /etc/nginx/conf.d/*.conf

# Se houver outro arquivo antes alfabeticamente, pode estar sobrescrevendo
```

