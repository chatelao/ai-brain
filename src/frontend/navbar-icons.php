<?php
/** @var array $user */
/** @var App\User $userModel */
/** @var App\Task $taskModel */

if ($user) {
    $counts = $taskModel->getTaskCounts($user['user_id']);
    $totalTasks = $counts['total'];
    $openIssues = $counts['open_issues'];
    $completedTasks = $counts['completed_tasks'];

    $telegramConnected = (bool)$userModel->getTelegramChatId($user['user_id']);
} else {
    $totalTasks = 0;
    $openIssues = 0;
    $completedTasks = 0;
    $telegramConnected = false;
}

$syncStatus = null;
$syncMessage = '';

if ((isset($_GET['success']) && $_GET['success'] === 'synced') || (isset($_GET['github']) && $_GET['github'] === 'success')) {
    $syncStatus = 'success';
    $syncMessage = (isset($_GET['success']) && $_GET['success'] === 'synced') ? 'Issues synced from GitHub' : 'GitHub account linked correctly';
} elseif (isset($_GET['github']) && $_GET['github'] === 'error') {
    $syncStatus = 'error';
    $syncMessage = 'GitHub error: ' . ($_GET['message'] ?? 'Authentication failed');
} elseif (isset($_GET['error'])) {
    $syncStatus = 'error';
    $syncMessage = $_GET['error'];
}
?>

