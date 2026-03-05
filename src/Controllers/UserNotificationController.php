<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PDO;

/**
 * Controller para notificações de usuário
 * Notificações geradas pelo chatbot, CRM e eventos de leads
 */
class UserNotificationController extends Controller
{
    /**
     * Lista notificações não lidas do usuário atual
     * GET /api/notifications
     */
    public function index(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $user = Auth::user();
            $userId = $user['id'] ?? 0;
            $db = DB::getConnection();

            $stmt = $db->prepare("
                SELECT id, type, title, message, entity_type, entity_id, data, is_read, created_at
                FROM user_notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $unreadCount = 0;
            foreach ($notifications as &$n) {
                $n['data'] = $n['data'] ? json_decode($n['data'], true) : null;
                if (!$n['is_read']) $unreadCount++;
            }

            $this->json([
                'success'      => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'notifications' => [], 'unread_count' => 0], 500);
        }
    }

    /**
     * Conta notificações não lidas (polling leve)
     * GET /api/notifications/unread-count
     */
    public function unreadCount(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $user = Auth::user();
            $userId = $user['id'] ?? 0;
            $db = DB::getConnection();

            $stmt = $db->prepare("
                SELECT id, type, title, message, entity_type, entity_id, data, created_at
                FROM user_notifications
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $unread = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($unread as &$n) {
                $n['data'] = $n['data'] ? json_decode($n['data'], true) : null;
            }

            $this->json([
                'success'      => true,
                'unread_count' => count($unread),
                'unread'       => $unread,
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'unread_count' => 0, 'unread' => []], 500);
        }
    }

    /**
     * Marca notificações como lidas
     * POST /api/notifications/read
     * Body: { "id": 123 } ou { "all": true }
     */
    public function markRead(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $user = Auth::user();
            $userId = $user['id'] ?? 0;
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $db = DB::getConnection();

            if (!empty($input['all'])) {
                $db->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0")
                   ->execute([$userId]);
            } elseif (!empty($input['id'])) {
                $db->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")
                   ->execute([(int)$input['id'], $userId]);
            }

            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
