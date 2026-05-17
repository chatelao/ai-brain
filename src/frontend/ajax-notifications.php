<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\NotificationService;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$notificationService = new NotificationService($db);
$taskModel = new \App\Task($db);
$userId = $auth->getUserId();

// Initialize Markdown parser
if (!class_exists('\Parsedown')) {
    http_response_code(500);
    echo json_encode(['error' => "Class 'Parsedown' not found."]);
    exit;
}
$parsedown = new \Parsedown();
$parsedown->setSafeMode(true);

$action = $_GET['action'] ?? 'unread_count';

try {
    if ($action === 'unread_count') {
        echo json_encode([
            'status' => 'success',
            'unread_count' => $notificationService->getUnreadCount($userId)
        ]);
    } elseif ($action === 'list') {
        $notifications = $notificationService->getNotifications($userId);
        // Process data field
        foreach ($notifications as &$n) {
            if (isset($n['data']) && is_string($n['data'])) {
                $n['data'] = json_decode($n['data'], true);
            }
            $n['title'] = $parsedown->text($taskModel->processGitHubImages($n['title']));
            $n['message'] = $parsedown->text($taskModel->processGitHubImages($n['message']));
        }
        echo json_encode([
            'status' => 'success',
            'notifications' => $notifications
        ]);
    } elseif ($action === 'mark_read') {
        if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }

        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            // Check ownership
            $stmt = $db->getConnection()->prepare("SELECT user_id FROM notifications WHERE notification_id = ?");
            $stmt->execute([$notificationId]);
            $ownerId = $stmt->fetchColumn();

            if ($ownerId == $userId) {
                $notificationService->markAsRead($notificationId);
                echo json_encode(['status' => 'success']);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
            }
        } else {
            echo json_encode(['error' => 'Invalid notification ID']);
        }
    } elseif ($action === 'mark_all_read') {
        if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }

        $notificationService->markAllAsRead($userId);
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'test_broadcast') {
        if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }

        $result = $notificationService->sendTestNotification($userId);
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