<div class="flex items-center space-x-4 mr-4" x-data="{
    syncing: <?= $syncStatus ? 'false' : 'true' ?>,
    syncStatus: <?= htmlspecialchars(json_encode($syncStatus)) ?>,
    syncMessage: <?= htmlspecialchars(json_encode($syncMessage)) ?>,
    quotaUsage: <?= (int)($user['jules_quota_usage'] ?? 0) ?>,
    quotaLimit: <?= (int)($user['jules_quota_limit'] ?? 0) ?>,
    openIssues: <?= (int)$openIssues ?>,
    totalTasks: <?= (int)$totalTasks ?>,
    completedTasks: <?= (int)$completedTasks ?>,
    unreadNotifications: 0,
    notifications: [],
    showNotifications: false,
    loadingNotifications: false,
    csrfToken: '<?= $auth->getCsrfToken() ?>',
    init() {
        const basePath = window.location.pathname.includes('/admin/') ? '../' : '';

        // Fetch Sync Status
        if (!this.syncStatus) {
            this.syncing = true;
            const projectId = new URLSearchParams(window.location.search).get('id');
            const url = (projectId ? 'ajax-sync.php?id=' + projectId : 'ajax-sync.php');
            fetch(basePath + url)
                .then(res => res.json())
                .then(data => {
                    this.syncing = false;
                    if (data.status === 'success') {
                        this.syncStatus = 'success';
                        this.syncMessage = 'Refreshed';
                        this.quotaUsage = data.quota_usage;
                        this.quotaLimit = data.quota_limit;
                        this.openIssues = data.open_issues;
                        this.totalTasks = data.total_tasks;
                        this.completedTasks = data.completed_tasks;
                    } else {
                        this.syncStatus = 'error';
                        this.syncMessage = data.error || 'Sync failed';
                    }
                })
                .catch(err => {
                    this.syncing = false;
                    this.syncStatus = 'error';
                    this.syncMessage = 'Connection error';
                });
        }

        // Fetch Unread Notification Count
        fetch(basePath + 'ajax-notifications.php?action=unread_count')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    this.unreadNotifications = data.unread_count;
                }
            });
    },
    fetchNotifications() {
        if (this.showNotifications) return;
        this.showNotifications = true;
        this.loadingNotifications = true;
        const basePath = window.location.pathname.includes('/admin/') ? '../' : '';
        fetch(basePath + 'ajax-notifications.php?action=list')
            .then(res => res.json())
            .then(data => {
                this.loadingNotifications = false;
                if (data.status === 'success') {
                    this.notifications = data.notifications;
                }
            });
    },
    markAsRead(id, url) {
        const notification = this.notifications.find(n => n.notification_id == id);
        const wasRead = notification ? notification.is_read : true;

        const basePath = window.location.pathname.includes('/admin/') ? '../' : '';
        const formData = new FormData();
        formData.append('notification_id', id);
        formData.append('csrf_token', this.csrfToken);

        fetch(basePath + 'ajax-notifications.php?action=mark_read', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (!wasRead) {
                    this.unreadNotifications = Math.max(0, this.unreadNotifications - 1);
                }
                this.notifications = this.notifications.map(n => n.notification_id == id ? { ...n, is_read: 1 } : n);
                if (url) window.open(url, '_blank');
            }
        })
        .catch(err => console.error('Failed to mark notification as read:', err));
    }
}">
    <div class="flex items-center" x-show="syncing || syncStatus">
        <div class="w-3 h-3 rounded-full transition-colors duration-500 <?= !$syncStatus ? 'bg-yellow-400 animate-pulse' : ($syncStatus === 'success' ? 'bg-green-500' : 'bg-red-500') ?>"
             :class="{
                'bg-yellow-400 animate-pulse': syncing,
                'bg-green-500': !syncing && syncStatus === 'success',
                'bg-red-500': !syncing && syncStatus === 'error'
             }"
             :title="syncMessage"></div>
    </div>

    <!-- GitHub Status -->
    <div class="flex items-center <?= $totalTasks > 0 ? 'text-black' : 'text-gray-300' ?>" title="GitHub Issues: Open / Total">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
        <span class="text-xs font-bold"><span x-text="openIssues"><?= $openIssues ?></span>/<span x-text="totalTasks"><?= $totalTasks ?></span></span>
    </div>

    <!-- Jules Status -->
    <div class="flex items-center <?= $totalTasks > 0 ? 'text-black' : 'text-gray-300' ?>"
         :title="'Jules Tasks: Completed / Total ' + (quotaLimit > 0 ? '| Daily session limit: (' + quotaUsage + '/' + quotaLimit + ')' : '')">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .52 5.586 3.004 3.004 0 0 0 5.193 2.019A4 4 0 0 1 12 18c.35 0 .692.045 1.02.13a3.004 3.004 0 0 0 5.193-2.019 4 4 0 0 0 .52-5.586 4 4 0 0 0-2.526-5.77A3 3 0 1 0 12 5M9 14.5a2.5 2.5 0 0 0 2.46-2.019M15 14.5a2.5 2.5 0 0 1-2.46-2.019"/></svg>
        <span class="text-xs font-bold">
            <span x-text="completedTasks"><?= $completedTasks ?></span>/<span x-text="totalTasks"><?= $totalTasks ?></span>
            <template x-if="quotaLimit > 0">
                <span class="ml-1 text-gray-500 font-normal">(<span x-text="quotaUsage"><?= (int)($user['jules_quota_usage'] ?? 0) ?></span>/<span x-text="quotaLimit"><?= (int)($user['jules_quota_limit'] ?? 0) ?></span>)</span>
            </template>
        </span>
    </div>

    <!-- Telegram Status -->
    <div class="flex items-center <?= $telegramConnected ? 'text-black' : 'text-gray-300' ?>" title="Telegram: <?= $telegramConnected ? 'Connected' : 'Not Linked' ?>">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.665 3.717l-17.73 6.837c-1.21.486-1.203 1.161-.222 1.462l4.552 1.42l10.532-6.645c.498-.303.953-.14.579.192l-8.533 7.701l-.333 4.981c.488 0 .704-.224.977-.488l2.347-2.284l4.882 3.606c.899.496 1.542.24 1.766-.83l3.201-15.084c.328-1.315-.502-1.912-1.362-1.523z"/></svg>
    </div>

    <!-- Notification Bell -->
    <div class="relative">
        <button @click="fetchNotifications()" class="flex items-center text-gray-500 hover:text-gray-700 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <template x-if="unreadNotifications > 0">
                <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full" x-text="unreadNotifications"></span>
            </template>
        </button>

        <!-- Dropdown menu -->
        <div x-show="showNotifications" @click.away="showNotifications = false" x-cloak
             class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
            <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <span class="text-sm font-bold text-gray-700">Notifications</span>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <template x-if="loadingNotifications">
                    <div class="p-4 text-center text-gray-500 text-sm">Loading...</div>
                </template>
                <template x-if="!loadingNotifications && notifications.length === 0">
                    <div class="p-4 text-center text-gray-500 text-sm">No notifications yet.</div>
                </template>
                <template x-for="n in notifications" :key="n.notification_id">
                    <div class="p-3 border-b border-gray-50 hover:bg-gray-50 transition-colors relative cursor-pointer"
                         :class="{'bg-blue-50/30': !n.is_read}"
                         @click="markAsRead(n.notification_id, n.data?.source_url)">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-gray-900" x-text="n.title"></span>
                            <span class="text-[10px] text-gray-400" x-text="new Date(n.created_at).toLocaleDateString()"></span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1" x-text="n.message"></p>
                        <template x-if="!n.is_read">
                            <div class="absolute top-3 right-3 w-2 h-2 bg-blue-600 rounded-full"></div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="p-2 border-t border-gray-100 text-center bg-gray-50">
                <a href="#" class="text-xs text-blue-600 hover:underline">View all (Coming Soon)</a>
            </div>
        </div>
    </div>
</div>
