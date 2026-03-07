<?php
/**
 * Fix: normaliza dados históricos de prospecção onde whatsapp_sent_at está preenchido
 * mas status ainda é 'new' (situação impossível — se WA foi enviado, deveria ser 'qualified').
 * Causado por versão anterior de markWaSent() que setava whatsapp_sent_at sem mudar status.
 */

use PixelHub\Core\DB;

$db = DB::getConnection();

$stmt = $db->prepare("
    UPDATE prospecting_results
    SET    status     = 'qualified',
           updated_at = NOW()
    WHERE  whatsapp_sent_at IS NOT NULL
    AND    status = 'new'
");
$stmt->execute();
$rows = $stmt->rowCount();

echo "Migration OK: {$rows} resultado(s) corrigido(s) — status 'new' → 'qualified' onde whatsapp_sent_at estava preenchido.\n";
