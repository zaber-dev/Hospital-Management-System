<?php
require_once __DIR__ . '/../database.php';

if (isset($_SESSION['user']) && $_SESSION['user'] !== null) {
	redirect('../dashboard/index.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	verify_csrf_or_die();

	$username = trim($_POST['username'] ?? '');
	$email    = trim($_POST['email'] ?? '');
	$password = (string)($_POST['password'] ?? '');
	$confirm  = (string)($_POST['confirm_password'] ?? '');

	if ($username === '' || $email === '' || $password === '' || $confirm === '') {
		flash_add('error', 'All fields are required.');
		redirect('signup.php?error=missing');
	}
	if ($password !== $confirm) {
		flash_add('error', 'Passwords do not match.');
		redirect('signup.php?error=nomatch');
	}

	$existing = find_user_by_username_or_email($username) ?: find_user_by_username_or_email($email);
	if ($existing) {
		flash_add('error', 'A user with that username or email already exists.');
		redirect('signup.php?error=exists');
	}

	try {
		create_user($username, $email, $password, ROLE_STAFF, null);
	} catch (Throwable $e) {
		flash_add('error', 'Failed to register user.');
		redirect('signup.php?error=failed');
	}

	flash_add('success', 'Registration successful. Please sign in.');
	redirect('login.php?registered=1');
} else {
	include_once __DIR__ . '/../config.php'; ?>
	<html>

	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Sign up</title>
		<script src="https://cdn.tailwindcss.com"></script>
	</head>

	<body class="bg-gray-50 text-slate-800">
		<main class="max-w-md mx-auto p-6">
			<h1 class="text-2xl font-semibold mb-4">Create Account</h1>
			<?php render_flash(); ?>
			<form method="post" action="signup.php" class="space-y-4 bg-white p-5 rounded border shadow-sm">
				<div>
					<label class="block text-sm font-medium mb-1">Username</label>
					<input name="username" required class="w-full rounded border px-3 py-2" />
				</div>
				<div>
					<label class="block text-sm font-medium mb-1">Email</label>
					<input name="email" type="email" required class="w-full rounded border px-3 py-2" />
				</div>
				<div>
					<label class="block text-sm font-medium mb-1">Password</label>
					<input name="password" type="password" required class="w-full rounded border px-3 py-2" />
				</div>
				<div>
					<label class="block text-sm font-medium mb-1">Confirm Password</label>
					<input name="confirm_password" type="password" required class="w-full rounded border px-3 py-2" />
				</div>
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
				<button type="submit" class="w-full rounded bg-blue-600 text-white py-2 hover:bg-blue-700">Create</button>
			</form>
			<p class="mt-4 text-sm"><a href="login.php" class="text-blue-600 hover:underline">Have an account? Sign in</a></p>
		</main>
	</body>

	</html>
<?php
	exit;
}
