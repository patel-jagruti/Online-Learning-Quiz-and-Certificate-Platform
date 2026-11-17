<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

if (isset($_GET['mark_all'])) {
    dbExec("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$userId]);
    redirect(APP_URL . '/student/notifications.php');
}

if (isset($_GET['read'])) {
    dbExec("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [(int)$_GET['read'], $userId]);
    redirect(APP_URL . '/student/notifications.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$total = (int)(dbRow("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?", [$userId])['c'] ?? 0);
$notifications = dbRows("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", [$userId]);
$unread = (int)(dbRow("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['c'] ?? 0);

$icons = [
    'success' => ['fa-circle-check', 'text-success', 'bg-success-subtle'],
    'error'   => ['fa-circle-exclamation', 'text-danger', 'bg-danger-subtle'],
    'warning' => ['fa-triangle-exclamation', 'text-warning', 'bg-warning-subtle'],
    'info'    => ['fa-circle-info', 'text-primary', 'bg-primary-subtle'],
];

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h4>
                    <small class="text-muted"><?= $unread ?> unread of <?= number_format($total) ?></small>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread > 0): ?>
                    <a href="<?= APP_URL ?>/student/notifications.php?mark_all=1" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-check-double me-1"></i>Mark all read
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted opacity-50 mb-3"></i>
                    <h6 class="text-muted">You have no notifications</h6>
                    <p class="text-muted small mb-0">Updates about your courses and quizzes will appear here.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n):
                        $icon = $icons[$n['type']] ?? $icons['info'];
                    ?>
                    <a href="<?= APP_URL ?>/student/notifications.php?read=<?= (int)$n['id'] ?>"
                       class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= !$n['is_read'] ? 'bg-light' : '' ?>">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 <?= $icon[2] ?>"
                             style="width:42px;height:42px;">
                            <i class="fas <?= $icon[0] ?> <?= $icon[1] ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="<?= !$n['is_read'] ? 'fw-semibold' : '' ?>"><?= htmlspecialchars($n['message']) ?></div>
                            <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
                        </div>
                        <?php if (!$n['is_read']): ?>
                        <span class="badge bg-primary align-self-center">New</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($total > $perPage): ?>
            <div class="mt-4">
                <?= paginationLinks($total, $page, $perPage, APP_URL . '/student/notifications.php') ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
