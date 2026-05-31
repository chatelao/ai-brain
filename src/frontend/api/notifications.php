<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\NotificationService;

header('Content-Type: application/json');

$db = new Database();
$auth = new Auth($db);
$userId = $auth->getAuthenticatedUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notificationService = new NotificationService($db);
$taskModel = new \App\Task($db);

// Initialize Markdown parser
if (!class_exists('\Parsedown')) {
    http_response_code(500);
    echo json_encode(['error' => "Class 'Parsedown' not found."]);
    exit;
}
$parsedown = new \Parsedown();
$parsedown->setSafeMode(true);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'unread_count') {
            $unreadCount = $notificationService->getUnreadCount($userId);
            $latestUnread = [];
            if ($unreadCount > 0) {
                // Get latest 5 unread notifications
                $latestUnread = $notificationService->getLatestUnread($userId, 5);
                foreach ($latestUnread as &$n) {
                    if (isset($n['data']) && is_string($n['data'])) {
                        $n['data'] = json_decode($n['data'], true);
                    }
                    $n['title_plain'] = strip_tags($n['title']);
                    $n['message_plain'] = ($n['github_repo'] ? ($n['github_repo'] . "\n") : "") . strip_tags($n['message']);
                }
            }

            echo json_encode([
                'status' => 'success',
                'unread_count' => $unreadCount,
                'notifications' => $latestUnread,
                'settings' => $notificationService->getUserSettings($userId)
            ]);
        } else {
            // Default to list
            $notifications = $notificationService->getNotifications($userId);
            foreach ($notifications as &$n) {
                if (isset($n['data']) && is_string($n['data'])) {
                    $n['data'] = json_decode($n['data'], true);
                }
                $n['title_plain'] = strip_tags($n['title']);
                $n['message_plain'] = ($n['github_repo'] ? ($n['github_repo'] . "\n") : "") . strip_tags($n['message']);

                // Also provide HTML versions as per spec
                $n['title'] = $parsedown->text($taskModel->processGitHubImages($n['title']));
                $n['message'] = $parsedown->text($taskModel->processGitHubImages($n['message']));
            }
            echo json_encode([
                'status' => 'success',
                'notifications' => $notifications
            ]);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationId = (int)($input['notification_id'] ?? 0);
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
                http_response_code(400);
                echo json_encode(['error' => 'Invalid notification ID']);
            }
        } elseif ($action === 'mark_all_read') {
            $notificationService->markAllAsRead($userId);
            echo json_encode(['status' => 'success']);
        } elseif ($action === 'clear_all') {
            $notificationService->deleteAllNotifications($userId);
            echo json_encode(['status' => 'success']);
        } elseif ($action === 'test_broadcast') {
            $result = $notificationService->sendTestNotification($userId);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
