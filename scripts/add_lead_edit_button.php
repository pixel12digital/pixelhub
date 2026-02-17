<?php

/**
 * Script para adicionar botão de edição de lead na view de opportunities
 */

// Caminho do arquivo a ser modificado
$filePath = __DIR__ . '/../views/opportunities/view.php';

// Lê o conteúdo atual do arquivo
$content = file_get_contents($filePath);

// Encontra a linha específica onde adicionar o botão de edição
// Procuramos pelo contexto único da seção de lead
$pattern = '/(<\/div>\s*<\/div>\s*<div style="display: flex; gap: 6px;">\s*<\?php if \(\$hasPhone\):)/s';

// Substituição com o botão de edição
$replacement = '</div>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button onclick="window.location.href=\'<?= pixelhub_url(\'/tenants/edit?id=\' . ($opp[\'tenant_id\'] ?? $opp[\'lead_id\'])) ?>\'" 
                                title="Editar Lead"
                                style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #6c757d; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <?php if ($hasPhone):';

// Realiza a substituição
$newContent = preg_replace($pattern, $replacement, $content);

if ($newContent !== null) {
    // Salva o arquivo modificado
    file_put_contents($filePath, $newContent);
    echo "✅ Botão de edição de lead adicionado com sucesso!\n";
} else {
    echo "❌ Erro ao adicionar botão de edição de lead.\n";
    echo "Verifique se o arquivo existe e se o padrão foi encontrado.\n";
}
