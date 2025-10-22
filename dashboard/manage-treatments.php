<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : null;
$doctorUnlinked = $isDoctor && !$doctorId;
$patients = patients_options();
$doctors  = doctors_options();
$apptsParamId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$appts    = appointments_minimal_recent(300);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  verify_csrf_or_die();
  $payload = $_POST;
  if ($isDoctor && $doctorId) {
    $payload['doctor_id'] = $doctorId;
  }
  treatments_create($payload);
  flash_add('success', 'Treatment added.');
  redirect('./manage-treatments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    if ($isDoctor) {
      $row = treatments_get($id);
      if (!$row || !$doctorId || (int)($row['doctor_id'] ?? 0) !== $doctorId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
    }
    $payload = $_POST;
    if ($isDoctor && $doctorId) {
      $payload['doctor_id'] = $doctorId;
    }
    treatments_update($id, $payload);
    flash_add('success', 'Treatment updated.');
  }
  redirect('./manage-treatments.php');
}

if (($_GET['delete'] ?? '') !== '') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  treatments_delete((int)$_GET['delete']);
  flash_add('success', 'Treatment deleted.');
  redirect('./manage-treatments.php');
}
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? treatments_get($editId) : null;
$userOwnsEdit = true;
if ($isDoctor && $editId) {
  $userOwnsEdit = $editRow && $doctorId && (int)($editRow['doctor_id'] ?? 0) === (int)$doctorId;
  if (!$userOwnsEdit) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} else if ($isDoctor && $doctorId) {
  $data = treatments_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = treatments_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Treatments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-slate-800"><?= $editRow ? 'Edit Treatment' : 'Treatments' ?></h2>
      <?php if (!$doctorUnlinked && !$editRow): ?>
        <a href="#treatment-form" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">New Treatment</a>
      <?php endif; ?>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2 w-full max-w-sm" name="q" placeholder="Search by patient, doctor, diagnosis..." value="<?= htmlspecialchars($p['q']) ?>" />
      <button type="submit" class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700">Search</button>
    </form>

    <?php if ($isDoctor && $editRow): ?>
      <?php $pat = patients_get((int)$editRow['patient_id']); ?>
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

    <?php if ($editRow && (int)($editRow['appointment_id'] ?? 0) > 0): ?>
      <?php $appt = appointments_get((int)$editRow['appointment_id']);
      $patSummary = $appt ? patients_get((int)$appt['patient_id']) : null;
      $docSummary = $appt ? doctors_get((int)$appt['doctor_id']) : null; ?>
      <?php if ($appt): ?>
        <div class="mt-4 bg-white border rounded p-4 text-sm">
          <div class="font-semibold mb-2">Appointment Summary</div>
          <div class="grid sm:grid-cols-2 gap-2">
            <div><span class="text-slate-500">When:</span> <?= htmlspecialchars((string)($appt['appointment_date'] ?? '')) ?></div>
            <?php if ($docSummary): ?>
              <div><span class="text-slate-500">Doctor:</span> <?= htmlspecialchars(($docSummary['first_name'] ?? '') . ' ' . ($docSummary['last_name'] ?? '')) ?></div>
            <?php endif; ?>
            <?php if ($patSummary): ?>
              <div class="sm:col-span-2"><span class="text-slate-500">Patient:</span> <?= htmlspecialchars(($patSummary['first_name'] ?? '') . ' ' . ($patSummary['last_name'] ?? '')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <form id="treatment-form" method="post" class="bg-white border rounded p-4 space-y-4">
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>" />
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php

        $lockedAppointmentId = 0;
        $defaultPatientId = 0;
        $defaultDoctorId = 0;
        if ($apptsParamId > 0) {
          foreach ($appts as $a) {
            if ((int)$a['id'] === $apptsParamId) {
              $lockedAppointmentId = (int)$a['id'];
              $defaultPatientId = (int)$a['patient_id'];
              $defaultDoctorId = (int)$a['doctor_id'];
              break;
            }
          }
        }
        if ($isDoctor && $doctorId && !$defaultDoctorId) {
          $defaultDoctorId = $doctorId;
        }
        ?>
        <label>Patient
          <select name="patient_id" id="patient_id" required class="border rounded px-3 py-2 w-full">
            <?php foreach ($patients as $p): $sel = ($editRow ? (int)$editRow['patient_id'] === (int)$p['id'] : ($defaultPatientId && (int)$p['id'] === $defaultPatientId)); ?>
              <option value="<?= (int)$p['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Doctor
          <select name="doctor_id" class="border rounded px-3 py-2 w-full" <?= $isDoctor ? 'disabled' : '' ?>>
            <option value="">-- None --</option>
            <?php foreach ($doctors as $d): $docSel = $editRow ? (int)($editRow['doctor_id'] ?? 0) === (int)$d['id'] : (($defaultDoctorId && (int)$d['id'] === $defaultDoctorId) || ($isDoctor && $doctorId === (int)$d['id'])); ?>
              <option value="<?= (int)$d['id'] ?>" <?= $docSel ? 'selected' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($isDoctor && $doctorId): ?><input type="hidden" name="doctor_id" value="<?= (int)$doctorId ?>" /><?php endif; ?>
        </label>
        <label>Appointment
          <select name="appointment_id" id="appointment_id" class="border rounded px-3 py-2 w-full" <?= $lockedAppointmentId ? 'disabled' : '' ?>>
            <option value="">-- None --</option>
            <?php foreach ($appts as $a):
              $keep = true;
              if (!$editRow) {
                if ($defaultPatientId && (int)$a['patient_id'] !== $defaultPatientId) $keep = false;
                if ($defaultDoctorId && (int)$a['doctor_id'] !== $defaultDoctorId) $keep = false;
              }
              if ($keep):
                $selected = $editRow ? ((int)($editRow['appointment_id'] ?? 0) === (int)$a['id']) : ($lockedAppointmentId && (int)$a['id'] === $lockedAppointmentId);
            ?>
                <option value="<?= (int)$a['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($a['appointment_date']) ?></option>
            <?php endif;
            endforeach; ?>
          </select>
          <?php if ($lockedAppointmentId): ?><input type="hidden" name="appointment_id" value="<?= (int)$lockedAppointmentId ?>" /><?php endif; ?>
        </label>
        <label class="block text-sm">Treatment Date<input class="w-full border rounded px-3 py-2" type="date" name="treatment_date" value="<?= htmlspecialchars($editRow['treatment_date'] ?? date('Y-m-d')) ?>" required /></label>
      </div>
      <label class="block text-sm">Diagnosis<textarea class="w-full border rounded px-3 py-2" name="diagnosis"><?= htmlspecialchars($editRow['diagnosis'] ?? '') ?></textarea></label>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?>
          <a class="text-slate-600 hover:underline" href="./manage-treatments.php">Cancel</a>
          <a class="inline-flex items-center px-3 py-2 rounded border border-emerald-600 text-emerald-700 hover:bg-emerald-50" href="manage-prescriptions.php?treatment_id=<?= (int)$editRow['id'] ?>">Add Prescription</a>
        <?php endif; ?>
      </div>
    </form>

    <?php

    if (!$editRow && $lockedAppointmentId) {
      $appt = appointments_get((int)$lockedAppointmentId);
      $patSummary = null;
      $docSummary = null;
      if ($appt) {
        $patSummary = patients_get((int)$appt['patient_id']);
        $docSummary = doctors_get((int)$appt['doctor_id']);
      }
    ?>
      <div class="mt-4 bg-white border rounded p-4 text-sm">
        <div class="font-semibold mb-2">Appointment Summary</div>
        <div class="grid sm:grid-cols-2 gap-2">
          <div><span class="text-slate-500">When:</span> <?= htmlspecialchars((string)($appt['appointment_date'] ?? '')) ?></div>
          <?php if ($docSummary): ?>
            <div><span class="text-slate-500">Doctor:</span> <?= htmlspecialchars(($docSummary['first_name'] ?? '') . ' ' . ($docSummary['last_name'] ?? '')) ?></div>
          <?php endif; ?>
          <?php if ($patSummary): ?>
            <div class="sm:col-span-2"><span class="text-slate-500">Patient:</span> <?= htmlspecialchars(($patSummary['first_name'] ?? '') . ' ' . ($patSummary['last_name'] ?? '')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php } ?>

    <?php

    if ($isDoctor && !$editRow) {
      $pid = 0;
      if ($lockedAppointmentId && !empty($appt)) {
        $pid = (int)($appt['patient_id'] ?? 0);
      } elseif (!empty($defaultPatientId)) {
        $pid = (int)$defaultPatientId;
      }
      if ($pid > 0) {
        $pat = patients_get($pid);
      }
      if (!empty($pat)) {
    ?>
        <div class="mt-4 bg-white border rounded p-4 text-sm">
          <div class="font-semibold mb-2">Patient Summary</div>
          <div class="grid sm:grid-cols-2 gap-2">
            <div><span class="text-slate-500">Name:</span> <?= htmlspecialchars(($pat['first_name'] ?? '') . ' ' . ($pat['last_name'] ?? '')) ?></div>
            <div><span class="text-slate-500">Gender:</span> <?= htmlspecialchars(ucfirst((string)($pat['gender'] ?? ''))) ?></div>
            <div><span class="text-slate-500">Birthdate:</span> <?= htmlspecialchars((string)($pat['birthdate'] ?? '')) ?></div>
            <div><span class="text-slate-500">Phone:</span> <?= htmlspecialchars((string)($pat['phone'] ?? '')) ?></div>
            <div class="sm:col-span-2"><span class="text-slate-500">Address:</span> <?= htmlspecialchars((string)($pat['address'] ?? '')) ?></div>
          </div>
        </div>
    <?php
      }
    }
    ?>

    <h2 class="text-lg font-semibold text-slate-800 mt-8">Treatments</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">Date</th>
            <th class="text-left px-4 py-2">Patient</th>
            <?php if (!$isDoctor): ?><th class="text-left px-4 py-2">Doctor</th><?php endif; ?>
            <th class="text-left px-4 py-2">Diagnosis</th>
            <th class="text-right px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= htmlspecialchars($r['treatment_date']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['pf'] . ' ' . $r['pl']) ?></td>
              <?php if (!$isDoctor): ?>
                <td class="px-4 py-2"><?= htmlspecialchars(($r['df'] ?? '') . ' ' . ($r['dl'] ?? '')) ?></td>
              <?php endif; ?>
              <td class="px-4 py-2"><?= htmlspecialchars($r['diagnosis'] ?? '') ?></td>
              <td class="px-4 py-2 text-right space-x-2">
                <?php $own = $isDoctor && $doctorId && (int)($r['doctor_id'] ?? 0) === (int)$doctorId; ?>
                <a class="inline-flex items-center px-3 py-1.5 rounded border border-blue-600 text-blue-700 hover:bg-blue-50" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                <?php if (!$isDoctor || $own): ?>
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-emerald-600 text-emerald-700 hover:bg-emerald-50" href="manage-prescriptions.php?treatment_id=<?= (int)$r['id'] ?>">Add Prescription</a>
                <?php endif; ?>
                <?php if (!$isDoctor): ?>
                  <a class="inline-flex items-center px-3 py-1.5 rounded border border-red-600 text-red-600 hover:bg-red-50" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this treatment?')">Delete</a>
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
    <script>
      (function() {
        const patientSelect = document.getElementById('patient_id');
        const apptSelect = document.getElementById('appointment_id');
        if (!patientSelect || !apptSelect) return;

        if (apptSelect.hasAttribute('disabled')) return;

        const baseUrl = 'api/appointments_options.php';
        async function refreshAppointments() {
          const pid = patientSelect.value || '';
          if (!pid) {
            apptSelect.innerHTML = '<option value="">-- None --</option>';
            return;
          }
          apptSelect.innerHTML = '<option>Loading…</option>';
          try {
            const res = await fetch(baseUrl + '?patient_id=' + encodeURIComponent(pid), {
              credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('Network');
            const data = await res.json();
            const opts = ['<option value="">-- None --</option>'];
            for (const a of data) {
              const label = a.appointment_date || ('Appointment #' + a.id);
              opts.push(`<option value="${a.id}">${label}</option>`);
            }
            apptSelect.innerHTML = opts.join('');
          } catch (e) {
            apptSelect.innerHTML = '<option value="">-- None --</option>';
          }
        }
        patientSelect.addEventListener('change', refreshAppointments);
      })();
    </script>
  </main>
</body>

</html>