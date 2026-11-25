<?php
$currentAdminId = (int)(auth_user()->id ?? 0);
$search = $search ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$totalUsers = $totalUsers ?? count($users);
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">User &amp; Subscription Management <span class="badge bg-secondary-subtle text-secondary fs-6 ms-1"><?= number_format((int)$totalUsers) ?></span></h4>
        <p class="text-muted small mb-0">Create new user accounts, assign roles, manage subscription plans, grant/reset free trials, and set Gmail limits.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <form action="<?= url('/admin/users') ?>" method="GET" class="d-flex gap-2">
            <div class="input-group input-group-sm" style="min-width: 230px;">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search name, email, or #ID..." value="<?= e($search) ?>">
                <?php if ($search !== ''): ?>
                    <a href="<?= url('/admin/users') ?>" class="btn btn-outline-secondary" title="Clear search"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold">Search</button>
        </form>
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-3 py-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add New User</span>
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 820px;">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Plan &amp; Subscription</th>
                        <th>Gmail Limit</th>
                        <th>Bulk Sender</th>
                        <th>Trial Status</th>
                        <th>Role / Status</th>
                        <th class="text-end" style="width: 195px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="fa-solid fa-users-slash fs-3 mb-2 d-block opacity-50"></i>
                            No users found<?= $search !== '' ? ' matching "' . e($search) . '"' : '' ?>.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                    <?php
                        $isSelf = ((int)$u->id === $currentAdminId);
                        $canBulk = $u->canBulkSend();
                        $initial = strtoupper(substr($u->name !== '' ? $u->name : 'U', 0, 1));
                    ?>
                    <tr>
                        <td class="font-monospace text-muted">#<?= $u->id ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; border-radius: 50%; font-size: 13px;">
                                    <?= e($initial) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= e($u->name) ?></div>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <div><?= e($u->email) ?></div>
                            <?php if ($u->email_verified_at): ?>
                                <span class="badge bg-success-subtle text-success py-0" style="font-size: 9px;"><i class="fa-solid fa-check"></i> Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning py-0" style="font-size: 9px;">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $u->hasActiveSubscription() ? 'bg-success' : ($u->isTrialActive() ? 'bg-info' : 'bg-light text-dark border') ?>">
                                <?= e(ucfirst($u->plan_type)) ?> (<?= e(ucfirst($u->subscription_status)) ?>)
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border fw-bold"><?= (int)$u->getMaxGmailAccounts() ?> Accounts</span>
                        </td>
                        <td>
                            <?php if ($canBulk): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fa-solid fa-check me-1"></i> Enabled
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="fa-solid fa-xmark me-1"></i> Disabled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $u->trial_status === 'active' ? 'bg-info' : ($u->trial_status === 'expired' ? 'bg-danger' : 'bg-secondary') ?>">
                                <?= e(ucfirst($u->trial_status)) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u->role === 'admin'): ?>
                                <span class="badge bg-primary text-white"><i class="fa-solid fa-shield-halved me-1"></i> Admin</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><i class="fa-solid fa-user me-1"></i> User</span>
                            <?php endif; ?>
                            <?php if ($u->status === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check me-1"></i> Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-ban me-1"></i> Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 align-items-center">
                                <?php if ($u->role !== 'admin'): ?>
                                    <!-- Toggle Bulk Sender Permission -->
                                    <form action="<?= url("/admin/users/{$u->id}/toggle-bulk") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm <?= $canBulk ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= $canBulk ? 'Revoke Bulk Sender Permission' : 'Grant Bulk Sender Permission' ?>">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$isSelf): ?>
                                    <!-- Login as User (Impersonate without password) -->
                                    <form action="<?= url("/admin/users/{$u->id}/impersonate") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold" title="Login directly into this user's account without password" data-confirm-msg="Log in as <?= e($u->name) ?> (<?= e($u->email) ?>)? You can return to Admin anytime." onclick="return confirm(this.getAttribute('data-confirm-msg'))">
                                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Edit User Button -->
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary js-edit-user-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-id="<?= (int)$u->id ?>"
                                        data-action="<?= e(url("/admin/users/{$u->id}/update")) ?>"
                                        data-name="<?= e($u->name) ?>"
                                        data-email="<?= e($u->email) ?>"
                                        data-plan-id="<?= (int)($u->plan_id ?? 0) ?>"
                                        data-subscription-status="<?= e($u->subscription_status) ?>"
                                        data-gmail-limit="<?= (int)$u->gmail_limit ?>"
                                        data-trial-status="<?= e($u->trial_status) ?>"
                                        data-can-bulk="<?= $canBulk ? '1' : '0' ?>"
                                        data-role="<?= e($u->role) ?>"
                                        data-status="<?= e($u->status) ?>"
                                        data-is-self="<?= $isSelf ? '1' : '0' ?>"
                                        title="Edit User &amp; Subscription">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <!-- Lead Permissions Button -->
                                <a href="<?= url("/admin/users/{$u->id}/permissions") ?>" class="btn btn-sm btn-outline-info" title="Manage Lead Permissions &amp; RBAC">
                                    <i class="fa-solid fa-user-shield"></i>
                                </a>

                                <?php if (!$isSelf): ?>
                                    <!-- Suspend / Activate Toggle -->
                                    <form action="<?= url("/admin/users/{$u->id}/toggle") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <?php if ($u->status === 'active'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspend User" data-confirm-msg="Suspend user <?= e($u->name) ?>?" onclick="return confirm(this.getAttribute('data-confirm-msg'))">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Activate User">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Delete User -->
                                    <form action="<?= url("/admin/users/{$u->id}/delete") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User" data-confirm-msg="Are you sure you want to delete <?= e($u->name) ?>? All connected accounts and automation data will be deleted!" onclick="return confirm(this.getAttribute('data-confirm-msg'))">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div class="small text-muted">
            Page <strong><?= (int)$page ?></strong> of <strong><?= (int)$totalPages ?></strong> (<?= number_format((int)$totalUsers) ?> total users)
        </div>
        <nav aria-label="Users pagination">
            <ul class="pagination pagination-sm mb-0">
                <?php
                    $queryBase = $search !== '' ? '&q=' . urlencode($search) : '';
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('/admin/users?page=' . $prevPage . $queryBase) ?>">Previous</a>
                </li>
                <?php for ($pNum = $startPage; $pNum <= $endPage; $pNum++): ?>
                <li class="page-item <?= $pNum === (int)$page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('/admin/users?page=' . $pNum . $queryBase) ?>"><?= $pNum ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('/admin/users?page=' . $nextPage . $queryBase) ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Shared Edit User Modal -->
