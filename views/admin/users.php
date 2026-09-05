<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">User &amp; Subscription Management</h4>
        <p class="text-muted small mb-0">Create new user accounts, assign roles, manage subscription plans, grant/reset free trials, and set Gmail limits.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
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
                        <th class="text-end" style="width: 175px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="font-monospace text-muted">#<?= $u->id ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; border-radius: 50%; font-size: 13px;">
                                    <?= strtoupper(substr($u->name, 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= e($u->name) ?></div>
                                    <?php if ($u->id === auth_user()->id): ?>
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
                                <?= ucfirst($u->plan_type) ?> (<?= ucfirst($u->subscription_status) ?>)
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border fw-bold"><?= $u->getMaxGmailAccounts() ?> Accounts</span>
                        </td>
                        <td>
                            <?php if ($u->canBulkSend()): ?>
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
                                <?= ucfirst($u->trial_status) ?>
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
                                        <button type="submit" class="btn btn-sm <?= $u->canBulkSend() ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= $u->canBulkSend() ? 'Revoke Bulk Sender Permission' : 'Grant Bulk Sender Permission' ?>">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($u->id !== auth_user()->id): ?>
                                    <!-- Login as User (Impersonate without password) -->
                                    <form action="<?= url("/admin/users/{$u->id}/impersonate") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold" title="Login directly into this user's account without password" onclick="return confirm('Log in as <?= e($u->name) ?> (<?= e($u->email) ?>)? You can return to Admin anytime.')">
                                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Edit User Button -->
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u->id ?>" title="Edit User & Subscription">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <?php if ($u->id !== auth_user()->id): ?>
                                    <!-- Suspend / Activate Toggle -->
                                    <form action="<?= url("/admin/users/{$u->id}/toggle") ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <?php if ($u->status === 'active'): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspend User" onclick="return confirm('Suspend user <?= e($u->name) ?>?')">
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
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User" onclick="return confirm('Are you sure you want to delete <?= e($u->name) ?>? All connected accounts and automation data will be deleted!')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Edit User Modal -->
                            <div class="modal fade text-start" id="editUserModal<?= $u->id ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <form action="<?= url("/admin/users/{$u->id}/update") ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit User &amp; Subscription (#<?= $u->id ?>)
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($u->name) ?>" required>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                                        <input type="email" name="email" class="form-control" value="<?= e($u->email) ?>" required>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Subscription Plan</label>
                                                        <select name="plan_id" class="form-select">
                                                            <option value="0" <?= empty($u->plan_id) ? 'selected' : '' ?>>Free / No Paid Plan</option>
                                                            <?php foreach ($plans as $p): ?>
                                                            <option value="<?= $p->id ?>" <?= $u->plan_id === $p->id ? 'selected' : '' ?>>
                                                                <?= e($p->name) ?> Plan ($<?= number_format($p->price, 0) ?>/mo - <?= $p->gmail_limit ?> Gmails)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Subscription Status</label>
                                                        <select name="subscription_status" class="form-select">
                                                            <option value="active" <?= $u->subscription_status === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="trialing" <?= $u->subscription_status === 'trialing' ? 'selected' : '' ?>>Trialing</option>
                                                            <option value="inactive" <?= $u->subscription_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            <option value="cancelled" <?= $u->subscription_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                            <option value="expired" <?= $u->subscription_status === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Custom Gmail Account Limit</label>
                                                        <input type="number" name="gmail_limit" class="form-control" min="1" max="1000" value="<?= $u->gmail_limit ?>">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label fw-semibold small">Trial Status</label>
                                                        <select name="trial_status" class="form-select">
                                                            <option value="not_started" <?= $u->trial_status === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                                                            <option value="active" <?= $u->trial_status === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="expired" <?= $u->trial_status === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                            <option value="used" <?= $u->trial_status === 'used' ? 'selected' : '' ?>>Used</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="form-check form-switch mb-3 p-2 bg-light rounded">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_trial" id="reset_trial_<?= $u->id ?>" value="1">
                                                    <label class="form-check-label fw-semibold small" for="reset_trial_<?= $u->id ?>">
                                                        Reset Free Trial Eligibility (Allows user to start fresh trial)
                                                    </label>
                                                </div>

                                                <div class="form-check form-switch mb-3 p-2 bg-light rounded border">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="can_bulk_send" id="can_bulk_send_<?= $u->id ?>" value="1" <?= $u->canBulkSend() ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold small text-dark" for="can_bulk_send_<?= $u->id ?>">
                                                        <i class="fa-solid fa-paper-plane text-primary me-1"></i> Grant Bulk Sender Permission (Access to Bulk Campaigns)
                                                    </label>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold small">Role</label>
                                                        <select name="role" class="form-select" <?= $u->id === auth_user()->id ? 'disabled' : '' ?>>
                                                            <option value="user" <?= $u->role === 'user' ? 'selected' : '' ?>>User (Standard)</option>
                                                            <option value="admin" <?= $u->role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold small">Account Status</label>
                                                        <select name="status" class="form-select" <?= $u->id === auth_user()->id ? 'disabled' : '' ?>>
                                                            <option value="active" <?= $u->status === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="suspended" <?= $u->status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label fw-semibold small">New Password (Optional)</label>
                                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep existing password">
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
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                            <option value="<?= $p->id ?>"><?= e($p->name) ?> Plan ($<?= number_format($p->price, 0) ?>/mo - <?= $p->gmail_limit ?> Gmails)</option>
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
