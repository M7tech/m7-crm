<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

if (has_users()) {
    header('Location: login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Enter a name between 2 and 100 characters.';
    } elseif (!valid_username($username)) {
        $error = 'Use 3–100 letters, numbers, dots, dashes, @, or underscores for the username.';
    } elseif (strlen($password) < 12) {
        $error = 'The password must contain at least 12 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        $now = gmdate('c');
        $statement = database()->prepare(
            'INSERT INTO users (name, username, password_hash, role, active, created_at, updated_at)
             VALUES (:name, :username, :password_hash, \'admin\', 1, :created_at, :updated_at)'
        );
        $statement->execute([
            'name' => $name,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) database()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        log_event($userId, 'initial_admin_created');
        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create administrator</title><link rel="stylesheet" href="auth.css"></head>
<body>
<main class="auth-shell">
    <section class="card">
        <div class="brand">Sheet Sender</div>
        <h1>Create the administrator</h1>
        <p>This first account controls who can access the sending tool.</p>
        <?php if ($error): ?><div class="error" role="alert"><?= auth_e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= auth_e(csrf_token()) ?>">
            <label for="name">Full name</label>
            <input id="name" name="name" value="<?= auth_e($_POST['name'] ?? '') ?>" maxlength="100" autocomplete="name" required autofocus>
            <label for="username">Username or email</label>
            <input id="username" name="username" value="<?= auth_e($_POST['username'] ?? '') ?>" maxlength="100" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
            <div class="actions"><button class="primary" type="submit">Create administrator</button></div>
        </form>
    </section>
</main>
</body>
</html>
