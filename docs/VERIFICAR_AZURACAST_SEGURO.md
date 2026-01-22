# ✅ Verificação: AzuraCast Não Será Afetado

## 🎯 Garantias

A solução proposta **NÃO afeta o AzuraCast** porque:

1. ✅ **AzuraCast usa porta 443** (via Docker) - **NÃO será alterado**
2. ✅ **Gateway usará porta 8443** - Porta diferente, sem conflito
3. ✅ **Configurações separadas** - Cada domínio tem sua própria configuração
4. ✅ **Nginx do host não escuta na 443** - AzuraCast continua usando diretamente

---

## 🔍 Verificação Antes de Aplicar

Execute estes comandos para confirmar que está tudo seguro:

### 1. Verificar qual domínio o AzuraCast está usando

```bash
docker exec azuracast cat /etc/nginx/azuracast.conf 2>/dev/null | grep -i "server_name" | head -5
```

ou

```bash
docker exec azuracast env | grep -i "azuracast_base_url\|azuracast_base_domain" 2>/dev/null
```

### 2. Verificar se há configuração Nginx para radioweb.app.br

```bash
grep -r "radioweb.app.br" /etc/nginx/ 2>/dev/null
```

### 3. Verificar que AzuraCast está rodando normalmente

```bash
docker ps | grep azuracast
curl -I https://painel.radioweb.app.br/login
```

---

## 🛡️ Por Que É Seguro

### Configuração Atual:
- **AzuraCast (Docker)**: Escuta na porta **443** diretamente
- **Gateway (Nginx host)**: Tentando escutar na porta **443** → **CONFLITO**

### Configuração Proposta:
- **AzuraCast (Docker)**: Continua na porta **443** → **SEM MUDANÇAS**
- **Gateway (Nginx host)**: Mudará para porta **8443** → **SEM CONFLITO**

### Resultado:
- ✅ AzuraCast: `https://painel.radioweb.app.br` (porta 443) → **Funciona normalmente**
- ✅ Gateway: `https://wpp.pixel12digital.com.br:8443` (porta 8443) → **Funciona normalmente**
- ✅ **Zero interferência entre eles**

---

## 📋 Comandos Seguros para Aplicar

Estes comandos **apenas alteram** a configuração do gateway, **não tocam** no AzuraCast:

```bash
# 1. Verificar que AzuraCast está funcionando ANTES
curl -I https://painel.radioweb.app.br/login

# 2. Fazer backup (apenas do gateway)
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 3. Alterar APENAS a configuração do gateway (wpp.pixel12digital.com.br)
sed -i 's/listen 443 ssl http2;/listen 8443 ssl http2;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i 's/listen \[::\]:443 ssl http2;/listen [::]:8443 ssl http2;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i 's/return 301 https:\/\/\$server_name\$request_uri;/return 301 https:\/\/$server_name:8443$request_uri;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 4. Validar (vai verificar TODAS as configurações, incluindo AzuraCast)
nginx -t

# 5. Recarregar Nginx (sem downtime, não afeta Docker)
systemctl reload nginx

# 6. Verificar que AzuraCast AINDA funciona DEPOIS
curl -I https://painel.radioweb.app.br/login

# 7. Verificar que gateway funciona na nova porta
curl -k -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Checklist de Segurança

Antes de aplicar, confirme:

- [ ] AzuraCast está funcionando: `curl -I https://painel.radioweb.app.br/login`
- [ ] Backup criado: `ls -la /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_*`
- [ ] Sintaxe válida: `nginx -t` (deve passar sem erros)
- [ ] Após aplicar, AzuraCast ainda funciona: `curl -I https://painel.radioweb.app.br/login`

---

## 🔄 Reverter (Se Necessário)

Se por algum motivo o AzuraCast parar de funcionar (improvável), reverta:

```bash
# Restaurar backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_* /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# Recarregar
nginx -t && systemctl reload nginx
```

---

## 📊 Explicação Técnica

### Por que não há conflito:

1. **Docker usa porta 443 diretamente** (bypass do Nginx do host)
   - AzuraCast → Docker → Porta 443
   - Nginx do host não precisa escutar na 443 para o AzuraCast

2. **Gateway precisa do Nginx do host**
   - Gateway → Nginx host → Porta 8443 (nova)
   - Não interfere com Docker na 443

3. **Configurações separadas**
   - `/etc/nginx/conf.d/wpp.pixel12digital.com.br.conf` → Apenas gateway
   - AzuraCast tem sua própria configuração dentro do Docker

---

## 🎯 Conclusão

**A solução é 100% segura para o AzuraCast** porque:
- ✅ Não altera configuração do Docker
- ✅ Não altera porta do AzuraCast
- ✅ Não altera domínio do AzuraCast
- ✅ Apenas move o gateway para porta diferente

**AzuraCast continuará funcionando normalmente em:**
- `https://painel.radioweb.app.br/login` (porta 443)

**Gateway funcionará em:**
- `https://wpp.pixel12digital.com.br:8443` (porta 8443)

---

**Pode aplicar com segurança!** 🛡️

