# 🔍 Encontrar Arquivo do Agentes

## ⚠️ Problema

O arquivo do agentes não foi encontrado. Precisamos localizá-lo.

---

## 📋 Comandos para Encontrar

Execute:

```bash
# 1. Procurar em todos os lugares
find /etc/nginx -name "*.conf" -exec grep -l "agentes.pixel12digital.com.br" {} \;

# 2. Ver todos os arquivos de configuração
ls -la /etc/nginx/conf.d/*.conf
ls -la /etc/nginx/sites-enabled/*.conf 2>/dev/null

# 3. Ver conteúdo de cada arquivo procurando por agentes
for file in /etc/nginx/conf.d/*.conf; do
    echo "=== $file ==="
    grep -n "agentes\|8443" "$file" | head -5
done

# 4. Ver configuração completa do agentes (onde quer que esteja)
nginx -T 2>/dev/null | grep -B 20 -A 50 "agentes.pixel12digital.com.br" | head -80
```

---

## 🛠️ Solução: Desabilitar Server Block do Agentes na 8443

Após encontrar o arquivo:

```bash
# 1. Encontrar arquivo (substitua pelo caminho encontrado)
AGENTES_FILE="/caminho/do/arquivo.conf"

# 2. Fazer backup
cp "$AGENTES_FILE" "${AGENTES_FILE}.backup_$(date +%Y%m%d_%H%M%S)"

# 3. Comentar apenas o server block HTTPS (porta 8443) do agentes
# Usar sed para comentar do "listen 8443" até o "}" correspondente
sed -i '/server {/,/^}/ {
    /listen 8443 ssl http2;/ {
        :a
        N
        /^}/!ba
        s/^/#/g
    }
}' "$AGENTES_FILE"

# 4. Validar
nginx -t

# 5. Se OK, recarregar
systemctl reload nginx
```

---

## ✅ Alternativa: Editar Manualmente

Se o sed não funcionar, edite manualmente:

```bash
# Encontrar arquivo
find /etc/nginx -name "*.conf" -exec grep -l "agentes.pixel12digital.com.br" {} \;

# Editar
nano /caminho/do/arquivo.conf
```

**Comentar o server block que tem:**
```nginx
server {
    listen 8443 ssl http2;
    server_name agentes.pixel12digital.com.br;
    ...
}
```

**Adicionar `#` no início de cada linha desse server block.**

