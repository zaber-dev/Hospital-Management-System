<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN]);
$doctors = doctors_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  verify_csrf_or_die();
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $role     = (string)($_POST['role'] ?? ROLE_STAFF);
  $linkDoc  = ($_POST['linked_doctor_id'] ?? '') !== '' ? (int)$_POST['linked_doctor_id'] : null;
  if ($username && $email && $password) {
    try {
      create_user($username, $email, $password, $role, $linkDoc);
      flash_add('success', 'User created.');
    } catch (Throwable $e) {
      flash_add('error', 'Could not create user (maybe duplicate).');
    }
  }
  redirect('./manage-users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    users_update($id, $_POST);
    flash_add('success', 'User updated.');
  }
  redirect('./manage-users.php');
}

if (($_GET['delete'] ?? '') !== '') {
  users_delete((int)$_GET['delete']);
  flash_add('success', 'User deleted.');
  redirect('./manage-users.php');
}
$p = get_pagination_params(10);
$data = users_list($p['q'], $p['limit'], $p['offset']);
$rows = $data['rows'];
$pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? users_get($editId) : null;
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between gap-4">
      <h2 class="text-xl font-semibold"><?= $editRow ? 'Edit User' : 'Create User' ?></h2>
      <a href="./manage-users.php" class="inline-flex items-center px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">New User</a>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2" name="q" placeholder="Search..." value="<?= htmlspecialchars($p['q']) ?>" />
      <button class="rounded bg-blue-600 text-white px-3 py-2">Search</button>
    </form>
    <form method="post" class="bg-white border rounded p-4 space-y-4">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <label class="block text-sm">Username<input class="w-full border rounded px-3 py-2" name="username" value="<?= htmlspecialchars($editRow['username'] ?? '') ?>" required /></label>
        <label class="block text-sm">Email<input class="w-full border rounded px-3 py-2" name="email" type="email" value="<?= htmlspecialchars($editRow['email'] ?? '') ?>" required /></label>
        <label class="block text-sm">Password <?= $editRow ? '(leave blank to keep)' : '' ?><input class="w-full border rounded px-3 py-2" name="password" type="password" <?= $editRow ? '' : 'required' ?> /></label>
        <label class="block text-sm">Role
          <select name="role" class="border rounded px-3 py-2 w-full">
            <?php foreach (['staff', 'doctor', 'admin'] as $r): ?>
              <option value="<?= $r ?>" <?= ($editRow['role'] ?? 'staff') === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block text-sm">Linked Doctor (optional)
          <select name="linked_doctor_id" class="border rounded px-3 py-2 w-full">
            <option value="">-- None --</option>
            <?php foreach ($doctors as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= $editRow && (int)($editRow['linked_doctor_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Create' ?></button>
        <?php if ($editRow): ?><a class="text-slate-600 hover:underline" href="./manage-users.php">Cancel</a><?php endif; ?>
      </div>
    </form>

    <h2 class="text-xl font-semibold mt-8">Users</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">ID</th>
            <th class="text-left px-4 py-2">Username</th>
            <th class="text-left px-4 py-2">Email</th>
            <th class="text-left px-4 py-2">Role</th>
            <th class="text-left px-4 py-2">Linked Doctor</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= (int)$r['id'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['username']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['email']) ?></td>
              <?php
              $role = (string)($r['role'] ?? 'staff');
              $roleCls = 'border px-2 py-0.5 rounded text-xs';
              if ($role === 'admin') {
                $roleCls .= ' border-purple-200 bg-purple-50 text-purple-700';
              } elseif ($role === 'doctor') {
                $roleCls .= ' border-sky-200 bg-sky-50 text-sky-700';
              } else {
                $roleCls .= ' border-slate-200 bg-slate-50 text-slate-700';
              }
              ?>
              <td class="px-4 py-2"><span class="<?= $roleCls ?>"><?= htmlspecialchars(ucfirst($role)) ?></span></td>
              <td class="px-4 py-2"><?= htmlspecialchars((string)($r['linked_doctor_id'] ?? '')) ?></td>
              <td class="px-4 py-2 text-right space-x-2">
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete user?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr>
              <td class="px-4 py-3" colspan="6">No rows</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <nav aria-label="pagination" class="mt-4">
        <ul class="flex gap-2">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li><a class="px-3 py-1 rounded border <?= $i === $p['page'] ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-100' ?>" href="?page=<?= $i ?>&per=<?= $p['per'] ?>&q=<?= urlencode($p['q']) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </main>
</body>

</html>