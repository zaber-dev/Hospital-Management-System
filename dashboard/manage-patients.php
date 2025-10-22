<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  patients_create($_POST);
  flash_add('success', 'Patient added.');
  redirect('./manage-patients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    patients_update($id, $_POST);
    flash_add('success', 'Patient updated.');
  }
  redirect('./manage-patients.php');
}

if (($_GET['delete'] ?? '') !== '') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  patients_delete((int)$_GET['delete']);
  flash_add('success', 'Patient deleted.');
  redirect('./manage-patients.php');
}
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? patients_get($editId) : null;
$p = get_pagination_params(10);
$data = patients_list($p['q'], $p['limit'], $p['offset']);
$rows = $data['rows'];
$pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Patients</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-slate-800">
        <?= $isDoctor ? 'Patients' : (($editRow) ? 'Edit Patient' : 'Patients') ?>
      </h2>
      <?php if (!$isDoctor && !$editRow): ?>
        <a href="#patient-form" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">New Patient</a>
      <?php endif; ?>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2 w-full max-w-sm" name="q" placeholder="Search patients by name, phone, email..." value="<?= htmlspecialchars($p['q']) ?>" />
      <button type="submit" class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700">Search</button>
    </form>
    <?php if (!$isDoctor): ?>
      <form id="patient-form" method="post" class="bg-white border rounded p-4 space-y-4">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
        <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <label class="block text-sm">First Name<input class="w-full border rounded px-3 py-2" name="first_name" value="<?= htmlspecialchars($editRow['first_name'] ?? '') ?>" required /></label>
          <label class="block text-sm">Last Name<input class="w-full border rounded px-3 py-2" name="last_name" value="<?= htmlspecialchars($editRow['last_name'] ?? '') ?>" required /></label>
          <label class="block text-sm">Gender
            <select class="w-full border rounded px-3 py-2" name="gender">
              <option value="">--</option>
              <?php foreach (['male', 'female', 'other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($editRow['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block text-sm">Birthdate<input class="w-full border rounded px-3 py-2" name="birthdate" type="date" value="<?= htmlspecialchars($editRow['birthdate'] ?? '') ?>" /></label>
          <label class="block text-sm">Phone<input class="w-full border rounded px-3 py-2" name="phone" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>" /></label>
          <label class="block text-sm">Email<input class="w-full border rounded px-3 py-2" name="email" type="email" value="<?= htmlspecialchars($editRow['email'] ?? '') ?>" /></label>
        </div>
        <label class="block text-sm">Address<textarea class="w-full border rounded px-3 py-2" name="address"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea></label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="flex items-center gap-2">
          <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Add' ?></button>
          <?php if ($editRow): ?><a class="text-slate-600 hover:underline" href="./manage-patients.php">Cancel</a><?php endif; ?>
        </div>
      </form>
    <?php endif; ?>

    <h2 class="text-lg font-semibold text-slate-800 mt-8">Recent Patients</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">ID</th>
            <th class="text-left px-4 py-2">Name</th>
            <th class="text-left px-4 py-2">Gender</th>
            <th class="text-left px-4 py-2">Phone</th>
            <th class="text-left px-4 py-2">Email</th>
            <?php if (!$isDoctor): ?><th class="px-4 py-2 text-right">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr class="">
              <td class="px-4 py-2"><?= (int)$r['id'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
              <td class="px-4 py-2">
                <?php $g = strtolower($r['gender'] ?? '');
                $gbg = $g === 'male' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($g === 'female' ? 'bg-pink-50 text-pink-700 border-pink-200' : 'bg-slate-50 text-slate-700 border-slate-200');
                ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded border text-xs <?= $gbg ?>"><?= $g ? ucfirst($g) : '-' ?></span>
              </td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['phone'] ?? '') ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['email'] ?? '') ?></td>
              <?php if (!$isDoctor): ?>
                <td class="px-4 py-2 text-right space-x-2">
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-blue-600 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this patient?')">Delete</a>
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-emerald-600 text-emerald-700 hover:bg-emerald-50" href="manage-rooms.php?patient_id=<?= (int)$r['id'] ?>">Admit</a>
                </td>
              <?php endif; ?>
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