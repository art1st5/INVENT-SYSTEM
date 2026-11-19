<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Owner-facing staff account interface. Customize role labels and form layout here; actual
 * role validation belongs in handlers/users.php and database.sql.
 */
/**
 * src/pages/users.php — Staff account management page (owner only).
 */
?>

<div class="two-col">

    <!-- ── Existing accounts list ──────────────────────────────────────── -->
    <section class="panel">
        <h2>Accounts</h2>
        <table>
            <thead>
                <tr>
                    <th>USERNAME</th>
                    <th>ROLE</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (getall($pdo, 'SELECT user_id, user_name, role FROM users ORDER BY user_id') as $u): ?>
                    <tr>
                        <td><?= h($u['user_name']) ?></td>
                        <td>
                            <span class="pill <?= $u['role'] === 'owner' ? 'warning' : 'muted' ?>">
                                <?= ucfirst(h($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['role'] !== 'owner'): ?>
                                <button class="btn danger-btn mini"
                                    onclick="openDeleteModal(<?= $u['user_id'] ?>, '<?= h(addslashes($u['user_name'])) ?>')">
                                    ✕ Delete
                                </button>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:11px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- ── Add new staff account form ──────────────────────────────────── -->
    <section class="panel form-panel">
        <h2>Add Staff Account</h2>
        <p>All fields are required. Your own password is needed to approve the action.</p>

        <form method="post">
            <input type="hidden" name="csrf"   value="<?= h(csrf()) ?>">
            <input type="hidden" name="action" value="add_user">

            <label>New Username
                <input name="name" required minlength="3" autocomplete="off"
                       placeholder="e.g. juan_dela_cruz">
            </label>

            <label>New Account Password
                <input name="password" type="password" required minlength="10"
                       autocomplete="new-password"
                       placeholder="Minimum 10 characters">
            </label>

            <label>Role
                <select name="role">
                    <option value="employee">Employee</option>
                    <option value="assistant">Assistant</option>
                </select>
            </label>

            <div style="border-top:1px solid var(--border);margin:8px 0 16px;"></div>

            <label>
                Your Password (required to approve)
                <input name="owner_password" type="password" required
                       autocomplete="current-password"
                       placeholder="Enter your own login password">
                <small style="color:var(--muted);margin-top:4px">
                    This confirms you authorise creating this account.
                </small>
            </label>

            <button class="btn primary">Create Account</button>
        </form>
    </section>

</div>

<!-- ── Delete confirmation modal ───────────────────────────────────────── -->
<div class="modal" id="deleteUserModal">
    <div class="modal-card" style="max-width:420px;">
        <div class="modal-head">
            <h2 style="color:var(--danger-text);">Delete Account</h2>
            <button onclick="closeDeleteModal()">✕</button>
        </div>

        <p style="color:var(--muted);margin-bottom:20px;font-size:13px;">
            You are about to permanently delete
            <strong id="deleteUserName" style="color:var(--ink);"></strong>.
            This cannot be undone. Enter your password to confirm.
        </p>

        <form method="post" id="deleteUserForm">
            <input type="hidden" name="csrf"    value="<?= h(csrf()) ?>">
            <input type="hidden" name="action"  value="delete_user">
            <input type="hidden" name="user_id" id="deleteUserId" value="">

            <label style="margin-bottom:16px;">
                Your Password
                <input name="owner_password" type="password" required
                       autocomplete="current-password"
                       placeholder="Enter your login password to confirm">
            </label>

            <div class="modal-foot" style="margin-top:0;">
                <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn danger-btn">Delete Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal(id, name) {
    document.getElementById('deleteUserId').value   = id;
    document.getElementById('deleteUserName').textContent = name;
    // Clear the password field each time it opens
    document.querySelector('#deleteUserForm [name="owner_password"]').value = '';
    document.getElementById('deleteUserModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteUserModal').classList.remove('show');
}

// Close on backdrop click
document.getElementById('deleteUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
