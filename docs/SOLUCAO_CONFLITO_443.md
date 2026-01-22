# 🔧 Solução para Conflito de Porta 443 - AzuraCast vs Gateway

## 📋 Situação Atual

- ✅ **AzuraCast** está usando a porta **443** (container Docker)
- ❌ **Nginx do host** não consegue escutar na **443** (conflito)
- ⚠️ **Gateway** precisa ser acessível via HTTPS

---

## 🎯 Duas Soluções Possíveis

### **Solução 1: Porta Alternativa (8443) - RECOMENDADA** ⭐

**Vantagens:**
- ✅ Não interfere com AzuraCast
- ✅ Implementação rápida e segura
- ✅ Fácil de reverter

**Desvantagens:**
- ⚠️ Gateway acessível em `https://wpp.pixel12digital.com.br:8443` (não padrão)

**Como funciona:**
- Gateway usa porta **8443** externamente
- AzuraCast continua na **443**
- Nginx do host escuta na **8443** e faz proxy para o gateway

---

### **Solução 2: Nginx como Proxy Principal (443)**

**Vantagens:**
- ✅ Gateway acessível em `https://wpp.pixel12digital.com.br` (porta padrão)
- ✅ Mais profissional

**Desvantagens:**
- ⚠️ Requer ajustar configuração do AzuraCast
- ⚠️ Mais complexo de implementar
- ⚠️ Pode afetar outros serviços do AzuraCast

**Como funciona:**
- Nginx do host escuta na **443**
- Roteia por `server_name`:
  - `wpp.pixel12digital.com.br` → Gateway
  - Outros domínios → AzuraCast (porta interna)

---

## 🚀 Implementação Rápida - Solução 1 (Recomendada)

### Passo 1: Executar Script de Correção

```bash
# Copiar script para VPS
chmod +x corrigir_configuracao_nginx.sh
sudo ./corrigir_configuracao_nginx.sh
```

### Passo 2: Abrir Porta no Firewall

```bash
# UFW
sudo ufw allow 8443/tcp

# ou iptables
sudo iptables -A INPUT -p tcp --dport 8443 -j ACCEPT
```

### Passo 3: Testar Acesso

```bash
# Testar HTTPS na nova porta
curl -I https://wpp.pixel12digital.com.br:8443

# Testar com autenticação
curl -u usuario:senha -I https://wpp.pixel12digital.com.br:8443
```

### Passo 4: Atualizar Aplicações

Atualize qualquer aplicação/cliente que acessa o gateway para usar a porta **8443**:
- `https://wpp.pixel12digital.com.br:8443`

---

## 🔧 Implementação Avançada - Solução 2

Se preferir usar a porta 443 padrão, siga estes passos:

### Passo 1: Verificar Configuração do AzuraCast

```bash
# Ver docker-compose do AzuraCast
docker inspect azuracast | grep -A 20 "Ports"
```

### Passo 2: Ajustar Mapeamento do AzuraCast

O AzuraCast precisa parar de mapear a porta 443 diretamente. Isso requer:
1. Parar o container AzuraCast
2. Ajustar docker-compose ou variáveis de ambiente
3. Fazer Nginx do host fazer proxy para AzuraCast

**⚠️ ATENÇÃO:** Isso pode afetar outros serviços do AzuraCast. Faça backup antes!

---

## 📝 Comparação das Soluções

| Aspecto | Solução 1 (8443) | Solução 2 (443) |
|---------|------------------|-----------------|
| **Complexidade** | ⭐ Fácil | ⭐⭐⭐ Complexa |
| **Risco** | ⭐ Muito baixo | ⭐⭐ Médio |
| **Tempo** | ⭐ 5 minutos | ⭐⭐⭐ 30+ minutos |
| **AzuraCast** | ✅ Não afetado | ⚠️ Pode ser afetado |
| **Porta** | 8443 | 443 (padrão) |
| **Recomendado** | ✅ SIM | ⚠️ Apenas se necessário |

---

## ✅ Recomendação Final

**Use a Solução 1 (porta 8443)** porque:
1. ✅ Implementação rápida e segura
2. ✅ Não afeta o AzuraCast
3. ✅ Fácil de reverter se necessário
4. ✅ Porta 8443 é comum para HTTPS alternativo
5. ✅ Funciona imediatamente

A porta 8443 é amplamente usada para HTTPS alternativo e não causa problemas de compatibilidade.

---

## 🔄 Reverter Mudanças (Se Necessário)

Se precisar voltar ao estado anterior:

```bash
# Restaurar backup
sudo cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_* /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sudo nginx -t && sudo systemctl reload nginx
```

---

## 📞 Próximos Passos

1. **Execute o script de correção** (`corrigir_configuracao_nginx.sh`)
2. **Abra a porta 8443** no firewall
3. **Teste o acesso** em `https://wpp.pixel12digital.com.br:8443`
4. **Atualize aplicações** para usar a nova porta

---

**Última atualização:** 2026-01-21

