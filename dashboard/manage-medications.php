<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  verify_csrf_or_die();
  try {
    medications_create($_POST);
    flash_add('success', 'Medication added.');
  } catch (Throwable $e) {
    flash_add('error', 'Could not add medication (maybe duplicate).');
  }
  redirect('./manage-medications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    medications_update($id, $_POST);
    flash_add('success', 'Medication updated.');
  }
  redirect('./manage-medications.php');
}

if (($_GET['delete'] ?? '') !== '') {
  medications_delete((int)$_GET['delete']);
  flash_add('success', 'Medication deleted.');
  redirect('./manage-medications.php');
}
$p = get_pagination_params(10);
$data = medications_list($p['q'], $p['limit'], $p['offset']);
$rows = $data['rows'];
$pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? medications_get($editId) : null;
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Medications</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-slate-800"><?= $editRow ? 'Edit Medication' : 'Medications' ?></h2>
      <?php if (!$editRow): ?>
        <a href="#medication-form" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">New Medication</a>
      <?php endif; ?>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2 w-full max-w-sm" name="q" placeholder="Search medications..." value="<?= htmlspecialchars($p['q']) ?>" />
      <button type="submit" class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700">Search</button>
    </form>
    <form id="medication-form" method="post" class="bg-white border rounded p-4 space-y-4">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block text-sm">Name<input class="w-full border rounded px-3 py-2" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required /></label>
        <label class="block text-sm">Description<textarea class="w-full border rounded px-3 py-2" name="description"><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea></label>
      </div>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a class="text-slate-600 hover:underline" href="./manage-medications.php">Cancel</a><?php endif; ?>
      </div>
    </form>

    <h2 class="text-lg font-semibold text-slate-800 mt-8">Medications</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">ID</th>
            <th class="text-left px-4 py-2">Name</th>
            <th class="text-left px-4 py-2">Description</th>
            <th class="text-right px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= (int)$r['id'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['description'] ?? '') ?></td>
              <td class="px-4 py-2 text-right space-x-2">
                <a class="inline-flex items-center px-3 py-1.5 rounded border border-blue-600 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                <a class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this medication?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr>
              <td class="px-4 py-3" colspan="4">No rows</td>
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