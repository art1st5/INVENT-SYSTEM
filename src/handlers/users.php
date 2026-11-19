<?php
/**
 * src/handlers/users.php
 *
 * POST action handler for staff accounts:
 *   - add_user  (owner only)
 *
 * SECURITY:
 *   Before creating any account, the owner must re-enter their own login
 *   password to confirm the action. This prevents someone from creating
 *   rogue accounts if the owner leaves their session unattended.
 *
 *   Verification steps:
 *     1. Fetch the owner's own record from the database using $_SESSION['uid'].
 *     2. Run password_verify() against the submitted owner_password field.
 *     3. Only proceed if verification passes.
 */

if ($act === 'add_user') {

    // Only owners can reach this action — owner() kills the request otherwise
    owner();

    // ── Collect new account details ───────────────────────────────────────
    $name         = trim($_POST['name']           ?? '');
    $pass         = $_POST['password']            ?? '';
    $role         = $_POST['role']                ?? 'employee';
    $ownerConfirm = $_POST['owner_password']      ?? '';  // Owner's own password for approval

    // ── Basic field validation ────────────────────────────────────────────
    if (strlen($name) < 3)
        throw new Exception('Username must be at least 3 characters.');
    if (strlen($pass) < 10)
        throw new Exception('New account password must be at least 10 characters.');
    if (!in_array($role, ['owner', 'assistant', 'employee'], true))
        throw new Exception('Invalid role selected.');
    if ($ownerConfirm === '')
        throw new Exception('Your password is required to approve this action.');

    // ── Verify the owner's own password ───────────────────────────────────
    // Fetch the current owner's record using their session user ID.
    // This ensures the person sitting at the screen is really the owner
    // and not someone who stumbled onto an unlocked browser.
    $q = $pdo->prepare('SELECT user_pw FROM users WHERE user_id = ? AND active = 1');
    $q->execute([$_SESSION['uid']]);
    $owner = $q->fetch();

    if (!$owner || !password_verify($ownerConfirm, $owner['user_pw'])) {
        throw new Exception('Owner password is incorrect. Account not created.');
    }

    // ── Create the new account ────────────────────────────────────────────
    // password_hash() creates a bcrypt hash — the plain-text password is never stored.
    $pdo->prepare('INSERT INTO users(user_name, user_pw, role) VALUES(?, ?, ?)')
        ->execute([$name, password_hash($pass, PASSWORD_DEFAULT), $role]);

    logit($pdo, 'Create user', "New $role account: $name (approved by owner #" . $_SESSION['uid'] . ")");
    flash("Account \"$name\" created successfully.");
    go('users');
}

if ($act === 'delete_user') {

    // Only owners can delete accounts
    owner();

    $targetId     = (int)($_POST['user_id']       ?? 0);
    $ownerConfirm = $_POST['owner_password']      ?? '';

    if (!$targetId)
        throw new Exception('Invalid account selected.');
    if ($ownerConfirm === '')
        throw new Exception('Your password is required to confirm deletion.');

    // Prevent the owner from deleting their own account
    if ($targetId === (int)$_SESSION['uid'])
        throw new Exception('You cannot delete your own account.');

    // Fetch the target account — make sure it exists and is not an owner
    $q = $pdo->prepare('SELECT user_name, role FROM users WHERE user_id = ?');
    $q->execute([$targetId]);
    $target = $q->fetch();

    if (!$target)
        throw new Exception('Account not found.');
    if ($target['role'] === 'owner')
        throw new Exception('Owner accounts cannot be deleted.');

    // Verify the owner's own password
    $q = $pdo->prepare('SELECT user_pw FROM users WHERE user_id = ? AND active = 1');
    $q->execute([$_SESSION['uid']]);
    $ownerRow = $q->fetch();

    if (!$ownerRow || !password_verify($ownerConfirm, $ownerRow['user_pw']))
        throw new Exception('Password is incorrect. Account not deleted.');

    // Nullify FK references so historical records (transactions, deliveries,
    // audit logs) are preserved after the user account is removed.
    // This requires the columns to be nullable — if your schema has them NOT NULL,
    // run the ALTER statements in database.sql to make them nullable first.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE transactions  SET admin_id = NULL WHERE admin_id = ?')->execute([$targetId]);
        $pdo->prepare('UPDATE deliveries    SET admin_id = NULL WHERE admin_id = ?')->execute([$targetId]);
        $pdo->prepare('UPDATE audit_logs    SET user_id  = NULL WHERE user_id  = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$targetId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    logit($pdo, 'Delete user', "Deleted account: {$target['user_name']} (approved by owner #" . $_SESSION['uid'] . ")");
    flash("Account \"{$target['user_name']}\" has been deleted.");
    go('users');
}
