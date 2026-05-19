<?php

/** @var array $user */
/** @var App\User $userModel */
/** @var App\Task $taskModel */

if ($user) {
    $counts = $taskModel->getTaskCounts($user['user_id']);
    $totalTasks = $counts['total'] ?? 0;
    $openIssues = $counts['open_issues'] ?? 0;
    $completedTasks = $counts['completed_tasks'] ?? 0;
    $julesAnalyzing = $counts['jules_analyzing'] ?? 0;
    $julesExecuting = $counts['jules_executing'] ?? 0;
    $julesFailed = $counts['jules_failed'] ?? 0;
    $githubRunning = $counts['github_running'] ?? 0;
    $githubPassed = $counts['github_passed'] ?? 0;
    $githubFailed = $counts['github_failed'] ?? 0;

    $quotaUsage = (int)($user['jules_quota_usage'] ?? 0);
    $quotaLimit = (int)($user['jules_quota_limit'] ?? 0);

    $telegramConnected = (bool)$userModel->getTelegramChatId($user['user_id']);
} else {
    $totalTasks = 0;
    $openIssues = 0;
    $completedTasks = 0;
    $julesAnalyzing = 0;
    $julesExecuting = 0;
    $julesFailed = 0;
    $githubRunning = 0;
    $githubPassed = 0;
    $githubFailed = 0;
    $quotaUsage = 0;
    $quotaLimit = 0;
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
    quotaUsage: <?= (int)$quotaUsage ?>,
    quotaLimit: <?= (int)$quotaLimit ?>,
    openIssues: <?= (int)$openIssues ?>,
    totalTasks: <?= (int)$totalTasks ?>,
    completedTasks: <?= (int)$completedTasks ?>,
    julesAnalyzing: <?= (int)$julesAnalyzing ?>,
    julesExecuting: <?= (int)$julesExecuting ?>,
    julesFailed: <?= (int)$julesFailed ?>,
    githubRunning: <?= (int)$githubRunning ?>,
    githubPassed: <?= (int)$githubPassed ?>,
    githubFailed: <?= (int)$githubFailed ?>,
    unreadNotifications: 0,
    notifications: [],
    showNotifications: false,
    loadingNotifications: false,
    lastSeenNotificationId: 0,
    csrfToken: '<?= $auth->getCsrfToken() ?>',
    basePath: window.location.pathname.includes('/admin/') ? '../' : '',
    init() {
        const basePath = this.basePath;

        // Initial fetch for last seen ID
        fetch(basePath + 'ajax-notifications.php?action=unread_count')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    this.unreadNotifications = data.unread_count;
                    if (data.notifications && data.notifications.length > 0) {
                        this.lastSeenNotificationId = data.notifications[0].notification_id;
                    }
                }
            });

        // Polling for updates
        setInterval(() => {
            fetch(basePath + 'ajax-notifications.php?action=unread_count')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Check for new notifications
                        if (data.notifications && data.notifications.length > 0) {
                            const newNotifications = data.notifications.filter(n => n.notification_id > this.lastSeenNotificationId);
                            if (newNotifications.length > 0) {
                                this.lastSeenNotificationId = data.notifications[0].notification_id;

                                // Dispatch event for other components
                                window.dispatchEvent(new CustomEvent('sync-updated', { detail: data }));

                                // Trigger Browser Notification
                                if (data.settings?.browser && 'Notification' in window && Notification.permission === 'granted') {
                                    newNotifications.forEach(n => {
                                        const notification = new Notification(n.title_plain, {
                                            body: n.message_plain,
                                            icon: basePath + 'favicon.svg'
                                        });
                                        notification.onclick = () => {
                                            window.focus();
                                            if (n.data?.source_url) {
                                                window.open(n.data.source_url, '_blank');
                                            }
                                        };
                                    });
                                }
                            }
                        }
                        this.unreadNotifications = data.unread_count;
                    }
                });
        }, 15000);

        // Fetch Sync Status
        if (!this.syncStatus) {
            this.syncing = true;
            const params = new URLSearchParams(window.location.search);
            const projectId = params.get('id');
            const isTaskPage = window.location.pathname.includes('task.php');

            let url = 'ajax-sync.php';
            if (isTaskPage && projectId) {
                url += '?task_id=' + projectId;
            } else if (projectId) {
                url += '?id=' + projectId;
            }

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
                        this.julesAnalyzing = data.jules_analyzing;
                        this.julesExecuting = data.jules_executing;
                        this.julesFailed = data.jules_failed;
                        this.githubRunning = data.github_running;
                        this.githubPassed = data.github_passed;
                        this.githubFailed = data.github_failed;
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

    },
    fetchNotifications() {
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
    <div class="flex items-center <?= $totalTasks > 0 ? 'text-black' : 'text-gray-300' ?>" title="GitHub PR Status: Running (Orange), Passed (Green), Failed (Red)">
        <svg class="w-5 h-5 mr-1 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
        <span class="text-xs font-bold space-x-1">
            <a :href="basePath + 'tasks.php?filter=github_running'" class="text-orange-600 hover:underline" x-text="githubRunning"><?= $githubRunning ?></a>
            <a :href="basePath + 'tasks.php?filter=github_passed'" class="text-green-600 hover:underline" x-text="githubPassed"><?= $githubPassed ?></a>
            <a :href="basePath + 'tasks.php?filter=github_failed'" class="text-red-600 hover:underline" x-text="githubFailed"><?= $githubFailed ?></a>
        </span>
    </div>

    <!-- Jules Status -->
    <div class="flex items-center <?= $totalTasks > 0 ? 'text-black' : 'text-gray-300' ?>"
         title="Jules Sessions: Remaining (Green), Analyzing (Blue), Executing (Yellow), Failed (Red)">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M21.61,18.91c-.59,0-1.06.48-1.06,1.06s-.48,1.06-1.06,1.06-1.09-.48-1.09-1.06v-5.41c.13-.27.38-.73.38-1.04v-6.03c0-3.68-3.13-6.66-6.81-6.66s-6.66,2.98-6.66,6.66v6.03c0,.43.16.99.38,1.31v5.14c0,.59-.5,1.06-1.09,1.06s-1.06-.48-1.06-1.06-.48-1.06-1.06-1.06-1.06.48-1.06,1.06c0,1.68,1.32,3.05,2.97,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.91-1.48,2.91-3.17v-4.25s0-.89.77-.89.75.89.75.89v4.21c0,.59.43,1.06,1.02,1.06s1.01-.48,1.01-1.06v-4.21s-.1-.89.76-.89.76.89.76.89v4.21c0,.59.42,1.06,1.01,1.06s1.03-.48,1.03-1.06v-4.21s-.02-.89.75-.89.78.89.78.89v4.25c0,1.68,1.25,3.05,2.9,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.98-1.48,2.98-3.17,0-.59-.48-1.06-1.06-1.06ZM8.5,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33ZM15.59,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33Z"/></svg>
        <span class="text-xs font-bold space-x-1">
            <a :href="basePath + 'tasks.php?filter=open_issues'" class="text-green-600 hover:underline" x-text="quotaLimit > 0 ? (quotaLimit - quotaUsage) : 0"><?= max(0, $quotaLimit - $quotaUsage) ?></a>
            <a :href="basePath + 'tasks.php?filter=jules_analyzing'" class="text-blue-600 hover:underline" x-text="julesAnalyzing"><?= $julesAnalyzing ?></a>
            <a :href="basePath + 'tasks.php?filter=jules_executing'" class="text-yellow-600 hover:underline" x-text="julesExecuting"><?= $julesExecuting ?></a>
            <a :href="basePath + 'tasks.php?filter=jules_failed'" class="text-red-600 hover:underline" x-text="julesFailed"><?= $julesFailed ?></a>
        </span>
    </div>

    <!-- Telegram Status -->
    <div class="flex items-center <?= $telegramConnected ? 'text-black' : 'text-gray-300' ?>" title="Telegram: <?= $telegramConnected ? 'Connected' : 'Not Linked' ?>">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.665 3.717l-17.73 6.837c-1.21.486-1.203 1.161-.222 1.462l4.552 1.42l10.532-6.645c.498-.303.953-.14.579.192l-8.533 7.701l-.333 4.981c.488 0 .704-.224.977-.488l2.347-2.284l4.882 3.606c.899.496 1.542.24 1.766-.83l3.201-15.084c.328-1.315-.502-1.912-1.362-1.523z"/></svg>
    </div>

    <!-- Notification Bell -->
    <div class="relative" @keydown.escape.window="showNotifications = false">
        <button @click="showNotifications = !showNotifications; if (showNotifications) fetchNotifications()" class="flex items-center text-gray-500 hover:text-gray-700 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <template x-if="unreadNotifications > 0">
                <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full" x-text="unreadNotifications"></span>
            </template>
        </button>

        <!-- Dropdown menu -->
        <div x-show="showNotifications" @click.outside="showNotifications = false" x-cloak
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
                            <div class="text-xs font-bold text-gray-900 markdown-body" x-html="n.title"></div>
                            <span class="text-[10px] text-gray-400 shrink-0 ml-2" x-text="new Date(n.created_at).toLocaleDateString()"></span>
                        </div>
                        <div class="text-xs text-gray-600 mt-1 markdown-body" x-html="n.message"></div>
                        <template x-if="!n.is_read">
                            <div class="absolute top-3 right-3 w-2 h-2 bg-blue-600 rounded-full"></div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="p-2 border-t border-gray-100 text-center bg-gray-50">
                <a href="notifications.php" class="text-xs text-blue-600 hover:underline">View all</a>
            </div>
        </div>
    </div>
</div>
