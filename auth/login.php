<?php
require_once __DIR__ . '/../database.php';

if (isset($_SESSION['user']) && $_SESSION['user'] !== null) {
	redirect('../dashboard/index.php');
	exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['csrf_token'])) {
		verify_csrf_or_die();
	}

	$identifier = trim($_POST['identifier'] ?? '');
	$password   = (string)($_POST['password'] ?? '');

	if ($identifier === '' || $password === '') {
		flash_add('error', 'Please provide username/email and password.');
		redirect('login.php?error=missing');
		exit;
	}

	$user = find_user_by_username_or_email($identifier);
	if (!$user || !password_verify($password, $user['password_hash'])) {
		flash_add('error', 'Invalid credentials.');
		redirect('login.php?error=invalid');
		exit;
	}

	$_SESSION['user'] = [
		'id' => (int)$user['id'],
		'username' => $user['username'],
		'email' => $user['email'],
		'role' => $user['role'],
		'linked_doctor_id' => $user['linked_doctor_id'] !== null ? (int)$user['linked_doctor_id'] : null,
	];

	redirect('../dashboard/index.php');
	exit;
} else {
	include_once __DIR__ . '/../config.php'; ?>
	<html>

	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Login</title>
		<script src="https://cdn.tailwindcss.com"></script>
	</head>

	<body class="bg-gray-50 text-slate-800">
		<main class="max-w-md mx-auto p-6">
			<h1 class="text-2xl font-semibold mb-4">Login</h1>
			<?php render_flash(); ?>
			<form method="post" action="login.php" class="space-y-4 bg-white p-5 rounded border shadow-sm">
				<div>
					<label class="block text-sm font-medium mb-1">Username or Email</label>
					<input name="identifier" class="w-full rounded border px-3 py-2" placeholder="username or email" required />
				</div>
				<div>
					<label class="block text-sm font-medium mb-1">Password</label>
					<input name="password" type="password" class="w-full rounded border px-3 py-2" required />
				</div>
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
				<button type="submit" class="w-full rounded bg-blue-600 text-white py-2 hover:bg-blue-700">Sign in</button>
			</form>
			<p class="mt-4 text-sm"><a href="../" class="text-blue-600 hover:underline">Home</a> · <a class="text-blue-600 hover:underline" href="signup.php">Create account</a></p>
		</main>
	</body>

	</html>
<?php
	exit;
}
