<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para diagnóstico e logs de erros do sistema
 */
class DiagnosticController extends Controller
{
    /**
     * Diagnóstico Financeiro - Exibe todos os erros relacionados ao módulo financeiro
     * 
     * GET /diagnostic/financial
     */
    public function financial(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Busca erros de sincronização
        $syncErrors = $this->getSyncErrors();
        
        // Busca erros de webhook do Asaas
        $webhookErrors = $this->getWebhookErrors($db);
        
        // Busca erros de cobranças
        $billingErrors = $this->getBillingErrors($db);
        
        // Estatísticas gerais
        $stats = [
            'total_sync_errors' => count($syncErrors),
            'total_webhook_errors' => count($webhookErrors),
            'total_billing_errors' => count($billingErrors),
            'last_sync_error' => !empty($syncErrors) ? $syncErrors[0]['timestamp'] : null,
            'last_webhook_error' => !empty($webhookErrors) ? $webhookErrors[0]['created_at'] : null,
        ];

        $this->view('diagnostic.financial', [
            'syncErrors' => $syncErrors,
            'webhookErrors' => $webhookErrors,
            'billingErrors' => $billingErrors,
            'stats' => $stats,
        ]);
    }

    /**
     * Busca erros de sincronização do arquivo de log
     */
    private function getSyncErrors(): array
    {
        $errors = [];
        
        // Busca erros do arquivo de sincronização específico
        $syncLogFile = __DIR__ . '/../../logs/asaas_sync_errors.log';
        if (file_exists($syncLogFile)) {
            $lines = file($syncLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -100);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.+)$/', $line, $matches)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'message' => $matches[2],
                        'type' => 'sync'
                    ];
                }
            }
        }
        
        // Busca erros gerais do módulo financeiro relacionados a sincronização
        $financialLogFile = __DIR__ . '/../../logs/financial_errors.log';
        if (file_exists($financialLogFile)) {
            $lines = file($financialLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -50);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*\[sync\]\s*(.+)$/', $line, $matches)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'message' => $matches[2],
                        'type' => 'sync'
                    ];
                }
            }
        }
        
        // Ordena por timestamp (mais recentes primeiro)
        usort($errors, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Limita a 100 erros
        return array_slice($errors, 0, 100);
    }

    /**
     * Busca erros de webhook do Asaas do banco de dados
     */
    private function getWebhookErrors($db): array
    {
        $errors = [];

        try {
            // Verifica se a tabela existe
            $stmt = $db->query("SHOW TABLES LIKE 'asaas_webhook_logs'");
            if ($stmt->rowCount() === 0) {
                return $errors;
            }

            // Busca webhooks com erros (payload contém "error" ou status de erro)
            $stmt = $db->query("
                SELECT id, event, payload, created_at
                FROM asaas_webhook_logs
                WHERE payload LIKE '%\"error\"%' 
                   OR payload LIKE '%\"status\":\"ERROR\"%'
                   OR payload LIKE '%\"status\":\"FAILED\"%'
                ORDER BY created_at DESC
                LIMIT 50
            ");
            
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $payload = json_decode($row['payload'], true);
                $errorMessage = 'Erro desconhecido';
                
                if (isset($payload['error'])) {
                    $errorMessage = is_array($payload['error']) ? json_encode($payload['error']) : $payload['error'];
                } elseif (isset($payload['status']) && in_array($payload['status'], ['ERROR', 'FAILED'])) {
                    $errorMessage = "Status: {$payload['status']}";
                }
                
                $errors[] = [
                    'id' => $row['id'],
                    'event' => $row['event'] ?? 'N/A',
                    'message' => $errorMessage,
                    'created_at' => $row['created_at'],
                    'type' => 'webhook',
                    'payload' => $row['payload']
                ];
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar webhook errors: " . $e->getMessage());
        }

        return $errors;
    }

    /**
     * Busca erros relacionados a cobranças (faturas com problemas)
     */
    private function getBillingErrors($db): array
    {
        $errors = [];

        try {
            // Busca faturas com problemas potenciais
            // 1. Faturas com valores inconsistentes
            $stmt = $db->query("
                SELECT id, tenant_id, asaas_payment_id, amount, status, due_date, created_at
                FROM billing_invoices
                WHERE amount <= 0 
                   OR (due_date IS NULL AND status IN ('pending', 'overdue'))
                   OR (asaas_payment_id IS NULL AND status != 'canceled')
                ORDER BY created_at DESC
                LIMIT 50
            ");
            
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $errorMessage = '';
                if ($row['amount'] <= 0) {
                    $errorMessage = "Valor inválido: R$ {$row['amount']}";
                } elseif ($row['due_date'] === null && in_array($row['status'], ['pending', 'overdue'])) {
                    $errorMessage = "Data de vencimento ausente para fatura {$row['status']}";
                } elseif ($row['asaas_payment_id'] === null && $row['status'] !== 'canceled') {
                    $errorMessage = "Fatura sem ID do Asaas";
                }
                
                if ($errorMessage) {
                    $errors[] = [
                        'id' => $row['id'],
                        'invoice_id' => $row['id'],
                        'tenant_id' => $row['tenant_id'],
                        'message' => $errorMessage,
                        'created_at' => $row['created_at'],
                        'type' => 'billing',
                        'status' => $row['status']
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar billing errors: " . $e->getMessage());
        }

        return $errors;
    }

    /**
     * API JSON para buscar erros (para AJAX)
     * 
     * GET /diagnostic/financial/errors?type=sync|webhook|billing
     */
    public function getErrorsJson(): void
    {
        Auth::requireInternal();

        $type = $_GET['type'] ?? 'all';
        $db = DB::getConnection();

        $errors = [];

        if ($type === 'all' || $type === 'sync') {
            $errors['sync'] = $this->getSyncErrors();
        }

        if ($type === 'all' || $type === 'webhook') {
            $errors['webhook'] = $this->getWebhookErrors($db);
        }

        if ($type === 'all' || $type === 'billing') {
            $errors['billing'] = $this->getBillingErrors($db);
        }

        $this->json($errors);
    }
}

