# ✅ Verificar Redirecionamento Configurado

## 🔍 Verificar Configuração

Execute:

```bash
# 1. Ver se o redirecionamento foi adicionado
grep -A 5 "location = /" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 2. Ver configuração completa do location
cat /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf | grep -A 10 "location"
```

---

## 🧪 Testar no Navegador

A forma mais fácil é testar no navegador:

1. Acesse: `https://wpp.pixel12digital.com.br:8443`
2. Digite usuário: `wpp.pixel12`
3. Digite a senha
4. Veja se redireciona para `/ui/`

---

## 🔧 Se Precisar Ajustar a Rota

Se `/ui/` não funcionar, descubra qual rota funciona:

```bash
# Testar diferentes rotas comuns (substitua SUA_SENHA pela senha real)
curl -k -u "wpp.pixel12:SUA_SENHA" https://wpp.pixel12digital.com.br:8443/api/ | head -5
curl -k -u "wpp.pixel12:SUA_SENHA" https://wpp.pixel12digital.com.br:8443/webhook/ | head -5
curl -k -u "wpp.pixel12:SUA_SENHA" https://wpp.pixel12digital.com.br:8443/status | head -5
```

---

## 🛠️ Alterar Rota do Redirecionamento

Se descobrir que a rota correta é diferente de `/ui/`, altere:

```bash
# Exemplo: mudar para /dashboard/
sed -i 's|return 302 /ui/;|return 302 /dashboard/;|' /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# Validar e recarregar
nginx -t && nginx -s reload
```

