<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        flash('error', 'Invalid security token. Please try again.');
        redirect(APP_URL . '/admin/settings.php');
    }

    $fields = [
        'site_name', 'site_description', 'site_email', 'allow_registration',
        'default_role', 'items_per_page', 'enable_certificates',
        'currency', 'currency_symbol', 'maintenance_mode',
    ];

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($fields as $key) {
        if (in_array($key, ['allow_registration', 'enable_certificates', 'maintenance_mode'], true)) {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = trim($_POST[$key] ?? '');
        }
        $stmt->execute([$key, $value]);
    }

    unset($_SESSION['_settings']);
    flash('success', 'Settings saved successfully.');
    redirect(APP_URL . '/admin/settings.php');
}

$settings = getSettings();
$pageTitle = 'Site Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-cog me-2 text-secondary"></i>Site Settings</h4>
                <small class="text-muted">Configure the platform</small>
            </div>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php $err = flash('error'); if ($err): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">General</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Site Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? APP_NAME) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Site Description</label>
                                <textarea name="site_description" class="form-control" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Contact Email</label>
                                    <input type="email" name="site_email" class="form-control" value="<?= htmlspecialchars($settings['site_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Default Role for New Users</label>
                                    <select name="default_role" class="form-select">
                                        <?php foreach (['student', 'instructor'] as $r): ?>
                                        <option value="<?= $r ?>" <?= ($settings['default_role'] ?? 'student') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Localization &amp; Display</h6></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Currency Code</label>
                                    <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency'] ?? 'USD') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '$') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Items Per Page</label>
                                    <input type="number" name="items_per_page" class="form-control" min="1" max="100" value="<?= (int)($settings['items_per_page'] ?? 12) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Features</h6></div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_registration" id="allow_registration" value="1"
                                    <?= ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allow_registration">Allow public registration</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_certificates" id="enable_certificates" value="1"
                                    <?= ($settings['enable_certificates'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable_certificates">Enable certificates</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1"
                                    <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">Maintenance mode</label>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
