# 🔍 Verificar Arquivo Tentando Usar Porta 443

## ⚠️ Problema

Ainda há um arquivo tentando usar a porta 443. Precisamos encontrar e remover/comentar.

---

## 📋 Diagnóstico

Execute:

```bash
# 1. Ver TODOS os arquivos ativos (não backups) tentando usar 443
find /etc/nginx/conf.d/ /etc/nginx/sites-enabled/ -type f ! -name "*.backup*" -exec grep -l "listen 443" {} \;

# 2. Ver conteúdo do arquivo wpp_443_proxy_to_9443 (vimos ele antes)
cat /etc/nginx/sites-enabled/wpp_443_proxy_to_9443

# 3. Ver conteúdo do arquivo sites-available correspondente
cat /etc/nginx/sites-available/wpp_443_proxy_to_9443

# 4. Ver se há outros arquivos em conf.d tentando usar 443
grep -r "listen 443" /etc/nginx/conf.d/ --include="*.conf" | grep -v backup
```

---

## 🛠️ Solução: Desabilitar Arquivo Usando 443

Se encontrar o arquivo, desabilite:

```bash
# 1. Remover link simbólico de sites-enabled
rm -f /etc/nginx/sites-enabled/wpp_443_proxy_to_9443

# 2. Ou comentar o listen 443 no arquivo
# (depende do que encontrarmos)

# 3. Validar
nginx -t

# 4. Reiniciar Nginx
pkill nginx
nginx

# 5. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

