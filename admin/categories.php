<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name        = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon'] ?? 'fas fa-folder');
        $slug        = generateSlug($name);
        if ($name) {
            // ensure unique slug
            $exists = $pdo->prepare("SELECT id FROM categories WHERE slug=?");
            $exists->execute([$slug]);
            if ($exists->fetch()) $slug .= '-' . time();
            $pdo->prepare("INSERT INTO categories (name, slug, description, icon) VALUES (?,?,?,?)")
                ->execute([$name, $slug, $description, $icon]);
            flash('success', 'Category added successfully.');
        } else {
            flash('error', 'Category name is required.');
        }
        redirect(APP_URL . '/admin/categories.php');
    }

    if ($action === 'edit') {
        $id          = (int)($_POST['cat_id'] ?? 0);
        $name        = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon'] ?? 'fas fa-folder');
        if ($id && $name) {
            $pdo->prepare("UPDATE categories SET name=?, description=?, icon=? WHERE id=?")
                ->execute([$name, $description, $icon, $id]);
            flash('success', 'Category updated successfully.');
        } else {
            flash('error', 'Invalid data.');
        }
        redirect(APP_URL . '/admin/categories.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['cat_id'] ?? 0);
        if ($id) {
            // Check if any courses use this category
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id=?");
            $cnt->execute([$id]);
            if ($cnt->fetchColumn() > 0) {
                flash('error', 'Cannot delete: courses exist in this category.');
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
                flash('success', 'Category deleted.');
            }
        }
        redirect(APP_URL . '/admin/categories.php');
    }
}

// Fetch categories with course count
$categories = $pdo->query("
    SELECT c.*, COUNT(co.id) AS course_count
    FROM categories c
    LEFT JOIN courses co ON co.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll();

$pageTitle = 'Category Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-tags me-2 text-success"></i>Category Management</h4>
                <small class="text-muted"><?= count($categories) ?> categories</small>
            </div>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php $err = flash('error'); if ($err): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Add Category Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Category</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Mathematics" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Short description..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Icon <small class="text-muted">(Font Awesome class)</small></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="iconPreview"><i class="fas fa-folder"></i></span>
                                    <input type="text" name="icon" id="iconInput" class="form-control" value="fas fa-folder" placeholder="fas fa-book">
                                </div>
                                <small class="text-muted">e.g. <code>fas fa-calculator</code>, <code>fas fa-flask</code>, <code>fas fa-code</code></small>
                            </div>
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-save me-2"></i>Add Category</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>All Categories</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Slug</th>
                                        <th>Description</th>
                                        <th class="text-center">Courses</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr id="row-<?= $cat['id'] ?>">
                                        <td>
                                            <span class="badge bg-light text-dark fs-5 p-2">
                                                <i class="<?= htmlspecialchars($cat['icon']) ?>"></i>
                                            </span>
                                        </td>
                                        <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                                        <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                        <td><small class="text-muted"><?= htmlspecialchars(truncate($cat['description'], 50)) ?></small></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill"><?= $cat['course_count'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="Edit"
                                                    onclick="openEdit(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($cat['course_count'] == 0): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                                    <button class="btn btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled title="Has courses"><i class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($categories)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No categories yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="cat_id" id="editCatId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="editCatDesc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Icon (Font Awesome class)</label>
                        <div class="input-group">
                            <span class="input-group-text" id="editIconPreview"><i class="fas fa-folder"></i></span>
                            <input type="text" name="icon" id="editCatIcon" class="form-control">
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
// Live icon preview on add form
document.getElementById('iconInput').addEventListener('input', function() {
    document.querySelector('#iconPreview i').className = this.value;
});

function openEdit(cat) {
    document.getElementById('editCatId').value   = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatDesc').value = cat.description || '';
    document.getElementById('editCatIcon').value = cat.icon || 'fas fa-folder';
    document.querySelector('#editIconPreview i').className = cat.icon || 'fas fa-folder';
}

document.getElementById('editCatIcon').addEventListener('input', function() {
    document.querySelector('#editIconPreview i').className = this.value;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
