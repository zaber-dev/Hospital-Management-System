<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF]);
$isAdmin = has_role(ROLE_ADMIN);
$isStaff = has_role(ROLE_STAFF);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_room') {
  verify_csrf_or_die();
  rooms_create($_POST);
  flash_add('success', 'Room added.');
  redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_room') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    rooms_update($id, $_POST);
    flash_add('success', 'Room updated.');
  }
  redirect('./manage-rooms.php');
}

if (($_GET['delete_room'] ?? '') !== '') {
  rooms_delete((int)$_GET['delete_room']);
  flash_add('success', 'Room deleted.');
  redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admit') {
  verify_csrf_or_die();
  admissions_create($_POST);
  flash_add('success', 'Patient admitted.');
  redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admission') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    admissions_update($id, $_POST);
    flash_add('success', 'Admission updated.');
  }
  redirect('./manage-rooms.php');
}

if (($_GET['discharge'] ?? '') !== '') {
  $id = (int) $_GET['discharge'];
  admissions_discharge($id);
  flash_add('success', 'Patient discharged.');
  redirect('./manage-rooms.php');
}

if (($_GET['delete_admission'] ?? '') !== '') {
  admissions_delete((int)$_GET['delete_admission']);
  flash_add('success', 'Admission deleted.');
  redirect('./manage-rooms.php');
}
$rp = get_pagination_params(10);
$roomsData = rooms_list($rp['q'], $rp['limit'], $rp['offset']);
$rooms = $roomsData['rows'];
$roomsPages = (int) ceil(max(1, (int)$roomsData['total']) / $rp['per']);

$patients = patients_options();
$prefillPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$availableRooms = rooms_options_available();

$roomEditId = isset($_GET['edit_room']) ? (int)$_GET['edit_room'] : 0;
$roomEditRow = $roomEditId ? rooms_get($roomEditId) : null;

