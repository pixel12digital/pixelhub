<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Env;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\EventNormalizationService;
use PixelHub\Services\EventRouterService;

/**
 * Controller para ingestão de eventos de sistemas internos
 * 
 * Rota: POST /api/events
 */
class EventIngestionController extends Controller
{
    /**
     * Recebe evento de sistema interno
     */
    public function handle(): void
    {
        header('Content-Type: application/json');

        // Valida secret
        $expectedSecret = Env::get('EVENT_INGESTION_SECRET');
        if (!empty($expectedSecret)) {
            $secretHeader = $_SERVER['HTTP_X_EVENT_SECRET'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            // Remove "Bearer " se presente
            if ($secretHeader && strpos($secretHeader, 'Bearer ') === 0) {
                $secretHeader = substr($secretHeader, 7);
            }
            
            if ($secretHeader !== $expectedSecret) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid event secret'
                ]);
                return;
            }
        }

        // Lê payload JSON
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ]);
            return;
        }

        // Valida campos obrigatórios
        $eventType = $data['event_type'] ?? null;
        $sourceSystem = $data['source_system'] ?? null;
        $payload = $data['payload'] ?? null;

        if (empty($eventType) || empty($sourceSystem) || $payload === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'event_type, source_system e payload são obrigatórios'
            ]);
            return;
        }

        try {
            // Ingere evento
            $eventId = EventIngestionService::ingest([
                'event_type' => $eventType,
                'source_system' => $sourceSystem,
                'payload' => $payload,
                'tenant_id' => $data['tenant_id'] ?? null,
                'trace_id' => $data['trace_id'] ?? null,
                'correlation_id' => $data['correlation_id'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ]);

            // Busca evento para normalizar e rotear
            $event = EventIngestionService::findByEventId($eventId);
            if ($event) {
                $normalized = EventNormalizationService::normalize($event);
                EventRouterService::route($normalized);
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'event_id' => $eventId
            ]);
        } catch (\Exception $e) {
            error_log("[EventIngestion] Erro: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error ingesting event: ' . $e->getMessage()
            ]);
        }
    }
}

