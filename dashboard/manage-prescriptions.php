<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : null;
$doctorUnlinked = $isDoctor && !$doctorId;
$treatmentsParamId = isset($_GET['treatment_id']) ? (int)$_GET['treatment_id'] : 0;
$treatments = $isDoctor && $doctorId ? treatments_options_recent_with_patient_for_doctor($doctorId, 200) : treatments_options_recent_with_patient(200);
$meds = medications_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  verify_csrf_or_die();
  if ($isDoctor && $doctorId) {
    $t = treatments_get((int)($_POST['treatment_id'] ?? 0));
    if (!$t || (int)($t['doctor_id'] ?? 0) !== $doctorId) {
      http_response_code(403);
      echo 'Forbidden';
      exit;
    }
  }
  prescriptions_create($_POST);
  flash_add('success', 'Prescription added.');
  redirect('./manage-prescriptions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    if ($isDoctor && $doctorId) {
      $row = prescriptions_get($id);
      $t = $row ? treatments_get((int)$row['treatment_id']) : null;
      if (!$row || !$t || (int)($t['doctor_id'] ?? 0) !== $doctorId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
    }
    prescriptions_update($id, $_POST);
    flash_add('success', 'Prescription updated.');
  }
  redirect('./manage-prescriptions.php');
}

if (($_GET['delete'] ?? '') !== '') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  prescriptions_delete((int)$_GET['delete']);
  flash_add('success', 'Prescription deleted.');
  redirect('./manage-prescriptions.php');
}
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? prescriptions_get($editId) : null;
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} else if ($isDoctor && $doctorId) {
  $data = prescriptions_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = prescriptions_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Prescriptions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-slate-800"><?= $editRow ? 'Edit Prescription' : 'Prescriptions' ?></h2>
      <?php if (!$doctorUnlinked && !$editRow): ?>
        <a href="#prescription-form" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">New Prescription</a>
      <?php endif; ?>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2 w-full max-w-sm" name="q" placeholder="Search by patient, medication..." value="<?= htmlspecialchars($p['q']) ?>" />
      <button type="submit" class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700">Search</button>
    </form>

    <?php if ($editRow): ?>
      <?php
      $t = treatments_get((int)$editRow['treatment_id']);
      $p = $t ? patients_get((int)$t['patient_id']) : null;
      $d = $t ? doctors_get((int)$t['doctor_id']) : null;
      ?>
      <div class="mt-4 bg-white border rounded p-4 text-sm">
        <div class="font-semibold mb-2">Linked Treatment</div>
        <div class="grid sm:grid-cols-2 gap-2">
          <div><span class="text-slate-500">Date:</span> <?= htmlspecialchars((string)($t['treatment_date'] ?? '')) ?></div>
          <?php if ($d): ?>
            <div><span class="text-slate-500">Doctor:</span> <?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></div>
          <?php endif; ?>
          <?php if ($p): ?>
            <div class="sm:col-span-2"><span class="text-slate-500">Patient:</span> <?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <form id="prescription-form" method="post" class="bg-white border rounded p-4 space-y-4">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <label>Treatment
          <select name="treatment_id" required class="border rounded px-3 py-2 w-full" <?= $treatmentsParamId ? 'disabled' : '' ?>>
            <?php foreach ($treatments as $t):
              $selected = $editRow ? ((int)$editRow['treatment_id'] === (int)$t['id']) : ($treatmentsParamId && (int)$t['id'] === $treatmentsParamId);
            ?>
              <option value="<?= (int)$t['id'] ?>" <?= $selected ? 'selected' : '' ?>>#<?= (int)$t['id'] ?> - <?= htmlspecialchars($t['treatment_date'] . ' - ' . $t['first_name'] . ' ' . $t['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($treatmentsParamId): ?><input type="hidden" name="treatment_id" value="<?= (int)$treatmentsParamId ?>" /><?php endif; ?>
        </label>
        <label>Medication
          <select name="medication_id" required class="border rounded px-3 py-2 w-full">
            <?php foreach ($meds as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= $editRow && (int)$editRow['medication_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block text-sm">Dosage<input class="w-full border rounded px-3 py-2" name="dosage" value="<?= htmlspecialchars($editRow['dosage'] ?? '') ?>" required /></label>
        <label class="block text-sm">Frequency<input class="w-full border rounded px-3 py-2" name="frequency" value="<?= htmlspecialchars($editRow['frequency'] ?? '') ?>" required /></label>
        <label class="block text-sm">Duration (days)<input class="w-full border rounded px-3 py-2" name="duration_days" type="number" min="1" value="<?= htmlspecialchars((string)($editRow['duration_days'] ?? '')) ?>" required /></label>
      </div>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a class="text-slate-600 hover:underline" href="./manage-prescriptions.php">Cancel</a><?php endif; ?>
      </div>
    </form>

    <?php if (!$editRow && $treatmentsParamId): ?>
      <?php
      $t = treatments_get((int)$treatmentsParamId);
      $p = $t ? patients_get((int)$t['patient_id']) : null;
      $d = $t ? doctors_get((int)$t['doctor_id']) : null;
      ?>
      <?php if ($t): ?>
        <div class="mt-4 bg-white border rounded p-4 text-sm">
          <div class="font-semibold mb-2">Linked Treatment</div>
          <div class="grid sm:grid-cols-2 gap-2">
            <div><span class="text-slate-500">Date:</span> <?= htmlspecialchars((string)($t['treatment_date'] ?? '')) ?></div>
            <?php if ($d): ?>
              <div><span class="text-slate-500">Doctor:</span> <?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if ($p): ?>
              <div class="sm:col-span-2"><span class="text-slate-500">Patient:</span> <?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <h2 class="text-lg font-semibold text-slate-800 mt-8">Prescriptions</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">ID</th>
            <th class="text-left px-4 py-2">Patient</th>
            <th class="text-left px-4 py-2">Medication</th>
            <th class="text-left px-4 py-2">Dosage</th>
            <th class="text-left px-4 py-2">Frequency</th>
            <th class="text-left px-4 py-2">Days</th>
            <th class="px-4 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= (int)$r['id'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['med_name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['dosage']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['frequency']) ?></td>
              <td class="px-4 py-2"><?= (int)$r['duration_days'] ?></td>
              <td class="px-4 py-2 text-right space-x-2">
                <a class="inline-flex items-center px-3 py-1.5 rounded border border-blue-600 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                <?php if (!$isDoctor): ?>
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this prescription?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr>
              <td class="px-4 py-3" colspan="7">No rows</td>
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