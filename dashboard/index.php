<?php
require_once __DIR__ . '/../database.php';
require_login();
$user = current_user();
$isAdmin = ($user['role'] === ROLE_ADMIN);
$isStaff = ($user['role'] === ROLE_STAFF);
$isDoctor = ($user['role'] === ROLE_DOCTOR);
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : 0;
$doctorUnlinked = $isDoctor && !$doctorId;

$stats = simple_stats();
$todayApptCounts = dashboard_appointments_today_status_counts($isDoctor && $doctorId ? $doctorId : null);
$todayAppts = dashboard_appointments_today($isDoctor && $doctorId ? $doctorId : null, 10);
$upcoming7 = dashboard_appointments_upcoming_count($isDoctor && $doctorId ? $doctorId : null, 7);
$roomOcc = ($isAdmin || $isStaff) ? rooms_occupancy_counts() : null;
$openAdmissions = ($isAdmin || $isStaff) ? admissions_open_list(8) : [];
$docTreatments = $isDoctor && $doctorId ? doctor_recent_treatments($doctorId, 8) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard</title>
	<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
	<?php include __DIR__ . '/sidebar.php'; ?>
	<main class="md:ml-64 max-w-6xl mx-auto p-6">
		<?php if ($doctorUnlinked): ?>
			<div class="mb-4 border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2 rounded">Your account isn’t linked to a doctor profile. Please contact an administrator.</div>
		<?php endif; ?>

		<div class="flex items-center justify-between mb-4">
			<h2 class="text-xl font-semibold text-slate-800">Overview</h2>
			<div class="flex gap-2">
				<?php if ($isAdmin || $isStaff): ?>
					<a href="manage-appointments.php" class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700">New Appointment</a>
					<a href="manage-patients.php" class="inline-flex items-center rounded bg-emerald-600 text-white px-3 py-2 hover:bg-emerald-700">New Patient</a>
				<?php endif; ?>
				<?php if ($isAdmin): ?>
					<a href="manage-doctors.php" class="inline-flex items-center rounded bg-indigo-600 text-white px-3 py-2 hover:bg-indigo-700">New Doctor</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
			<div class="rounded border bg-white p-4"><div class="text-sm text-slate-500">Patients</div><div class="text-2xl font-semibold"><?= $stats['patients'] ?></div></div>
			<div class="rounded border bg-white p-4"><div class="text-sm text-slate-500">Doctors</div><div class="text-2xl font-semibold"><?= $stats['doctors'] ?></div></div>
			<div class="rounded border bg-white p-4"><div class="text-sm text-slate-500">Appointments (today)</div>
				<div class="mt-1 flex items-center gap-2 text-xs">
					<span class="inline-flex items-center px-2 py-0.5 rounded border border-slate-200">Scheduled: <span id="count-scheduled" class="ml-1 font-semibold"><?= (int)$todayApptCounts['scheduled'] ?></span></span>
					<span class="inline-flex items-center px-2 py-0.5 rounded border border-emerald-200 text-emerald-800 bg-emerald-50">Completed: <span id="count-completed" class="ml-1 font-semibold"><?= (int)$todayApptCounts['completed'] ?></span></span>
					<span class="inline-flex items-center px-2 py-0.5 rounded border border-red-200 text-red-800 bg-red-50">Cancelled: <span id="count-cancelled" class="ml-1 font-semibold"><?= (int)$todayApptCounts['cancelled'] ?></span></span>
				</div>
			</div>
			<div class="rounded border bg-white p-4"><div class="text-sm text-slate-500">Upcoming 7 days</div><div class="text-2xl font-semibold"><?= (int)$upcoming7 ?></div></div>
			<?php if ($roomOcc): ?>
				<div class="rounded border bg-white p-4 sm:col-span-2 lg:col-span-2"><div class="text-sm text-slate-500">Rooms</div>
					<div class="mt-1 flex items-center gap-2 text-xs">
						<span class="inline-flex items-center px-2 py-0.5 rounded border border-emerald-200 text-emerald-800 bg-emerald-50">Available: <span class="ml-1 font-semibold"><?= (int)$roomOcc['available'] ?></span></span>
						<span class="inline-flex items-center px-2 py-0.5 rounded border border-amber-200 text-amber-800 bg-amber-50">Occupied: <span class="ml-1 font-semibold"><?= (int)$roomOcc['occupied'] ?></span></span>
						<span class="inline-flex items-center px-2 py-0.5 rounded border border-slate-200">Maintenance: <span class="ml-1 font-semibold"><?= (int)$roomOcc['maintenance'] ?></span></span>
					</div>
				</div>
			<?php endif; ?>
		</div>

	<div class="grid lg:grid-cols-2 gap-6">
			<section class="rounded border bg-white">
				<div class="flex items-center justify-between px-4 py-3 border-b">
					<h3 class="font-semibold">Today’s Appointments</h3>
					<a class="text-sm text-blue-600 hover:underline" href="manage-appointments.php">View all</a>
				</div>
				<div class="overflow-x-auto">
					<table class="min-w-full text-sm">
						<thead class="bg-slate-50 text-slate-700"><tr>
							<th class="text-left px-4 py-2">Time</th>
							<th class="text-left px-4 py-2">Patient</th>
							<?php if (!$isDoctor): ?><th class="text-left px-4 py-2">Doctor</th><?php endif; ?>
							<th class="text-left px-4 py-2">Status</th>
							<th class="text-right px-4 py-2">Quick</th>
						</tr></thead>
						<tbody>
							<?php foreach ($todayAppts as $a): ?>
								<?php $st = $a['status']; $cls = $st==='completed' ? 'border-emerald-200 text-emerald-800 bg-emerald-50' : ($st==='cancelled' ? 'border-red-200 text-red-800 bg-red-50' : 'border-slate-200'); ?>
								<tr class="border-t" data-appointment-row="<?= (int)$a['id'] ?>">
									<td class="px-4 py-2"><?= htmlspecialchars(substr($a['appointment_date'], 11, 5)) ?></td>
									<td class="px-4 py-2"><?= htmlspecialchars($a['patient_first'].' '.$a['patient_last']) ?></td>
									<?php if (!$isDoctor): ?><td class="px-4 py-2"><?= htmlspecialchars($a['doctor_first'].' '.$a['doctor_last']) ?></td><?php endif; ?>
									<td class="px-4 py-2"><span data-role="status-badge" data-status="<?= htmlspecialchars($st) ?>" class="inline-flex items-center px-2 py-0.5 rounded border text-xs <?= $cls ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
									<td class="px-4 py-2 text-right space-x-1">
										<?php $aid=(int)$a['id']; ?>
										<?php if ($isDoctor): ?>
											<button class="px-2 py-1 text-xs rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50" data-action="status" data-id="<?= $aid ?>" data-status="completed">Mark Completed</button>
											<button class="px-2 py-1 text-xs rounded border border-red-300 text-red-700 hover:bg-red-50" data-action="status" data-id="<?= $aid ?>" data-status="cancelled">Cancel</button>
										<?php else: ?>
											<button class="px-2 py-1 text-xs rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50" data-action="status" data-id="<?= $aid ?>" data-status="completed">Mark Completed</button>
											<button class="px-2 py-1 text-xs rounded border border-amber-300 text-amber-700 hover:bg-amber-50" data-action="status" data-id="<?= $aid ?>" data-status="scheduled">Mark Scheduled</button>
											<button class="px-2 py-1 text-xs rounded border border-red-300 text-red-700 hover:bg-red-50" data-action="status" data-id="<?= $aid ?>" data-status="cancelled">Cancel</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (!$todayAppts): ?><tr class="border-t"><td class="px-4 py-3" colspan="<?= $isDoctor ? 4 : 5 ?>">No appointments today.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<?php if ($isAdmin || $isStaff): ?>
			<section class="rounded border bg-white">
				<div class="flex items-center justify-between px-4 py-3 border-b">
					<h3 class="font-semibold">Open Admissions</h3>
					<a class="text-sm text-blue-600 hover:underline" href="manage-rooms.php">Manage</a>
				</div>
				<div class="overflow-x-auto">
					<table class="min-w-full text-sm">
						<thead class="bg-slate-50 text-slate-700"><tr>
							<th class="text-left px-4 py-2">Admitted</th>
							<th class="text-left px-4 py-2">Patient</th>
							<th class="text-left px-4 py-2">Room</th>
						</tr></thead>
						<tbody>
							<?php foreach ($openAdmissions as $a): ?>
								<tr class="border-t">
									<td class="px-4 py-2"><?= htmlspecialchars($a['admitted_on']) ?></td>
									<td class="px-4 py-2"><?= htmlspecialchars($a['pf'].' '.$a['pl']) ?></td>
									<td class="px-4 py-2"><?= htmlspecialchars($a['room_number']) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (!$openAdmissions): ?><tr class="border-t"><td class="px-4 py-3" colspan="3">No open admissions.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
			<?php endif; ?>

			<?php if ($isDoctor && $doctorId): ?>
			<section class="rounded border bg-white lg:col-span-2">
				<div class="flex items-center justify-between px-4 py-3 border-b">
					<h3 class="font-semibold">Recent Treatments</h3>
					<a class="text-sm text-blue-600 hover:underline" href="manage-treatments.php">Manage</a>
				</div>
				<div class="overflow-x-auto">
					<table class="min-w-full text-sm">
						<thead class="bg-slate-50 text-slate-700"><tr>
							<th class="text-left px-4 py-2">Date</th>
							<th class="text-left px-4 py-2">Patient</th>
							<th class="text-left px-4 py-2">Diagnosis</th>
						</tr></thead>
						<tbody>
							<?php foreach ($docTreatments as $t): ?>
								<tr class="border-t">
									<td class="px-4 py-2"><?= htmlspecialchars($t['treatment_date']) ?></td>
									<td class="px-4 py-2"><?= htmlspecialchars($t['pf'].' '.$t['pl']) ?></td>
									<td class="px-4 py-2"><?= htmlspecialchars($t['diagnosis'] ?? '') ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (!$docTreatments): ?><tr class="border-t"><td class="px-4 py-3" colspan="3">No recent treatments.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
			<?php endif; ?>
		</div>
	<input type="hidden" id="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>" />
	<div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
		<div id="toast-content" class="rounded border px-3 py-2 text-sm shadow bg-white"></div>
	</div>
	<script>
	(function(){
		const token = document.getElementById('csrf_token')?.value || '';
		const toastEl = document.getElementById('toast');
		const toastContent = document.getElementById('toast-content');
		function showToast(msg, type){
			if (!toastEl || !toastContent) return;
			toastContent.textContent = msg;
			toastContent.className = 'rounded border px-3 py-2 text-sm shadow ' + (type==='error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800');
			toastEl.classList.remove('hidden');
			setTimeout(()=> toastEl.classList.add('hidden'), 1800);
		}

		function updateCounts(oldStatus, newStatus){
			const ids = { scheduled: 'count-scheduled', completed: 'count-completed', cancelled: 'count-cancelled' };
			if (ids[oldStatus]){
				const el = document.getElementById(ids[oldStatus]);
				if (el) { const v = parseInt(el.textContent||'0',10); el.textContent = Math.max(0, v-1); }
			}
			if (ids[newStatus]){
				const el = document.getElementById(ids[newStatus]);
				if (el) { const v = parseInt(el.textContent||'0',10); el.textContent = v+1; }
			}
		}

		function applyStatusBadge(badge, status){
			if (!badge) return;
			badge.dataset.status = status;
			badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
			badge.className = 'inline-flex items-center px-2 py-0.5 rounded border text-xs ' + (
				status==='completed' ? 'border-emerald-200 text-emerald-800 bg-emerald-50' :
				status==='cancelled' ? 'border-red-200 text-red-800 bg-red-50' :
				'border-slate-200'
			);
		}

		document.addEventListener('click', async (e) => {
			const t = e.target;
			if (!(t instanceof HTMLElement)) return;
			if (t.dataset?.action !== 'status') return;
			e.preventDefault();
			const id = t.dataset.id;
			const status = t.dataset.status;
			if (!id || !status) return;
			t.disabled = true;
			const row = t.closest('tr');
			const badge = row ? row.querySelector('[data-role="status-badge"]') : null;
			const oldStatus = badge ? (badge.getAttribute('data-status')||'') : '';
			try {
				const res = await fetch('api/appointment_status.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ id, status, csrf_token: token }).toString(),
					credentials: 'same-origin'
				});
				if (!res.ok) throw new Error('network');
				const js = await res.json();
				if (js && js.ok) {
					applyStatusBadge(badge, status);
					if (oldStatus && oldStatus !== status) updateCounts(oldStatus, status);
					if (row) {
						row.classList.add('bg-amber-50');
						setTimeout(()=>row.classList.remove('bg-amber-50'), 600);
					}
					showToast('Status updated to ' + status + '.', 'success');
				} else {
					showToast('Could not update status.', 'error');
				}
			} catch(err) {
				showToast('Could not update status.', 'error');
			} finally {
				t.disabled = false;
			}
		});
	})();
	</script>
	</main>
</body>
</html>
