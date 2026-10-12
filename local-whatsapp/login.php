<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

if (!has_users()) {
    header('Location: setup.php');
    exit;
}
if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $blockedUntil = (int) ($_SESSION['login_blocked_until'] ?? 0);
    if ($blockedUntil > time()) {
        $error = 'Too many attempts. Try again in '.($blockedUntil - time()).' seconds.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $statement = database()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if ($user && (int) $user['active'] === 1 && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
            log_event((int) $user['id'], 'login');
            header('Location: index.php');
            exit;
        }

        $_SESSION['login_attempts'] = ((int) ($_SESSION['login_attempts'] ?? 0)) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_blocked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'The username or password is incorrect, or the account is disabled.';
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in</title><link rel="stylesheet" href="auth.css"></head>
<body>
<main class="auth-shell">
    <section class="card">
        <div class="brand">Sheet Sender</div>
        <h1>Authorized access</h1>
        <p>Sign in with an account created by the administrator.</p>
        <?php if ($error): ?><div class="error" role="alert"><?= auth_e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>">
            <label for="username">Username or email</label>
            <input id="username" name="username" value="<?= auth_e($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <div class="actions"><button class="primary" type="submit">Sign in</button></div>
        </form>
    </section>
</main>
</body>
</html>
