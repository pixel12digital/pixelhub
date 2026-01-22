# 🚨 Corrigir Nginx que Falhou ao Reiniciar

## ⚠️ Problema Crítico

O Nginx falhou ao reiniciar! Precisamos verificar o erro e corrigir.

---

## 📋 Diagnóstico Imediato

Execute:

```bash
# 1. Ver erro do Nginx
systemctl status nginx

# 2. Ver logs de erro detalhados
journalctl -xeu nginx.service --no-pager | tail -50

# 3. Testar sintaxe do Nginx
nginx -t

# 4. Ver qual linha está com erro
nginx -t 2>&1 | grep -E "error|failed|emerg"
```

---

## 🛠️ Solução: Verificar e Corrigir Erro

Após ver o erro, vamos corrigir. Possíveis causas:

1. **Erro de sintaxe** na configuração
2. **Conflito de porta** (ainda)
3. **Arquivo de configuração corrompido**

---

## ✅ Restaurar Funcionamento

Se necessário, restaurar backup:

```bash
# 1. Ver backups disponíveis
ls -la /etc/nginx/conf.d/*.backup* /etc/nginx/sites-available/*.backup* 2>/dev/null

# 2. Restaurar configuração que funcionava
# (escolha um backup anterior ao problema)
```

---

## 🔧 Verificar Erro Específico

Execute primeiro:

```bash
nginx -t
```

E compartilhe a saída completa do erro.

