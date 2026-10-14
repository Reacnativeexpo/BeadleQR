<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['role'], $_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$success = '';
$error = '';
$profileFields = ['username', 'fullname', 'email', 'employee_id', 'phone'];
$labels = ['username' => 'Username', 'fullname' => 'Full name', 'email' => 'Email address', 'employee_id' => 'Employee ID', 'phone' => 'Phone number'];
$columns = [];
$user = [];

try {
    // Inspect existing columns so this page works with the fields actually present in users.
    $schema = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'users'");
    $columns = $schema->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'teacher' LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$user) {
        throw new RuntimeException('Teacher account not found. Please sign in again.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $sets = [];
            $params = [':id' => $userId];
            foreach ($profileFields as $field) {
                if (!in_array($field, $columns, true) || !array_key_exists($field, $_POST)) {
                    continue;
                }
                $value = trim((string) $_POST[$field]);
                if (in_array($field, ['username', 'fullname'], true) && $value === '') {
                    throw new RuntimeException($labels[$field] . ' cannot be empty.');
                }
                if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid email address.');
                }
                $sets[] = '"' . $field . '" = :' . $field;
                $params[':' . $field] = $value;
            }
            if (!$sets) {
                throw new RuntimeException('No editable profile fields were found in the users table.');
            }
            $update = $conn->prepare('UPDATE users SET ' . implode(', ', $sets) . " WHERE id = :id AND role = 'teacher'");
            $update->execute($params);
            $success = 'Your profile information has been updated.';
        } elseif ($action === 'password') {
            if (!in_array('password', $columns, true)) {
                throw new RuntimeException('The users table does not contain a password column.');
            }
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if ($current === '' || $new === '' || $confirm === '') {
                throw new RuntimeException('Please complete all password fields.');
            }
            if (!password_verify($current, (string) ($user['password'] ?? ''))) {
                throw new RuntimeException('Your current password is incorrect.');
            }
            if (strlen($new) < 8) {
                throw new RuntimeException('Your new password must contain at least 8 characters.');
            }
            if ($new !== $confirm) {
                throw new RuntimeException('The new password and confirmation do not match.');
            }
            $update = $conn->prepare("UPDATE users SET password = :password WHERE id = :id AND role = 'teacher'");
            $update->execute([':password' => password_hash($new, PASSWORD_DEFAULT), ':id' => $userId]);
            $success = 'Your password has been changed successfully.';
        } else {
            throw new RuntimeException('Invalid settings request.');
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'teacher' LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;
    }
} catch (PDOException $e) {
    $error = 'Unable to save settings. The username or email may already be in use, or the database schema may need checking.';
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

function settingEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Settings | BeadleQR</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--orange:#f97316;--orange-dark:#ea580c;--ink:#172033;--muted:#64748b;--page:#f4f7fb}*{box-sizing:border-box}body{margin:0;background:var(--page);font-family:'Poppins',sans-serif;color:#1e293b}.main-content{margin-left:260px;padding:34px;min-height:100vh}.page-heading{margin-bottom:26px}.eyebrow{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--orange);margin-bottom:8px}h1{font-size:28px;font-weight:700;margin:0;color:var(--ink)}.page-subtitle{color:var(--muted);font-size:13px;margin-top:8px}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;align-items:start}.settings-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:25px;box-shadow:0 8px 28px rgba(15,23,42,.045)}.card-title{display:flex;align-items:center;gap:12px;margin-bottom:7px;font-size:17px;font-weight:700;color:var(--ink)}.title-icon{width:42px;height:42px;border-radius:13px;background:#fff1e8;color:var(--orange-dark);display:grid;place-items:center}.card-desc{font-size:12px;color:var(--muted);margin:0 0 22px 54px;line-height:1.6}.form-label{font-size:12px;font-weight:600;color:#334155;margin-bottom:7px}.form-control{min-height:45px;border-radius:10px;border-color:#dbe3ee;font-size:13px;padding:11px 13px}.form-control:focus{border-color:#fb923c;box-shadow:0 0 0 .2rem rgba(249,115,22,.12)}.form-text{font-size:11px;color:var(--muted)}.btn-orange{background:var(--orange);border:1px solid var(--orange);color:#fff;border-radius:10px;padding:11px 16px;font-size:12px;font-weight:600}.btn-orange:hover{background:var(--orange-dark);border-color:var(--orange-dark);color:#fff}.security-note{display:flex;gap:10px;background:#f8fafc;border:1px solid #e7edf5;border-radius:12px;padding:13px;color:#64748b;font-size:11px;line-height:1.6;margin-top:18px}@media(max-width:1100px){.settings-grid{grid-template-columns:1fr}}@media(max-width:992px){.main-content{margin-left:0;padding:22px 16px}}
</style>
</head>
<body>
<?php include "../asset/teacher_sidebar.php"; ?>
<main class="main-content">
  <header class="page-heading"><div class="eyebrow">Account management</div><h1>Settings</h1><p class="page-subtitle">Manage your teacher profile and keep your account secure.</p></header>
  <?php if ($success): ?><div class="alert alert-success border-0 rounded-3 small"><i class="fa-solid fa-circle-check me-2"></i><?= settingEscape($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger border-0 rounded-3 small"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= settingEscape($error) ?></div><?php endif; ?>
  <div class="settings-grid">
    <section class="settings-card">
      <div class="card-title"><span class="title-icon"><i class="fa-solid fa-user-pen"></i></span>Profile information</div>
      <p class="card-desc">Update the information connected to your teacher account.</p>
      <form method="post" autocomplete="on"><input type="hidden" name="action" value="profile">
      <?php $types = ['email'=>'email','phone'=>'tel']; $rendered = 0; foreach ($profileFields as $field): if (!in_array($field, $columns, true)) continue; $rendered++; ?>
        <div class="mb-3"><label class="form-label" for="<?= settingEscape($field) ?>"><?= settingEscape($labels[$field]) ?></label><input class="form-control" id="<?= settingEscape($field) ?>" name="<?= settingEscape($field) ?>" type="<?= $types[$field] ?? 'text' ?>" value="<?= settingEscape($user[$field] ?? '') ?>" <?= in_array($field, ['username','fullname'], true) ? 'required' : '' ?>></div>
      <?php endforeach; ?>
      <?php if (!$rendered): ?><p class="text-muted small">No editable profile fields were found. Your users table should include username, fullname, email, employee_id, or phone.</p><?php endif; ?>
      <button class="btn btn-orange mt-2" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save profile</button></form>
    </section>
    <section class="settings-card">
      <div class="card-title"><span class="title-icon"><i class="fa-solid fa-shield-halved"></i></span>Change password</div>
      <p class="card-desc">Verify your current password before choosing a new one.</p>
      <form method="post" autocomplete="off"><input type="hidden" name="action" value="password">
        <div class="mb-3"><label class="form-label" for="current_password">Current password</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <div class="mb-3"><label class="form-label" for="new_password">New password</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required><div class="form-text">Use at least 8 characters.</div></div>
        <div class="mb-3"><label class="form-label" for="confirm_password">Confirm new password</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div>
        <button class="btn btn-orange mt-2" type="submit"><i class="fa-solid fa-key me-2"></i>Update password</button>
      </form>
      <div class="security-note"><i class="fa-solid fa-lock mt-1"></i><span>Your new password is stored using PHP's secure password hashing.</span></div>
    </section>
  </div>
</main>
</body>
</html>
