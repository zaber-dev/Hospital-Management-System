<?php
$user = current_user();
$role = $user['role'] ?? 'staff';
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$linkBase = 'block px-3 py-2 rounded hover:bg-slate-100 text-slate-700';
$activeCls = 'bg-slate-100 text-slate-900 font-medium';
?>
<aside x-data="{open:false}" class="relative">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] {
            display: none !important
        }
    </style>

    <div class="md:hidden fixed top-0 inset-x-0 z-20 bg-white/80 backdrop-blur border-b">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <button @click="open = !open" aria-label="Toggle menu" class="p-2 rounded border hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path fill-rule="evenodd" d="M3 6.75A.75.75 0 013.75 6h16.5a.75.75 0 010 1.5H3.75A.75.75 0 013 6.75zm0 5.25a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H3.75a.75.75 0 01-.75-.75zm.75 4.5a.75.75 0 000 1.5h16.5a.75.75 0 000-1.5H3.75z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="text-sm text-slate-700">Hello, <?= htmlspecialchars($user['username'] ?? '') ?> (<?= htmlspecialchars($role) ?>)</div>
            <a class="text-sm text-blue-600 hover:underline" href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="md:hidden h-14" aria-hidden="true"></div>

    <div x-show="open" x-cloak class="fixed inset-0 bg-black/30 md:hidden z-20" @click="open=false"></div>

    <div x-cloak class="md:w-64 md:fixed md:inset-y-0 md:left-0 md:border-r bg-white" :class="open ? 'block fixed top-14 inset-y-0 left-0 w-64 border-r z-30 md:top-0' : 'hidden md:block'">
        <div class="h-full flex flex-col">
            <div class="px-4 py-4 border-b">
                <a href="index.php" class="font-semibold <?= $current === 'index.php' ? $activeCls : '' ?>">Hospital Management</a>
                <div class="text-xs text-slate-500 mt-1">Role: <?= htmlspecialchars($role) ?></div>
            </div>
            <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-1 text-sm">
                <a class="<?= $linkBase ?> <?= $current === 'index.php' ? $activeCls : '' ?>" href="index.php">Dashboard</a>

                <?php if ($role === ROLE_ADMIN): ?>
                    <div class="mt-3 text-xs uppercase tracking-wide text-slate-500 px-3">Management</div>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-patients.php' ? $activeCls : '' ?>" href="manage-patients.php">Patients</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-doctors.php' ? $activeCls : '' ?>" href="manage-doctors.php">Doctors</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-appointments.php' ? $activeCls : '' ?>" href="manage-appointments.php">Appointments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-treatments.php' ? $activeCls : '' ?>" href="manage-treatments.php">Treatments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-prescriptions.php' ? $activeCls : '' ?>" href="manage-prescriptions.php">Prescriptions</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-medications.php' ? $activeCls : '' ?>" href="manage-medications.php">Medications</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-departments.php' ? $activeCls : '' ?>" href="manage-departments.php">Departments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-rooms.php' ? $activeCls : '' ?>" href="manage-rooms.php">Rooms & Admissions</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-users.php' ? $activeCls : '' ?>" href="manage-users.php">Users</a>
                <?php elseif ($role === ROLE_DOCTOR): ?>
                    <div class="mt-3 text-xs uppercase tracking-wide text-slate-500 px-3">Doctor</div>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-appointments.php' ? $activeCls : '' ?>" href="manage-appointments.php">Appointments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-treatments.php' ? $activeCls : '' ?>" href="manage-treatments.php">Treatments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-prescriptions.php' ? $activeCls : '' ?>" href="manage-prescriptions.php">Prescriptions</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-patients.php' ? $activeCls : '' ?>" href="manage-patients.php">Patients</a>
                <?php else: ?>
                    <div class="mt-3 text-xs uppercase tracking-wide text-slate-500 px-3">Staff</div>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-appointments.php' ? $activeCls : '' ?>" href="manage-appointments.php">Appointments</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-patients.php' ? $activeCls : '' ?>" href="manage-patients.php">Patients</a>
                    <a class="<?= $linkBase ?> <?= $current === 'manage-rooms.php' ? $activeCls : '' ?>" href="manage-rooms.php">Rooms & Admissions</a>
                <?php endif; ?>

                <div class="mt-6 px-3">
                    <a class="inline-flex items-center rounded bg-slate-800 text-white px-3 py-2 text-xs hover:bg-slate-900" href="../auth/logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </div>
</aside>