<div class="modal fade text-start" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="editUserForm" action="" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit User &amp; Subscription (<span id="editModalUserId">#0</span>)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Subscription Plan</label>
                            <select name="plan_id" id="edit_plan_id" class="form-select">
                                <option value="0">Free / No Paid Plan</option>
                                <?php foreach ($plans as $p): ?>
                                <option value="<?= (int)$p->id ?>">
                                    <?= e($p->name) ?> Plan ($<?= number_format($p->price, 0) ?>/mo - <?= (int)$p->gmail_limit ?> Gmails)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Subscription Status</label>
                            <select name="subscription_status" id="edit_subscription_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="trialing">Trialing</option>
                                <option value="inactive">Inactive</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Custom Gmail Account Limit</label>
                            <input type="number" name="gmail_limit" id="edit_gmail_limit" class="form-control" min="1" max="1000" value="1">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Trial Status</label>
                            <select name="trial_status" id="edit_trial_status" class="form-select">
                                <option value="not_started">Not Started</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="used">Used</option>
                                <option value="converted">Converted</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3 p-2 bg-light rounded">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_trial" id="edit_reset_trial" value="1">
                        <label class="form-check-label fw-semibold small" for="edit_reset_trial">
                            Reset Free Trial Eligibility (Allows user to start fresh trial)
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3 p-2 bg-light rounded border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="can_bulk_send" id="edit_can_bulk_send" value="1">
                        <label class="form-check-label fw-semibold small text-dark" for="edit_can_bulk_send">
                            <i class="fa-solid fa-paper-plane text-primary me-1"></i> Grant Bulk Sender Permission (Access to Bulk Campaigns)
                        </label>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Role</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="user">User (Standard)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Account Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">New Password (Optional)</label>
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep existing password">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save All Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/admin/users/create') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-user-plus me-2 text-primary"></i> Create New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Initial Subscription Plan</label>
                        <select name="plan_id" class="form-select">
                            <option value="0">Free / No Plan</option>
                            <?php foreach ($plans as $p): ?>
                            <option value="<?= (int)$p->id ?>"><?= e($p->name) ?> Plan ($<?= number_format($p->price, 0) ?>/mo - <?= (int)$p->gmail_limit ?> Gmails)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3 p-2 bg-light rounded border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="can_bulk_send" id="new_can_bulk_send" value="1">
                        <label class="form-check-label fw-semibold small text-dark" for="new_can_bulk_send">
                            <i class="fa-solid fa-paper-plane text-primary me-1"></i> Grant Bulk Sender Permission
                        </label>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Role</label>
                            <select name="role" class="form-select">
                                <option value="user" selected>User (Standard)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-edit-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var isSelf = btn.getAttribute('data-is-self') === '1';
            document.getElementById('editUserForm').setAttribute('action', btn.getAttribute('data-action') || '');
            document.getElementById('editModalUserId').textContent = '#' + (btn.getAttribute('data-id') || '0');
            document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
            document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
            document.getElementById('edit_plan_id').value = btn.getAttribute('data-plan-id') || '0';
            document.getElementById('edit_subscription_status').value = btn.getAttribute('data-subscription-status') || 'inactive';
            document.getElementById('edit_gmail_limit').value = btn.getAttribute('data-gmail-limit') || '1';
            document.getElementById('edit_trial_status').value = btn.getAttribute('data-trial-status') || 'not_started';
            document.getElementById('edit_reset_trial').checked = false;
            document.getElementById('edit_can_bulk_send').checked = btn.getAttribute('data-can-bulk') === '1';
            var roleSelect = document.getElementById('edit_role');
            var statusSelect = document.getElementById('edit_status');
            roleSelect.value = btn.getAttribute('data-role') || 'user';
            statusSelect.value = btn.getAttribute('data-status') || 'active';
            roleSelect.disabled = isSelf;
            statusSelect.disabled = isSelf;
            document.getElementById('edit_password').value = '';
        });
    });
});
</script>
