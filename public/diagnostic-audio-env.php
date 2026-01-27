<?php
/**
 * Diagnóstico: ambiente para conversão de áudio (WebM→OGG)
 * Verifica disable_functions e ffmpeg para decidir se Hostmidia pode converter
 * ou se a conversão deve ficar no Gateway (VPS).
 *
 * Acesso: GET /diagnostic-audio-env.php (ou public/diagnostic-audio-env.php)
 * Não expõe dados sensíveis; safe para rodar em produção para diagnóstico.
 */
header('Content-Type: application/json; charset=utf-8');

$disabled = array_map('trim', array_filter(explode(',', (string) ini_get('disable_functions'))));
$required = ['exec', 'shell_exec', 'proc_open'];

$checks = [
    'disable_functions_raw' => ini_get('disable_functions'),
    'exec_available' => !in_array('exec', $disabled),
    'shell_exec_available' => !in_array('shell_exec', $disabled),
    'proc_open_available' => !in_array('proc_open', $disabled),
    'can_run_ffmpeg_from_php' => !in_array('exec', $disabled) && !in_array('shell_exec', $disabled),
    'ffmpeg_in_path' => null,
    'ffmpeg_version' => null,
];

// Só tenta exec se não estiver bloqueado
if ($checks['exec_available']) {
    $out = [];
    $ret = -1;
    @exec('ffmpeg -version 2>&1', $out, $ret);
    $checks['ffmpeg_in_path'] = ($ret === 0);
    $checks['ffmpeg_version'] = $ret === 0 && !empty($out) ? substr(trim($out[0]), 0, 80) : null;
} else {
    $checks['ffmpeg_in_path'] = false;
    $checks['ffmpeg_version'] = '(exec disabled, não testado)';
}

$recommendation = 'hostmidia_convert'; // ou gateway_convert
if (!$checks['can_run_ffmpeg_from_php']) {
    $recommendation = 'gateway_convert';
    $reason = 'exec/shell_exec estão em disable_functions; conversão WebM→OGG deve ser feita no Gateway (VPS).';
} elseif (!$checks['ffmpeg_in_path']) {
    $recommendation = 'gateway_convert';
    $reason = 'ffmpeg não encontrado no PATH do PHP; conversão deve ser feita no Gateway ou instale ffmpeg no servidor.';
} else {
    $reason = 'Ambiente OK para conversão no Hostmidia; fallback para Gateway pode ser usado se ffmpeg falhar.';
}

echo json_encode([
    'checks' => $checks,
    'recommendation' => $recommendation,
    'reason' => $reason,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
