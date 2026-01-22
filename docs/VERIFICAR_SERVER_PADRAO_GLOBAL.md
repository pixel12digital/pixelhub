# 🔍 Verificar Server Block Padrão Global

## ⚠️ Problema

Ainda está servindo arquivo estático. Pode haver um server block padrão no nginx.conf servindo arquivos.

---

## 📋 Verificação

Execute:

```bash
# 1. Ver se há server block padrão no nginx.conf
grep -A 30 "server {" /etc/nginx/nginx.conf | head -50

# 2. Ver TODOS os server blocks ativos (sem server_name específico)
nginx -T 2>/dev/null | grep -B 5 -A 20 "listen.*8443" | grep -B 5 "server_name _"

# 3. Ver se há server block com server_name "_" (padrão)
nginx -T 2>/dev/null | grep -B 10 -A 30 'server_name "_"'

# 4. Ver configuração completa de TODOS os server blocks na 8443
nginx -T 2>/dev/null | grep -B 15 "listen.*8443" | grep -A 40 "server {"
```

---

## 🛠️ Solução: Verificar Arquivo do Agentes Novamente

O arquivo do agentes pode não ter sido comentado completamente. Verifique:

```bash
# 1. Ver se ainda há server block ativo do agentes na 8443
nginx -T 2>/dev/null | grep -B 10 -A 30 "agentes.pixel12digital.com.br" | grep -A 30 "listen.*8443"

# 2. Ver arquivo do agentes novamente
cat /etc/nginx/sites-available/agentes_ssl_8443.disabled 2>/dev/null || cat /etc/nginx/sites-enabled/agentes_ssl_8443 2>/dev/null
```

---

## 🔧 Solução Alternativa: Verificar Ordem de Prioridade

O Nginx pode estar usando o server block do agentes porque foi carregado de sites-enabled. Verifique:

```bash
# Ver todos os arquivos em sites-enabled
ls -la /etc/nginx/sites-enabled/

# Ver se agentes ainda está ativo
ls -la /etc/nginx/sites-enabled/*agentes* 2>/dev/null
```

