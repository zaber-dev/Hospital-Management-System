<?php
define('DB_HOST', getenv('HMS_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('HMS_DB_NAME') ?: 'hospital_management');
define('DB_USER', getenv('HMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('HMS_DB_PASS') ?: '');

define('BASE_PATH', __DIR__);
define('BASE_URL', getenv('HMS_BASE_URL') ?: '/');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

const ROLE_ADMIN = 'admin';
const ROLE_STAFF = 'staff';
const ROLE_DOCTOR = 'doctor';

function is_logged_in(): bool
{
	return isset($_SESSION['user']);
}

function current_user()
{
	return $_SESSION['user'] ?? null;
}

function require_login(): void
{
	if (!is_logged_in()) {
		redirect('../auth/login.php?error=login_required');
	}
}

function require_role(string $role): void
{
	require_login();
	$user = current_user();
	if (!$user || ($user['role'] ?? null) !== $role) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

function has_role(string $role): bool
{
	$user = current_user();
	return $user && (($user['role'] ?? null) === $role);
}

function require_any_role(array $roles): void
{
	require_login();
	$userRole = current_user()['role'] ?? null;
	if (!in_array($userRole, $roles, true)) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

function redirect(string $path): void
{
	$isAbsoluteUrl = (stripos($path, 'http://') === 0) || (stripos($path, 'https://') === 0);
	if ($isAbsoluteUrl || (substr($path, 0, 1) === '/')) {
		header('Location: ' . $path);
		exit;
	}
	if (strpos($path, './') === 0 || strpos($path, '../') === 0) {
		header('Location: ' . $path);
		exit;
	}
	$base = defined('BASE_URL') ? (string)BASE_URL : '';
	if ($base && $base !== '/') {
		$base = rtrim($base, '/') . '/';
		$url = $base . ltrim($path, '/');
	} else {
		$dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\') . '/';
		$url = $dir . ltrim($path, '/');
	}
	header('Location: ' . $url);
	exit;
}

function csrf_token(): string
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function verify_csrf_or_die(): void
{
	$token = $_POST['csrf_token'] ?? '';
	if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
		http_response_code(400);
		echo 'Invalid CSRF token';
		exit;
	}
}

function flash_add(string $type, string $message): void
{
	$_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_consume(): array
{
	$msgs = $_SESSION['flash'] ?? [];
	unset($_SESSION['flash']);
	return $msgs;
}

function render_flash(): void
{
	$msgs = flash_consume();
	if (!$msgs) return;
	echo '<div class="space-y-2 my-3">';
	foreach ($msgs as $m) {
		$isErr = ($m['type'] === 'error');
		$cls = $isErr
			? 'border border-red-200 bg-red-50 text-red-800'
			: 'border border-emerald-200 bg-emerald-50 text-emerald-800';
		echo '<p class="' . $cls . ' px-3 py-2 rounded">' . htmlspecialchars($m['message']) . '</p>';
	}
	echo '</div>';
}

function get_pagination_params(int $defaultPerPage = 10): array
{
	$page = max(1, (int)($_GET['page'] ?? 1));
	$per = (int)($_GET['per'] ?? $defaultPerPage);
	if ($per <= 0) $per = $defaultPerPage;
	if ($per > 100) $per = 100;
	$offset = ($page - 1) * $per;
	$q = trim((string)($_GET['q'] ?? ''));
	return ['page' => $page, 'per' => $per, 'offset' => $offset, 'limit' => $per, 'q' => $q];
}
