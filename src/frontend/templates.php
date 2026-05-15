<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Auth;
use App\User;
use App\IssueTemplate;

$auth = new Auth();
$db = new Database();
$userModel = new User($db);
$templateModel = new IssueTemplate($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $userModel->findById($auth->getUserId());
$errorMessage = null;
$successMessage = null;

// Handle Template Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_template'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $name = trim($_POST['name']);
    $title = trim($_POST['title_template']);
    $body = trim($_POST['body_template']);

    if (!empty($name) && !empty($title)) {
        try {
            $templateModel->create($user['id'], $name, $title, $body);
            $successMessage = "Template created successfully.";
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = "Name and Title Template are required.";
    }
}

// Handle Template Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }

    $id = (int)$_POST['template_id'];
    $name = trim($_POST['name']);
    $title = trim($_POST['title_template']);
    $body = trim($_POST['body_template']);

    if ($id > 0 && !empty($name) && !empty($title)) {
        try {
            $templateModel->update($id, $user['id'], $name, $title, $body);
            $successMessage = "Template updated successfully.";
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = "Name and Title Template are required.";
    }
}

// Handle Template Deletion
if (isset($_GET['delete_template'])) {
    if (!$auth->validateCsrfToken($_GET['csrf_token'] ?? null)) {
        die("CSRF token validation failed.");
    }
    $templateModel->delete((int)$_GET['delete_template'], $user['id']);
    $successMessage = "Template deleted successfully.";
}

$templates = $templateModel->findByUserId($user['id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Templates - Agent Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal" x-data="{
    editing: false,
    template: {
        id: '',
        name: '',
        title_template: '',
        body_template: ''
    },
    editTemplate(t) {
        this.editing = true;
        this.template = { ...t };
    },
    cancelEdit() {
        this.editing = false;
        this.template = { id: '', name: '', title_template: '', body_template: '' };
    }
}">
    <nav class="bg-white border-b border-gray-200 fixed w-full z-30 top-0">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start">
                    <a href="index.php" class="text-xl font-bold flex items-center lg:ml-2.5">
                        <span class="self-center whitespace-nowrap">Agent Control</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center ml-3">
                        <img class="w-8 h-8 rounded-full" src="<?= htmlspecialchars($user['avatar'] ?? 'https://www.gravatar.com/avatar/?d=mp') ?>" alt="user photo">
                        <div class="ml-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                        <a href="logout.php" class="ml-4 text-sm font-medium text-red-600 hover:underline">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 overflow-hidden bg-gray-50">
        <div id="main-content" class="relative w-full h-full overflow-y-auto bg-gray-50">
            <main>
                <div class="px-4 pt-6">
                    <nav class="flex mb-5" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2">
                            <li class="inline-flex items-center">
                                <a href="index.php" class="text-gray-700 hover:text-gray-900 inline-flex items-center">
                                    <svg class="w-5 h-5 mr-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="text-gray-400 ml-1 md:ml-2 font-medium">Issue Templates</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Issue Templates</h1>
                        <p class="text-sm text-gray-500 mt-1">Create reusable templates for GitHub issues. Use <strong>%1</strong> and <strong>%2</strong> as placeholders for dynamic content.</p>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error!</span> <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                        <div class="lg:col-span-2 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Your Templates</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Name</th>
                                            <th scope="col" class="px-6 py-3">Title Template</th>
                                            <th scope="col" class="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($templates)): ?>
                                            <tr class="bg-white border-b">
                                                <td colspan="3" class="px-6 py-4 text-center">No templates found. Create one using the form.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($templates as $template): ?>
                                            <tr class="bg-white border-b">
                                                <td class="px-6 py-4 font-medium text-gray-900">
                                                    <?= htmlspecialchars($template['name']) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?= htmlspecialchars($template['title_template']) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button @click="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)" class="text-blue-600 hover:text-blue-800 font-medium mr-3">Edit</button>
                                                    <a href="?delete_template=<?= $template['id'] ?>&csrf_token=<?= $auth->getCsrfToken() ?>" class="text-red-600 hover:text-red-800 font-medium" onclick="return confirm('Are you sure?')">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4" x-text="editing ? 'Edit Template' : 'Create New Template'">Create New Template</h3>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $auth->getCsrfToken() ?>">
                                <input type="hidden" name="template_id" x-model="template.id">
                                <div class="mb-4">
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Template Name</label>
                                    <input type="text" name="name" x-model="template.name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Bug Report" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Title Template</label>
                                    <input type="text" name="title_template" x-model="template.title_template" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Bug: %1 in %2" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Body Template</label>
                                    <textarea name="body_template" x-model="template.body_template" rows="6" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Description of the bug: %1&#10;Expected behavior: %2"></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" :name="editing ? 'update_template' : 'create_template'" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full focus:outline-none" x-text="editing ? 'Update Template' : 'Create Template'">Create Template</button>
                                    <button type="button" x-show="editing" @click="cancelEdit()" class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-5 py-2.5 w-full">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
