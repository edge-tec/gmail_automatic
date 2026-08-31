<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">User Management</h4>
        <p class="text-muted small mb-0">Create new user accounts, assign roles, reset passwords, and manage statuses.</p>
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
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th class="text-end" style="width: 220px;">Actions</th>
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
                        <td><?= e($u->email) ?></td>
                        <td>
                            <?php if ($u->role === 'admin'): ?>
                                <span class="badge bg-primary text-white"><i class="fa-solid fa-shield-halved me-1"></i> Admin</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><i class="fa-solid fa-user me-1"></i> User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u->status === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i> Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted font-monospace"><?= date('M d, Y H:i', strtotime($u->created_at)) ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <!-- Edit User Button -->
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u->id ?>" title="Edit User">
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
                            <div class="modal fade text-start" id="editUserModal<?= $u->id ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= $u->id ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="<?= url("/admin/users/{$u->id}/update") ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold" id="editUserModalLabel<?= $u->id ?>">
                                                    <i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit User Account
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control" value="<?= e($u->name) ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                                    <input type="email" name="email" class="form-control" value="<?= e($u->email) ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">New Password</label>
                                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep existing password" minlength="6">
                                                    <div class="form-text">Only enter a new password if you want to reset it (min. 6 characters).</div>
                                                </div>

                                                <div class="row g-3">
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold">Role</label>
                                                        <select name="role" class="form-select" <?= $u->id === auth_user()->id ? 'disabled' : '' ?>>
                                                            <option value="user" <?= $u->role === 'user' ? 'selected' : '' ?>>User (Standard)</option>
                                                            <option value="admin" <?= $u->role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                        <?php if ($u->id === auth_user()->id): ?>
                                                            <input type="hidden" name="role" value="admin">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold">Status</label>
                                                        <select name="status" class="form-select" <?= $u->id === auth_user()->id ? 'disabled' : '' ?>>
                                                            <option value="active" <?= $u->status === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="suspended" <?= $u->status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                        </select>
                                                        <?php if ($u->id === auth_user()->id): ?>
                                                            <input type="hidden" name="status" value="active">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
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
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/admin/users/create') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="createUserModalLabel">
                        <i class="fa-solid fa-user-plus me-2 text-primary"></i> Create New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" class="form-select">
                                <option value="user" selected>User (Standard)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                        <i class="fa-solid fa-check"></i>
                        <span>Create User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