$ap = get_pagination_params(10);
$admData = admissions_list($ap['q'], $ap['limit'], $ap['offset']);
$admissions = $admData['rows'];
$admPages = (int) ceil(max(1, (int)$admData['total']) / $ap['per']);
$admEditId = isset($_GET['edit_admission']) ? (int)$_GET['edit_admission'] : 0;
$admEditRow = $admEditId ? admissions_get($admEditId) : null;
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Rooms & Admissions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="md:ml-64 max-w-6xl mx-auto p-6">
    <div class="mb-6"><a class="text-blue-600 hover:underline" href="index.php">← Back</a></div>

    <div class="flex items-center justify-between gap-4">
      <h2 class="text-xl font-semibold"><?= $roomEditRow ? 'Edit Room' : 'Add Room' ?></h2>
      <a href="./manage-rooms.php" class="inline-flex items-center px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">New Room</a>
    </div>
    <?php render_flash(); ?>
    <form method="get" class="mb-4 flex gap-2">
      <input class="border rounded px-3 py-2" name="q" placeholder="Search rooms..." value="<?= htmlspecialchars($rp['q']) ?>" />
      <button class="rounded bg-blue-600 text-white px-3 py-2">Search</button>
    </form>
    <form method="post" class="bg-white border rounded p-4 space-y-4">
      <input type="hidden" name="action" value="<?= $roomEditRow ? 'update_room' : 'create_room' ?>" />
      <?php if ($roomEditRow): ?><input type="hidden" name="id" value="<?= (int)$roomEditRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <label class="block text-sm">Room #<input class="w-full border rounded px-3 py-2" name="room_number" value="<?= htmlspecialchars($roomEditRow['room_number'] ?? '') ?>" required /></label>
        <label class="block text-sm">Type
          <select name="type" class="w-full border rounded px-3 py-2">
            <option value="">-- None --</option>
            <?php foreach (['General', 'Private', 'ICU', 'Emergency', 'Maternity'] as $rt): ?>
              <option value="<?= $rt ?>" <?= ($roomEditRow['type'] ?? '') === $rt ? 'selected' : '' ?>><?= $rt ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block text-sm">Status
          <select name="status" class="w-full border rounded px-3 py-2">
            <?php foreach (['available', 'occupied', 'maintenance'] as $rs): ?>
              <option value="<?= $rs ?>" <?= ($roomEditRow['status'] ?? 'available') === $rs ? 'selected' : '' ?>><?= ucfirst($rs) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $roomEditRow ? 'Update Room' : 'Add Room' ?></button>
        <?php if ($roomEditRow): ?><a class="text-slate-600 hover:underline" href="./manage-rooms.php">Cancel</a><?php endif; ?>
      </div>
    </form>

    <h2 class="text-xl font-semibold mt-8">Rooms</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">#</th>
            <th class="text-left px-4 py-2">Type</th>
            <th class="text-left px-4 py-2">Status</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($rooms as $r): ?>
            <tr>
              <td class="px-4 py-2"><?= htmlspecialchars($r['room_number']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['type'] ?? '') ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['status']) ?></td>
              <td class="px-4 py-2 text-right space-x-2">
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50" href="?edit_room=<?= (int)$r['id'] ?>">Edit</a>
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50" href="?delete_room=<?= (int)$r['id'] ?>" onclick="return confirm('Delete room?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rooms): ?><tr>
              <td class="px-4 py-3" colspan="4">No rooms</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($roomsPages > 1): ?>
      <nav aria-label="pagination" class="mt-4">
        <ul class="flex gap-2">
          <?php for ($i = 1; $i <= $roomsPages; $i++): ?>
            <li><a class="px-3 py-1 rounded border <?= $i === $rp['page'] ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-100' ?>" href="?page=<?= $i ?>&per=<?= $rp['per'] ?>&q=<?= urlencode($rp['q']) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <h2 class="text-xl font-semibold mt-10"><?= $admEditRow ? 'Edit Admission' : 'Admit Patient' ?></h2>
    <form method="post" class="bg-white border rounded p-4 space-y-4 mt-8">
      <input type="hidden" name="action" value="<?= $admEditRow ? 'update_admission' : 'admit' ?>" />
      <?php if ($admEditRow): ?><input type="hidden" name="id" value="<?= (int)$admEditRow['id'] ?>" /><?php endif; ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <label>Patient
          <select name="patient_id" required class="border rounded px-3 py-2 w-full">
            <?php foreach ($patients as $p): $sel = $admEditRow ? ((int)$admEditRow['patient_id'] === (int)$p['id']) : ($prefillPatientId && (int)$p['id'] === $prefillPatientId); ?>
              <option value="<?= (int)$p['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Room
          <select name="room_id" required class="border rounded px-3 py-2 w-full">
            <?php if ($admEditRow): ?>
              <?php
              $currentRoomId = (int)$admEditRow['room_id'];
              $currentRoom = rooms_get($currentRoomId);
              if ($currentRoom): ?>
                <option value="<?= (int)$currentRoom['id'] ?>" selected><?= htmlspecialchars($currentRoom['room_number']) ?> (current)</option>
              <?php endif; ?>
            <?php endif; ?>
            <?php foreach ($availableRooms as $r): ?>
              <?php if ($admEditRow && (int)$r['id'] === (int)$admEditRow['room_id']) continue; ?>
              <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['room_number']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php
        $admDtVal = '';
        if ($admEditRow) {
          $dt = $admEditRow['admitted_on'];
          if ($dt && strpos($dt, ' ') !== false) {
            $admDtVal = str_replace(' ', 'T', substr($dt, 0, 16));
          } else {
            $admDtVal = (string)$dt;
          }
        }
        ?>
        <label class="block text-sm">Admitted On<input class="w-full border rounded px-3 py-2" type="datetime-local" name="admitted_on" value="<?= htmlspecialchars($admDtVal) ?>" required /></label>
      </div>
      <label class="block text-sm">Notes<textarea class="w-full border rounded px-3 py-2" name="notes"><?= htmlspecialchars($admEditRow['notes'] ?? '') ?></textarea></label>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="flex items-center gap-2">
        <button class="rounded bg-blue-600 text-white px-3 py-2"><?= $admEditRow ? 'Update Admission' : 'Admit' ?></button>
        <?php if ($admEditRow): ?><a class="text-slate-600 hover:underline" href="./manage-rooms.php">Cancel</a><?php endif; ?>
      </div>
    </form>

    <h2>Admissions</h2>
    <div class="overflow-x-auto rounded border bg-white mt-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left px-4 py-2">Admitted</th>
            <th class="text-left px-4 py-2">Patient</th>
            <th class="text-left px-4 py-2">Room</th>
            <th class="text-left px-4 py-2">Discharged</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($admissions as $a): ?>
            <tr>
              <td class="px-4 py-2"><?= htmlspecialchars($a['admitted_on']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($a['pf'] . ' ' . $a['pl']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($a['room_number']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($a['discharged_on'] ?? '') ?></td>
              <td class="px-4 py-2 text-right space-x-4">
                <a class="text-blue-700 hover:underline" href="?edit_admission=<?= (int)$a['id'] ?>">Edit</a>
                <?php if ($a['discharged_on'] === null): ?>
                  <a class="inline-flex items-center px-2.5 py-1 rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50" href="?discharge=<?= (int)$a['id'] ?>">Discharge</a>
                <?php endif; ?>
                <a class="inline-flex items-center px-2.5 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50" href="?delete_admission=<?= (int)$a['id'] ?>" onclick="return confirm('Delete admission?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$admissions): ?><tr>
              <td class="px-4 py-3" colspan="5">No admissions</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($admPages > 1): ?>
      <nav aria-label="pagination" class="mt-4">
        <ul class="flex gap-2">
          <?php for ($i = 1; $i <= $admPages; $i++): ?>
            <li><a class="px-3 py-1 rounded border <?= $i === $ap['page'] ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-100' ?>" href="?page=<?= $i ?>&per=<?= $ap['per'] ?>&q=<?= urlencode($ap['q']) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </main>
</body>

</html>