<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
$currentUser = require_admin();
$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'sender');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                throw new RuntimeException('Enter a name between 2 and 100 characters.');
            }
            if (!valid_username($username)) {
                throw new RuntimeException('Enter a valid username or email.');
            }
            if (strlen($password) < 12) {
                throw new RuntimeException('The password must contain at least 12 characters.');
            }
            if (!in_array($role, ['admin', 'sender'], true)) {
                throw new RuntimeException('Choose a valid role.');
            }
            $now = gmdate('c');
            $statement = database()->prepare(
                'INSERT INTO users (name, username, password_hash, role, active, created_at, updated_at)
                 VALUES (:name, :username, :password_hash, :role, 1, :created_at, :updated_at)'
            );
            $statement->execute([
                'name' => $name,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            log_event((int) $currentUser['id'], 'user_created', 'username='.$username.'; role='.$role);
            $notice = 'The user was created.';
        } elseif ($action === 'toggle') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$targetId || $targetId === (int) $currentUser['id']) {
                throw new RuntimeException('You cannot disable your own administrator account.');
            }
            $statement = database()->prepare('SELECT id, username, role, active FROM users WHERE id = :id');
            $statement->execute(['id' => $targetId]);
            $target = $statement->fetch();
            if (!$target) {
                throw new RuntimeException('The user was not found.');
            }
            $newState = (int) $target['active'] === 1 ? 0 : 1;
            if ($target['role'] === 'admin' && $newState === 0) {
                $activeAdmins = (int) database()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();
                if ($activeAdmins <= 1) {
                    throw new RuntimeException('At least one active administrator is required.');
                }
            }
            $update = database()->prepare('UPDATE users SET active = :active, updated_at = :updated_at WHERE id = :id');
            $update->execute(['active' => $newState, 'updated_at' => gmdate('c'), 'id' => $targetId]);
            log_event((int) $currentUser['id'], 'user_status_changed', 'username='.$target['username'].'; active='.$newState);
            $notice = $newState === 1 ? 'The user was activated.' : 'The user was disabled.';
        } elseif ($action === 'password') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $password = (string) ($_POST['new_password'] ?? '');
            if (!$targetId || strlen($password) < 12) {
                throw new RuntimeException('Enter a new password containing at least 12 characters.');
            }
            $statement = database()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
            $statement->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => gmdate('c'),
                'id' => $targetId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The user was not found.');
            }
            log_event((int) $currentUser['id'], 'user_password_reset', 'user_id='.$targetId);
            $notice = 'The password was reset.';
        }
    } catch (PDOException $exception) {
        $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'That username already exists.' : 'The user change could not be saved.';
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

$users = database()->query('SELECT id, name, username, role, active, created_at FROM users ORDER BY name, id')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>User manager</title><link rel="stylesheet" href="auth.css"></head>
<body>
<main class="wide-shell">
    <header class="topbar">
        <div><div class="brand">Sheet Sender</div><h1>User manager</h1><small>Signed in as <?= auth_e($currentUser['name']) ?></small></div>
        <nav><a class="button secondary" href="index.php">WhatsApp</a><a class="button secondary" href="email.php">Email sender</a><a class="button secondary" href="email-settings.php">Email settings</a><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><button class="danger" type="submit">Sign out</button></form></nav>
    </header>
    <?php if ($error): ?><div class="error" role="alert"><?= auth_e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="notice" role="status"><?= auth_e($notice) ?></div><?php endif; ?>
    <div class="grid">
        <section class="card">
            <h2>Add a user</h2><p>Senders can use the spreadsheet tool. Administrators can also manage accounts.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <label for="name">Full name</label><input id="name" name="name" maxlength="100" required>
                <label for="username">Username or email</label><input id="username" name="username" maxlength="100" required>
                <label for="password">Temporary password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                <label for="role">Role</label><select id="role" name="role"><option value="sender">Sender</option><option value="admin">Administrator</option></select>
                <div class="actions"><button class="primary" type="submit">Create user</button></div>
            </form>
        </section>
        <section class="card">
            <h2>Authorized users</h2><p>Disabled accounts are signed out and cannot open the sending page.</p>
            <div class="table-wrap"><table><thead><tr><th>User</th><th>Access</th><th>Controls</th></tr></thead><tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= auth_e($user['name']) ?></strong><br><small><?= auth_e($user['username']) ?></small></td>
                    <td><span class="badge <?= (int) $user['active'] === 1 ? '' : 'off' ?>"><?= auth_e(ucfirst($user['role'])) ?> · <?= (int) $user['active'] === 1 ? 'Active' : 'Disabled' ?></span></td>
                    <td>
                        <?php if ((int) $user['id'] !== (int) $currentUser['id']): ?>
                            <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><button class="<?= (int) $user['active'] === 1 ? 'danger' : 'secondary' ?>" type="submit"><?= (int) $user['active'] === 1 ? 'Disable' : 'Activate' ?></button></form>
                        <?php else: ?><small>Current account</small><?php endif; ?>
                        <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>"><input type="hidden" name="action" value="password"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input name="new_password" type="password" minlength="12" placeholder="New password" aria-label="New password for <?= auth_e($user['name']) ?>" required><button class="secondary" type="submit">Reset</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>
    </div>
</main>
</body>
</html>
