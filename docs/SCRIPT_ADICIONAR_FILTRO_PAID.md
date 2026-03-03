# SCRIPT PARA ADICIONAR FILTRO DE FATURAS PAGAS

**Data:** 03/03/2026  
**Objetivo:** Adicionar filtro `paid_at IS NULL` nas queries de cobrança

---

## 🔧 COMANDO PARA EXECUTAR NO SERVIDOR

**Rode no servidor PixelHub (SSH):**

```bash
cat > /tmp/add_paid_filter.sh << 'EOF'
#!/bin/bash
SCRIPT="/home/pixel12digital/hub.pixel12digital.com.br/scripts/billing_auto_dispatch.php"
BACKUP="${SCRIPT}.backup_paid_filter_$(date +%Y%m%d_%H%M%S)"

echo "=== Adicionando filtro de faturas pagas ==="
echo ""

# Backup
cp "$SCRIPT" "$BACKUP"
echo "✅ Backup: $BACKUP"

# Localizar a query que busca todas as faturas vencidas e adicionar filtro
# A query está dentro do código que adicionamos anteriormente
sed -i '/WHERE tenant_id = ?$/a\        AND (paid_at IS NULL OR paid_at = '\''0000-00-00 00:00:00'\'')' "$SCRIPT"

echo "✅ Filtro adicionado!"
echo ""
echo "Verificando alteração:"
grep -A 2 "WHERE tenant_id = ?" "$SCRIPT" | head -6
echo ""
echo "Para reverter:"
echo "  cp $BACKUP $SCRIPT"
EOF

bash /tmp/add_paid_filter.sh
```

---

## 📝 ALTERNATIVA: MODIFICAÇÃO MANUAL

Se o script automático não funcionar, edite manualmente:

**Arquivo:** `/home/pixel12digital/hub.pixel12digital.com.br/scripts/billing_auto_dispatch.php`

**Localizar (aproximadamente linha 15-20 após o código de agrupamento):**
```php
$allOverdueStmt = $pdo->prepare("
    SELECT id, due_date, amount, description, asaas_payment_id
    FROM billing_invoices
    WHERE tenant_id = ?
    AND status IN ('pending', 'overdue')
    AND is_deleted = 0
    ORDER BY due_date ASC
");
```

**Adicionar linha:**
```php
$allOverdueStmt = $pdo->prepare("
    SELECT id, due_date, amount, description, asaas_payment_id
    FROM billing_invoices
    WHERE tenant_id = ?
    AND status IN ('pending', 'overdue')
    AND (paid_at IS NULL OR paid_at = '0000-00-00 00:00:00')  // ← ADICIONAR ESTA LINHA
    AND is_deleted = 0
    ORDER BY due_date ASC
");
```

---

## ✅ RESULTADO ESPERADO

Após adicionar o filtro:
- ✅ Faturas com `paid_at` preenchido serão ignoradas
- ✅ Apenas faturas realmente pendentes serão cobradas
- ✅ Camada extra de segurança contra cobranças duplicadas

---

**Nota:** Este filtro é uma **camada de segurança adicional**. A sincronização do Asaas já atualiza corretamente o status e paid_at das faturas.
