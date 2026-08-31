<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">User Management</h4>
        <p class="text-muted small mb-0">View registered users, monitor account statuses, and suspend/activate users.</p>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="font-monospace text-muted">#<?= $u->id ?></td>
                        <td class="fw-semibold"><?= e($u->name) ?></td>
                        <td><?= e($u->email) ?></td>
                        <td>
                            <?php if ($u->role === 'admin'): ?>
                                <span class="badge bg-purple text-white bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u->status === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted font-monospace"><?= date('M d, Y H:i', strtotime($u->created_at)) ?></td>
                        <td class="text-end">
                            <?php if ($u->id !== auth_user()->id): ?>
                            <form action="<?= url("/admin/users/{$u->id}/toggle") ?>" method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <?php if ($u->status === 'active'): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Suspend user <?= e($u->name) ?>?')">
                                        <i class="fa-solid fa-ban me-1"></i> Suspend
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-check me-1"></i> Activate
                                    </button>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
