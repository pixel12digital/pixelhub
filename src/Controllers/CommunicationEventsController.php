<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PDO;

/**
 * Controller para visualizar eventos de comunicação
 */
class CommunicationEventsController extends Controller
{
    /**
     * Lista eventos de comunicação
     * 
     * GET /settings/communication-events
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Filtros
        $eventType = $_GET['event_type'] ?? null;
        $sourceSystem = $_GET['source_system'] ?? null;
        $status = $_GET['status'] ?? null;
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
        $traceId = $_GET['trace_id'] ?? null;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Monta query
        $where = [];
        $params = [];

        if ($eventType) {
            $where[] = "event_type LIKE ?";
            $params[] = $eventType;
        }

        if ($sourceSystem) {
            $where[] = "source_system = ?";
            $params[] = $sourceSystem;
        }

        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($tenantId) {
            $where[] = "tenant_id = ?";
            $params[] = $tenantId;
        }

        if ($traceId) {
            $where[] = "trace_id = ?";
            $params[] = $traceId;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Conta total
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM communication_events {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];
        $totalPages = ceil($total / $perPage);

        // Busca eventos
        $sql = "
            SELECT ce.*, 
                   t.name as tenant_name,
                   t.email as tenant_email
            FROM communication_events ce
            LEFT JOIN tenants t ON ce.tenant_id = t.id
            {$whereClause}
            ORDER BY ce.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $events = $stmt->fetchAll();

        // Decodifica JSONs
        foreach ($events as &$event) {
            if (!empty($event['payload'])) {
                $event['payload_decoded'] = json_decode($event['payload'], true);
            }
            if (!empty($event['metadata'])) {
                $event['metadata_decoded'] = json_decode($event['metadata'], true);
            }
        }

        // Estatísticas
        try {
            $statsStmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM communication_events
                GROUP BY status
            ");
            $stats = [];
            foreach ($statsStmt->fetchAll() as $row) {
                $stats[$row['status']] = (int) $row['count'];
            }
        } catch (\Exception $e) {
            $stats = [];
        }

        // Sistemas de origem únicos
        try {
            $sourcesStmt = $db->query("
                SELECT DISTINCT source_system 
                FROM communication_events 
                ORDER BY source_system
            ");
            $sourceSystems = $sourcesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {
            $sourceSystems = [];
        }

        // Tipos de evento únicos
        try {
            $typesStmt = $db->query("
                SELECT DISTINCT event_type 
                FROM communication_events 
                ORDER BY event_type
            ");
            $eventTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {
            $eventTypes = [];
        }

        $this->view('settings.communication_events.index', [
            'events' => $events,
            'stats' => $stats,
            'sourceSystems' => $sourceSystems,
            'eventTypes' => $eventTypes,
            'filters' => [
                'event_type' => $eventType,
                'source_system' => $sourceSystem,
                'status' => $status,
                'tenant_id' => $tenantId,
                'trace_id' => $traceId
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]);
    }

    /**
     * Visualiza detalhes de um evento
     * 
     * GET /settings/communication-events/view?event_id=xxx
     */
    public function show(): void
    {
        Auth::requireInternal();

        $eventId = $_GET['event_id'] ?? null;
        if (empty($eventId)) {
            $this->redirect('/settings/communication-events?error=missing_event_id');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT ce.*, 
                   t.name as tenant_name,
                   t.email as tenant_email
            FROM communication_events ce
            LEFT JOIN tenants t ON ce.tenant_id = t.id
            WHERE ce.event_id = ?
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            $this->redirect('/settings/communication-events?error=event_not_found');
            return;
        }

        // Decodifica JSONs
        $event['payload_decoded'] = json_decode($event['payload'], true);
        $event['metadata_decoded'] = !empty($event['metadata']) 
            ? json_decode($event['metadata'], true) 
            : null;

        // Busca eventos relacionados (mesmo trace_id)
        $relatedStmt = $db->prepare("
            SELECT event_id, event_type, source_system, status, created_at
            FROM communication_events
            WHERE trace_id = ? AND event_id != ?
            ORDER BY created_at ASC
        ");
        $relatedStmt->execute([$event['trace_id'], $eventId]);
        $relatedEvents = $relatedStmt->fetchAll();

        $this->view('settings.communication_events.view', [
            'event' => $event,
            'relatedEvents' => $relatedEvents
        ]);
    }
}

