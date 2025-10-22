<?php
require_once __DIR__ . '/database.php';
$user = current_user();
$isLoggedIn = is_logged_in();
$isAdmin = has_role(ROLE_ADMIN);
$isStaff = has_role(ROLE_STAFF);
$isDoctor = has_role(ROLE_DOCTOR);
$stats = $isLoggedIn ? simple_stats() : [];
?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Hospital Management System</title>
	<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-slate-800">
	<header class="bg-white border-b">
		<div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
			<a href="./" class="flex items-center gap-2 text-slate-800">
				<span class="text-2xl">🏥</span>
				<span class="font-semibold text-lg">Hospital Management</span>
			</a>
			<nav class="flex items-center gap-4 text-sm">
				<?php if ($isLoggedIn): ?>
					<span class="hidden sm:inline text-slate-600">Hi, <?= htmlspecialchars($user['username'] ?? ($user['email'] ?? 'User')) ?></span>
					<a class="inline-flex items-center rounded border border-slate-200 px-3 py-1.5 hover:bg-slate-50" href="dashboard/index.php">Dashboard</a>
					<a class="inline-flex items-center rounded bg-slate-800 text-white px-3 py-1.5 hover:bg-slate-900" href="auth/logout.php">Logout</a>
				<?php else: ?>
					<a class="text-blue-700 hover:underline" href="auth/login.php">Login</a>
					<a class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-1.5 hover:bg-blue-700" href="auth/signup.php">Sign up</a>
				<?php endif; ?>
			</nav>
		</div>
	</header>
	<main class="max-w-6xl mx-auto p-6">
		<?php render_flash(); ?>

		<?php if (!$isLoggedIn): ?>
			<section class="grid lg:grid-cols-2 gap-8 items-center">
				<div>
					<h1 class="text-3xl font-semibold leading-tight">Streamlined Hospital Operations</h1>
					<p class="mt-3 text-slate-600">Manage patients, appointments, treatments, prescriptions, and room admissions - all in one secure dashboard.</p>
					<div class="mt-6 flex flex-wrap gap-3">
						<a class="inline-flex items-center rounded bg-blue-600 text-white px-4 py-2 hover:bg-blue-700" href="auth/login.php">Sign in</a>
						<a class="inline-flex items-center rounded border border-blue-200 text-blue-700 px-4 py-2 hover:bg-blue-50" href="auth/signup.php">Create account</a>
						<a class="inline-flex items-center rounded border border-slate-200 px-4 py-2 hover:bg-slate-50" href="dashboard/index.php">View dashboard</a>
					</div>
					<ul class="mt-8 space-y-2 text-sm text-slate-700">
						<li>• Appointments and patient records</li>
						<li>• Doctor notes, treatments, and prescriptions</li>
						<li>• Rooms inventory and patient admissions</li>
						<li>• Role-based access for Admin, Staff, and Doctors</li>
					</ul>
				</div>
				<div class="bg-white border rounded-lg p-5 shadow-sm">
					<h2 class="font-medium mb-3">Modules</h2>
					<div class="grid sm:grid-cols-2 gap-3 text-sm">
						<div class="rounded border p-3">Patients</div>
						<div class="rounded border p-3">Appointments</div>
						<div class="rounded border p-3">Treatments</div>
						<div class="rounded border p-3">Prescriptions</div>
						<div class="rounded border p-3">Rooms & Admissions</div>
						<div class="rounded border p-3">Doctors & Departments</div>
					</div>
				</div>
			</section>
		<?php else: ?>
			<section class="mb-8">
				<div class="flex items-center justify-between">
					<div>
						<h1 class="text-2xl font-semibold">Welcome back<?= $user && isset($user['username']) ? ', ' . htmlspecialchars($user['username']) : '' ?></h1>
						<p class="text-slate-600 text-sm">Quick overview of your hospital data</p>
					</div>
					<div class="flex items-center gap-3">
						<a class="inline-flex items-center rounded bg-blue-600 text-white px-3 py-2 hover:bg-blue-700" href="dashboard/index.php">Open Dashboard</a>
					</div>
				</div>
			</section>
			<section class="grid md:grid-cols-3 lg:grid-cols-6 gap-4">
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Patients</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['patients'] ?? 0) ?></div>
				</div>
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Doctors</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['doctors'] ?? 0) ?></div>
				</div>
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Appointments</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['appointments'] ?? 0) ?></div>
				</div>
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Treatments</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['treatments'] ?? 0) ?></div>
				</div>
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Rooms</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['rooms'] ?? 0) ?></div>
				</div>
				<div class="rounded border bg-white p-4">
					<div class="text-xs text-slate-500">Admissions</div>
					<div class="text-2xl font-semibold mt-1"><?= (int)($stats['admissions'] ?? 0) ?></div>
				</div>
			</section>
			<section class="mt-8">
				<h2 class="text-lg font-medium mb-3">Quick actions</h2>
				<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
					<?php if ($isAdmin || $isStaff || $isDoctor): ?>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-appointments.php">Manage Appointments</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-treatments.php">Manage Treatments</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-prescriptions.php">Manage Prescriptions</a>
					<?php endif; ?>
					<?php if ($isAdmin || $isStaff): ?>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-patients.php">Manage Patients</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-rooms.php">Rooms & Admissions</a>
					<?php endif; ?>
					<?php if ($isAdmin): ?>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-doctors.php">Manage Doctors</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-departments.php">Departments</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-medications.php">Medications</a>
						<a class="rounded border p-3 hover:bg-slate-50" href="dashboard/manage-users.php">Users</a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	</main>
</body>

</html>