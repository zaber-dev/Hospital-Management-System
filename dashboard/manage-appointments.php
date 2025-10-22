<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = null;
if ($isDoctor) {
  $doctorId = (int)($user['linked_doctor_id'] ?? 0) ?: null;
}
$doctorUnlinked = $isDoctor && !$doctorId;

$patients = patients_options();
$doctors  = doctors_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  appointments_create($_POST);
  flash_add('success', 'Appointment created.');
  redirect('./manage-appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    if ($isDoctor) {
      if ($doctorUnlinked) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
      $row = appointments_get($id);
      if (!$row || !$doctorId || (int)$row['doctor_id'] !== $doctorId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
      appointments_update($id, [
        'patient_id' => $row['patient_id'],
        'doctor_id' => $row['doctor_id'],
        'appointment_date' => $row['appointment_date'],
        'status' => $_POST['status'] ?? $row['status'],
        'notes' => $_POST['notes'] ?? $row['notes'],
      ]);
    } else {
      appointments_update($id, $_POST);
    }
    flash_add('success', 'Appointment updated.');
  }
  redirect('./manage-appointments.php');
}

if (($_GET['delete'] ?? '') !== '') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  appointments_delete((int)$_GET['delete']);
  flash_add('success', 'Appointment deleted.');
  redirect('./manage-appointments.php');
}
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? appointments_get($editId) : null;
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} elseif ($isDoctor && $doctorId !== null) {
  $data = appointments_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = appointments_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Appointments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-xl font-semibold">
        <?php if ($editRow): ?>Edit Appointment<?php else: ?>
        <?= $isDoctor ? 'Appointments' : 'Add Appointment' ?>
      <?php endif; ?>
      </h2>
      <?php if (!$isDoctor): ?>
        <a href="./manage-appointments.php" class="inline-flex items-center px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">New Appointment</a>
      <?php endif; ?>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2" name="q" placeholder="Search by patient, doctor, or status" value="<?= htmlspecialchars($p['q']) ?>" />
      <button class="rounded bg-blue-600 text-white px-3 py-2">Search</button>
    </form>
    <?php if (!($isDoctor && !$editRow)): ?>
      <form method="post" class="bg-white border rounded p-4 space-y-4">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
        <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <label>Patient
            <select name="patient_id" required class="border rounded px-3 py-2 w-full" <?= ($isDoctor) ? 'disabled' : '' ?>>
              <?php foreach ($patients as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $editRow && (int)$editRow['patient_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Doctor
            <select name="doctor_id" required class="border rounded px-3 py-2 w-full" <?= ($isDoctor) ? 'disabled' : '' ?>>
              <?php foreach ($doctors as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $editRow && (int)$editRow['doctor_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php
          $dtVal = '';
          if ($editRow) {
            $dt = $editRow['appointment_date'];
            if ($dt && strpos($dt, ' ') !== false) {
              $dtVal = str_replace(' ', 'T', substr($dt, 0, 16));
            } else {
              $dtVal = $dt;
            }
          }
          ?>
          <label class="block text-sm">Date & Time<input class="w-full border rounded px-3 py-2" name="appointment_date" type="datetime-local" value="<?= htmlspecialchars($dtVal) ?>" required <?= ($isDoctor) ? 'disabled' : '' ?> /></label>
          <label class="block text-sm">Status
            <select name="status" class="w-full border rounded px-3 py-2">
              <?php foreach (['scheduled', 'completed', 'cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= ($editRow['status'] ?? 'scheduled') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label class="block text-sm">Notes<textarea class="w-full border rounded px-3 py-2" name="notes"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea></label>
        <?php if ($isDoctor): ?><p class="text-xs text-slate-500">As a doctor, you may update Status and Notes only.</p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="flex items-center gap-2">
          <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Add' ?></button>
          <?php if ($editRow): ?><a class="text-slate-600 hover:underline" href="./manage-appointments.php">Cancel</a><?php endif; ?>
        </div>
      </form>
      <?php if ($isDoctor && $editRow): ?>
        <?php
        $pat = patients_get((int)$editRow['patient_id']);
        ?>
        <?php if ($pat): ?>
          <div class="mt-4 bg-white border rounded p-4 text-sm">
            <div class="font-semibold mb-2">Patient Summary</div>
            <div class="grid sm:grid-cols-2 gap-2">
              <div><span class="text-slate-500">Name:</span> <?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?></div>
              <div><span class="text-slate-500">Gender:</span> <?= htmlspecialchars(ucfirst((string)($pat['gender'] ?? ''))) ?></div>
              <div><span class="text-slate-500">Birthdate:</span> <?= htmlspecialchars((string)($pat['birthdate'] ?? '')) ?></div>
              <div><span class="text-slate-500">Phone:</span> <?= htmlspecialchars((string)($pat['phone'] ?? '')) ?></div>
              <div class="sm:col-span-2"><span class="text-slate-500">Address:</span> <?= htmlspecialchars((string)($pat['address'] ?? '')) ?></div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

    <h2 class="text-xl font-semibold mt-8">Appointments</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">When</th>
            <th class="text-left px-4 py-2">Patient</th>
            <?php if (!$isDoctor): ?><th class="text-left px-4 py-2">Doctor</th><?php endif; ?>
            <th class="text-left px-4 py-2">Status</th>
            <th class="px-4 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= htmlspecialchars($r['appointment_date']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['pf'] . ' ' . $r['pl']) ?></td>
              <?php if (!$isDoctor): ?><td class="px-4 py-2"><?= htmlspecialchars($r['df'] . ' ' . $r['dl']) ?></td><?php endif; ?>
              <?php
              $st = (string)($r['status'] ?? 'scheduled');
              $badgeCls = 'border px-2 py-0.5 rounded text-xs';
              if ($st === 'completed') {
                $badgeCls .= ' border-emerald-200 bg-emerald-50 text-emerald-700';
              } elseif ($st === 'cancelled') {
                $badgeCls .= ' border-red-200 bg-red-50 text-red-700';
              } else {
                $badgeCls .= ' border-amber-200 bg-amber-50 text-amber-700';
              }
              ?>
              <td class="px-4 py-2"><span class="<?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
              <td class="px-4 py-2 text-right space-x-2">
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                <?php if ($isDoctor && $doctorId && (int)($r['doctor_id'] ?? 0) === (int)$doctorId && ($r['status'] ?? 'scheduled') !== 'cancelled'): ?>
                  <a class="inline-flex items-center px-2.5 py-1 rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50" href="manage-treatments.php?appointment_id=<?= (int)$r['id'] ?>">Add Treatment</a>
                <?php endif; ?>
                <?php if (!($isDoctor)): ?>
                  <a class="inline-flex items-center px-2.5 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete appointment?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr>
              <td class="px-4 py-3" colspan="5">No rows</td>
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