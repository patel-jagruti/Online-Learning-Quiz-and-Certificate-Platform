<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $name     = sanitize($_POST['name'] ?? '');
        $role     = in_array($_POST['role'], ['admin','instructor','student']) ? $_POST['role'] : 'student';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE users SET name=?, role=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $role, $isActive, $id]);
            flash('success', 'User updated successfully.');
        } else {
            flash('error', 'Invalid data provided.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== (int)$_SESSION['user_id']) {
            // Soft delete
            $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
            flash('success', 'User deactivated successfully.');
        } else {
            flash('error', 'Cannot deactivate yourself.');
        }
        redirect(APP_URL . '/admin/users.php');
    }
}

// --- Filters ---
$search  = sanitize($_GET['search'] ?? '');
$roleF   = sanitize($_GET['role'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleF && in_array($roleF, ['admin','instructor','student'])) {
    $where .= " AND role=?";
    $params[] = $roleF;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("SELECT id, name, email, role, is_active, created_at FROM users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// User for edit modal
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id=?");
    $editUser->execute([(int)$_GET['edit']]);
    $editUser = $editUser->fetch();
}

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-users me-2 text-primary"></i>User Management</h4>
                <small class="text-muted"><?= number_format($totalCount) ?> total users</small>
            </div>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php $err = flash('error'); if ($err): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin"      <?= $roleF==='admin'      ?'selected':'' ?>>Admin</option>
                            <option value="instructor" <?= $roleF==='instructor' ?'selected':'' ?>>Instructor</option>
                            <option value="student"    <?= $roleF==='student'    ?'selected':'' ?>>Student</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#ID</th>
                                <th>Name / Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><span class="text-muted">#<?= $u['id'] ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:35px;height:35px;font-size:0.85rem;flex-shrink:0;">
                                            <?= strtoupper(substr($u['name'],0,1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php $rc = ['admin'=>'danger','instructor'=>'warning','student'=>'info'][$u['role']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $rc ?>"><?= ucfirst($u['role']) ?></span>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= formatDate($u['created_at'], 'M d, Y') ?></small></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="?edit=<?= $u['id'] ?>&<?= http_build_query(['search'=>$search,'role'=>$roleF,'page'=>$page]) ?>" class="btn btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="loadEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this user?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Deactivate"><i class="fas fa-ban"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p===$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleF) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" id="editRole" class="form-select">
                            <option value="student">Student</option>
                            <option value="instructor">Instructor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
                            <label class="form-check-label fw-semibold" for="editActive">Active Account</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadEdit(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value   = user.name;
    document.getElementById('editRole').value   = user.role;
    document.getElementById('editActive').checked = user.is_active == 1;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
