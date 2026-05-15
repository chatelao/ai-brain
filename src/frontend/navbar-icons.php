<?php
/** @var array $user */
/** @var App\User $userModel */

if (!isset($user) || !isset($userModel)) {
    return;
}

$hasGitHub = !empty($userModel->getGitHubAccounts($user['id']));
$hasTelegram = !empty($userModel->getTelegramChatId($user['id']));
$hasGoogle = !empty($user['google_id']);

$googleColor = $hasGoogle ? 'text-black' : 'text-gray-300';
$githubColor = $hasGitHub ? 'text-black' : 'text-gray-300';
$telegramColor = $hasTelegram ? 'text-black' : 'text-gray-300';
?>

<div class="flex items-center space-x-2 mr-4">
    <a href="accounts.php" title="Google Connection" class="<?= $googleColor ?>">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.908 3.152-1.928 4.176-1.288 1.288-3.312 2.688-6.88 2.688-5.544 0-10.016-4.504-10.016-10.016s4.472-10.016 10.016-10.016c3.12 0 5.392 1.224 7.064 2.816l2.304-2.304C18.816 1.152 16.032 0 12.48 0 5.864 0 .424 5.44.424 12s5.44 12 12.056 12c3.576 0 6.264-1.176 8.36-3.344 2.16-2.16 2.84-5.216 2.84-7.664 0-.736-.064-1.424-.184-2.08H12.48z"/></svg>
    </a>
    <a href="accounts.php" title="GitHub Connection" class="<?= $githubColor ?>">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
    </a>
    <a href="accounts.php" title="Telegram Connection" class="<?= $telegramColor ?>">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.14-.257.257-.527.257l.214-3.053 5.57-5.032c.242-.214-.053-.332-.375-.118l-6.88 4.33-2.954-.924c-.642-.204-.654-.642.134-.948l11.54-4.448c.534-.194 1.001.124.832.943z"/></svg>
    </a>
</div>
