<?php
require_once __DIR__ . '/../../vendor/autoload.php';

$user = [
    'user_id' => 1,
    'name' => 'Test User',
    'avatar' => 'https://www.gravatar.com/avatar/?d=mp',
    'jules_quota_usage' => 90,
    'jules_quota_limit' => 100
];

$totalTasks = 5;
$openIssues = 2;
$completedTasks = 3;
$telegramConnected = true;

// Mocking needed objects/variables for navbar-icons.php
// navbar-icons.php expects $user, $userModel, $taskModel
// but it checks $user first.
// It also does its own calculations if $user is set.
// I will just override the variables it calculates.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mock Header Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <span class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap">Agent Control</span>
                </div>
                <div class="flex items-center">
                    <?php
                    // Manual include with mocked data
                    $syncStatus = 'success';
                    $syncMessage = 'Issues synced from GitHub';
                    ?>
                    <div class="flex items-center space-x-4 mr-4">
                        <?php if ($syncStatus) : ?>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full <?= $syncStatus === 'success' ? 'bg-green-500' : 'bg-red-500' ?>" title="<?= htmlspecialchars($syncMessage) ?>"></div>
                            </div>
                        <?php endif; ?>

                        <!-- GitHub Status -->
                        <div class="flex items-center text-black" title="GitHub Issues: Open / Total">
                            <svg class="w-5 h-5 mr-1 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                            <span class="text-xs font-bold"><?= $openIssues ?>/<?= $totalTasks ?></span>
                        </div>

                        <!-- Jules Status -->
                        <div class="flex items-center text-black"
                             title="Jules Tasks: Completed / Total <?= (isset($user['jules_quota_limit']) && $user['jules_quota_limit'] > 0) ? '| Daily session limit: (' . $user['jules_quota_usage'] . '/' . $user['jules_quota_limit'] . ')' : '' ?>">
                            <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M21.61,18.91c-.59,0-1.06.48-1.06,1.06s-.48,1.06-1.06,1.06-1.09-.48-1.09-1.06v-5.41c.13-.27.38-.73.38-1.04v-6.03c0-3.68-3.13-6.66-6.81-6.66s-6.66,2.98-6.66,6.66v6.03c0,.43.16.99.38,1.31v5.14c0,.59-.5,1.06-1.09,1.06s-1.06-.48-1.06-1.06-.48-1.06-1.06-1.06-1.06.48-1.06,1.06c0,1.68,1.32,3.05,2.97,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.91-1.48,2.91-3.17v-4.25s0-.89.77-.89.75.89.75.89v4.21c0,.59.43,1.06,1.02,1.06s1.01-.48,1.01-1.06v-4.21s-.1-.89.76-.89.76.89.76.89v4.21c0,.59.42,1.06,1.01,1.06s1.03-.48,1.03-1.06v-4.21s-.02-.89.75-.89.78.89.78.89v4.25c0,1.68,1.25,3.05,2.9,3.17.07.01.14.02.21.02s.15,0,.21-.02c1.66-.11,2.98-1.48,2.98-3.17,0-.59-.48-1.06-1.06-1.06ZM8.5,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33ZM15.59,12.89c-.59,0-1.06-.6-1.06-1.33s.48-1.33,1.06-1.33,1.06.6,1.06,1.33-.48,1.33-1.06,1.33Z"/></svg>
                            <span class="text-xs font-bold">
                                <?= $completedTasks ?>/<?= $totalTasks ?>
                                <?php if (isset($user['jules_quota_limit']) && $user['jules_quota_limit'] > 0) : ?>
                                    <span class="ml-1 text-gray-500 font-normal">(<?= htmlspecialchars($user['jules_quota_usage']) ?>/<?= htmlspecialchars($user['jules_quota_limit']) ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- Telegram Status -->
                        <div class="flex items-center text-black" title="Telegram: Connected">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.665 3.717l-17.73 6.837c-1.21.486-1.203 1.161-.222 1.462l4.552 1.42l10.532-6.645c.498-.303.953-.14.579.192l-8.533 7.701l-.333 4.981c.488 0 .704-.224.977-.488l2.347-2.284l4.882 3.606c.899.496 1.542.24 1.766-.83l3.201-15.084c.328-1.315-.502-1.912-1.362-1.523z"/></svg>
                        </div>
                    </div>

                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar']) ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</body>
</html>
