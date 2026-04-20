<?php
// dashboard_sim.php
$user = $auth->isLoggedIn() ? $userModel->findById($auth->getUserId()) : null;
?>
<div id="dashboard">
    <?php if ($user): ?>
        <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
        <p>Email: <?= htmlspecialchars($user['email']) ?></p>
    <?php else: ?>
        <h1>Please Login</h1>
    <?php endif; ?>
</div>
