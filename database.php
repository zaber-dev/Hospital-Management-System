<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}
	$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
	return $pdo;
}

function find_user_by_username_or_email(string $identifier): ?array
{
	$stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
	$stmt->execute([$identifier, $identifier]);
	$user = $stmt->fetch();
	return $user ?: null;
}

function create_user(string $username, string $email, string $password, string $role = ROLE_STAFF, ?int $linkedDoctorId = null): int
{
	$hash = password_hash($password, PASSWORD_BCRYPT);
	$stmt = db()->prepare('INSERT INTO users (username,email,password_hash,role,linked_doctor_id) VALUES (?,?,?,?,?)');
	$stmt->execute([$username, $email, $hash, $role, $linkedDoctorId]);
	return (int) db()->lastInsertId();
}

function simple_stats(): array
{
	$pdo = db();
	$counts = [];
	foreach (
		[
			'patients',
			'doctors',
			'appointments',
			'treatments',
			'rooms',
			'admissions'
		] as $table
	) {
		$counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
	}
	return $counts;
}

// Dashboard helpers
function dashboard_appointments_today(?int $doctorId = null, int $limit = 10): array
{
	$sql = 'SELECT a.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last
			FROM appointments a
			JOIN patients p ON p.id = a.patient_id
			JOIN doctors d ON d.id = a.doctor_id
			WHERE DATE(a.appointment_date) = CURDATE()';
	$params = [];
	if ($doctorId !== null) {
		$sql .= ' AND a.doctor_id = ?';
		$params[] = $doctorId;
	}
	$limit = (int)$limit;
	$sql .= ' ORDER BY a.appointment_date ASC LIMIT ' . $limit;
	$stmt = db()->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll();
}

function dashboard_appointments_today_status_counts(?int $doctorId = null): array
{
	$sql = 'SELECT a.status, COUNT(*) AS c
			FROM appointments a
			WHERE DATE(a.appointment_date) = CURDATE()';
	$params = [];
	if ($doctorId !== null) {
		$sql .= ' AND a.doctor_id = ?';
		$params[] = $doctorId;
	}
	$sql .= ' GROUP BY a.status';
	$stmt = db()->prepare($sql);
	$stmt->execute($params);
	$out = ['scheduled' => 0, 'completed' => 0, 'cancelled' => 0];
	foreach ($stmt->fetchAll() as $row) {
		$out[$row['status']] = (int)$row['c'];
	}
	return $out;
}

function dashboard_appointments_upcoming_count(?int $doctorId = null, int $days = 7): int
{
	$from = date('Y-m-d 00:00:00');
	$to = (new DateTime("+{$days} days"))->format('Y-m-d 23:59:59');
	$sql = 'SELECT COUNT(*) FROM appointments a WHERE a.appointment_date > ? AND a.appointment_date <= ?';
	$params = [$from, $to];
	if ($doctorId !== null) {
		$sql .= ' AND a.doctor_id = ?';
		$params[] = $doctorId;
	}
	$stmt = db()->prepare($sql);
	$stmt->execute($params);
	return (int)$stmt->fetchColumn();
}

function rooms_occupancy_counts(): array
{
	$stmt = db()->query('SELECT status, COUNT(*) AS c FROM rooms GROUP BY status');
	$out = ['available' => 0, 'occupied' => 0, 'maintenance' => 0];
	foreach ($stmt->fetchAll() as $row) {
		$out[$row['status']] = (int)$row['c'];
	}
	return $out;
}

function admissions_open_list(int $limit = 10): array
{
	$limit = (int)$limit;
	$sql = 'SELECT a.*, p.first_name AS pf, p.last_name AS pl, r.room_number
			FROM admissions a
			JOIN patients p ON p.id = a.patient_id
			JOIN rooms r ON r.id = a.room_id
			WHERE a.discharged_on IS NULL
			ORDER BY a.admitted_on DESC
			LIMIT ' . $limit;
	return db()->query($sql)->fetchAll();
}

function doctor_recent_treatments(int $doctorId, int $limit = 10): array
{
	$limit = (int)$limit;
	$sql = 'SELECT t.*, p.first_name AS pf, p.last_name AS pl
			FROM treatments t
			JOIN patients p ON p.id = t.patient_id
			WHERE t.doctor_id = ?
			ORDER BY t.treatment_date DESC, t.id DESC
			LIMIT ' . $limit;
	$stmt = db()->prepare($sql);
	$stmt->execute([$doctorId]);
	return $stmt->fetchAll();
}

function list_recent_appointments(int $limit = 10, ?int $doctorId = null): array
{
	$sql = 'SELECT a.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last
		FROM appointments a
		JOIN patients p ON p.id = a.patient_id
		JOIN doctors d ON d.id = a.doctor_id';
	$params = [];
	if ($doctorId !== null) {
		$sql .= ' WHERE a.doctor_id = ?';
		$params[] = $doctorId;
	}
	$limit = (int)$limit;
	$sql .= ' ORDER BY a.appointment_date DESC LIMIT ' . $limit;
	$stmt = db()->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll();
}

/* =============== Patients =============== */
function patients_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO patients (first_name,last_name,gender,birthdate,phone,email,address) VALUES (?,?,?,?,?,?,?)');
	$stmt->execute([
		trim($data['first_name'] ?? ''),
		trim($data['last_name'] ?? ''),
		($data['gender'] ?? null) ?: null,
		($data['birthdate'] ?? null) ?: null,
		($data['phone'] ?? null) ?: null,
		($data['email'] ?? null) ?: null,
		($data['address'] ?? null) ?: null,
	]);
	return (int) db()->lastInsertId();
}

function patients_delete(int $id): void
{
	db()->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
}

function patients_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = '';
	$params = [];
	if ($q !== '') {
		$where = ' AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
		$like = "%$q%";
		$params = [$like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT * FROM patients WHERE 1=1' . $where . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE 1=1' . ($q !== '' ? ' AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)' : ''));
	if ($q !== '') {
		$cnt->execute([$like, $like, $like, $like]);
	} else {
		$cnt->execute();
	}
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function patients_options(): array
{
	return db()->query('SELECT id, first_name, last_name FROM patients ORDER BY first_name, last_name')->fetchAll();
}

function patients_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM patients WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function patients_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE patients SET first_name = ?, last_name = ?, gender = ?, birthdate = ?, phone = ?, email = ?, address = ? WHERE id = ?');
	$stmt->execute([
		trim($data['first_name'] ?? ''),
		trim($data['last_name'] ?? ''),
		($data['gender'] ?? null) ?: null,
		($data['birthdate'] ?? null) ?: null,
		($data['phone'] ?? null) ?: null,
		($data['email'] ?? null) ?: null,
		($data['address'] ?? null) ?: null,
		$id,
	]);
}

/* =============== Doctors =============== */
function doctors_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO doctors (first_name,last_name,email,phone,specialization,department_id) VALUES (?,?,?,?,?,?)');
	$stmt->execute([
		trim($data['first_name'] ?? ''),
		trim($data['last_name'] ?? ''),
		trim($data['email'] ?? ''),
		($data['phone'] ?? null) ?: null,
		($data['specialization'] ?? null) ?: null,
		($data['department_id'] ?? null) ?: null,
	]);
	return (int) db()->lastInsertId();
}

function doctors_delete(int $id): void
{
	db()->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
}

function doctors_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = ' WHERE 1=1';
	$params = [];
	if ($q !== '') {
		$where .= ' AND (d.first_name LIKE ? OR d.last_name LIKE ? OR d.email LIKE ? OR d.specialization LIKE ?)';
		$like = "%$q%";
		$params = [$like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT d.*, dept.name AS dept_name FROM doctors d LEFT JOIN departments dept ON dept.id = d.department_id' . $where . ' ORDER BY d.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM doctors d LEFT JOIN departments dept ON dept.id = d.department_id' . ($q !== '' ? ' AND (d.first_name LIKE ? OR d.last_name LIKE ? OR d.email LIKE ? OR d.specialization LIKE ?)' : ''));
	if ($q !== '') {
		$cnt->execute([$like, $like, $like, $like]);
	} else {
		$cnt->execute();
	}
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function doctors_options(): array
{
	return db()->query('SELECT id, first_name, last_name FROM doctors ORDER BY first_name, last_name')->fetchAll();
}

function doctors_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM doctors WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function doctors_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE doctors SET first_name = ?, last_name = ?, email = ?, phone = ?, specialization = ?, department_id = ? WHERE id = ?');
	$stmt->execute([
		trim($data['first_name'] ?? ''),
		trim($data['last_name'] ?? ''),
		trim($data['email'] ?? ''),
		($data['phone'] ?? null) ?: null,
		($data['specialization'] ?? null) ?: null,
		($data['department_id'] ?? null) !== null && $data['department_id'] !== '' ? (int)$data['department_id'] : null,
		$id,
	]);
}

/* =============== Departments =============== */
function departments_create(string $name, string $description = ''): int
{
	$stmt = db()->prepare('INSERT INTO departments (name, description) VALUES (?, ?)');
	$stmt->execute([$name, $description]);
	return (int) db()->lastInsertId();
}

function departments_delete(int $id): void
{
	db()->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
}

function departments_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = '';
	$params = [];
	if ($q !== '') {
		$where = ' WHERE name LIKE ? OR description LIKE ?';
		$like = "%$q%";
		$params = [$like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$querySql = 'SELECT * FROM departments' . $where . ' ORDER BY name LIMIT ' . $limit . ' OFFSET ' . $offset;
	$stmt = $pdo->prepare($querySql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll();
	$countSql = 'SELECT COUNT(*) FROM departments' . ($q !== '' ? ' WHERE name LIKE ? OR description LIKE ?' : '');
	$countStmt = $pdo->prepare($countSql);
	if ($q !== '') {
		$countStmt->execute([$like, $like]);
	} else {
		$countStmt->execute();
	}
	$total = (int)$countStmt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function departments_options(): array
{
	return db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
}

function departments_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM departments WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function departments_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE departments SET name = ?, description = ? WHERE id = ?');
	$stmt->execute([
		trim($data['name'] ?? ''),
		trim($data['description'] ?? ''),
		$id,
	]);
}

/* =============== Appointments =============== */
function appointments_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, notes) VALUES (?,?,?,?,?)');
	$stmt->execute([
		(int)($data['patient_id'] ?? 0),
		(int)($data['doctor_id'] ?? 0),
		($data['appointment_date'] ?? ''),
		($data['status'] ?? 'scheduled'),
		($data['notes'] ?? null),
	]);
	return (int) db()->lastInsertId();
}

function appointments_delete(int $id): void
{
	db()->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);
}

function appointments_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE 1=1';
	$params = [];
	if ($q !== '') {
		$where .= ' AND (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR a.status LIKE ?)';
		$like = "%$q%";
		$params = [$like, $like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$sql = 'SELECT a.*, p.first_name AS pf, p.last_name AS pl, d.first_name AS df, d.last_name AS dl FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id ' . $where . ' ORDER BY a.appointment_date DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
	$list = $pdo->prepare($sql);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id ' . ($q !== '' ? 'WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR a.status LIKE ?)' : ''));
	if ($q !== '') {
		$cnt->execute([$like, $like, $like, $like, $like]);
	} else {
		$cnt->execute();
	}
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function appointments_list_for_doctor(int $doctorId, string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE a.doctor_id = ?';
	$params = [$doctorId];
	if ($q !== '') {
		$where .= ' AND (p.first_name LIKE ? OR p.last_name LIKE ? OR a.status LIKE ?)';
		$like = "%$q%";
		$params = [$doctorId, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$sql = 'SELECT a.*, p.first_name AS pf, p.last_name AS pl, d.first_name AS df, d.last_name AS dl FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id ' . $where . ' ORDER BY a.appointment_date DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
	$list = $pdo->prepare($sql);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id ' . $where);
	$cnt->execute($params);
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function appointments_options_recent(int $limit = 200): array
{
	return db()->query('SELECT id, appointment_date FROM appointments ORDER BY appointment_date DESC LIMIT ' . (int)$limit)->fetchAll();
}

function appointments_minimal_recent(int $limit = 500): array
{
	$limit = (int)$limit;
	$sql = 'SELECT id, appointment_date, patient_id, doctor_id FROM appointments ORDER BY appointment_date DESC LIMIT ' . $limit;
	return db()->query($sql)->fetchAll();
}

function appointments_options_recent_for_doctor(int $doctorId, int $limit = 200): array
{
	$limit = (int)$limit;
	$stmt = db()->prepare('SELECT id, appointment_date FROM appointments WHERE doctor_id = ? ORDER BY appointment_date DESC LIMIT ' . $limit);
	$stmt->execute([$doctorId]);
	return $stmt->fetchAll();
}

function appointments_options_for_patient(int $patientId, int $limit = 200): array
{
	$limit = (int)$limit;
	$stmt = db()->prepare('SELECT id, appointment_date FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC LIMIT ' . $limit);
	$stmt->execute([$patientId]);
	return $stmt->fetchAll();
}

function appointments_options_for_patient_and_doctor(int $patientId, int $doctorId, int $limit = 200): array
{
	$limit = (int)$limit;
	$stmt = db()->prepare('SELECT id, appointment_date FROM appointments WHERE patient_id = ? AND doctor_id = ? ORDER BY appointment_date DESC LIMIT ' . $limit);
	$stmt->execute([$patientId, $doctorId]);
	return $stmt->fetchAll();
}

function appointments_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM appointments WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function appointments_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE appointments SET patient_id = ?, doctor_id = ?, appointment_date = ?, status = ?, notes = ? WHERE id = ?');
	$stmt->execute([
		(int)($data['patient_id'] ?? 0),
		(int)($data['doctor_id'] ?? 0),
		($data['appointment_date'] ?? ''),
		($data['status'] ?? 'scheduled'),
		($data['notes'] ?? null),
		$id,
	]);
}

/* =============== Treatments =============== */
function treatments_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO treatments (patient_id, doctor_id, appointment_id, diagnosis, treatment_date) VALUES (?,?,?,?,?)');
	$stmt->execute([
		(int)($data['patient_id'] ?? 0),
		($data['doctor_id'] ?? null) !== null && $data['doctor_id'] !== '' ? (int)$data['doctor_id'] : null,
		($data['appointment_id'] ?? null) !== null && $data['appointment_id'] !== '' ? (int)$data['appointment_id'] : null,
		($data['diagnosis'] ?? null),
		($data['treatment_date'] ?? date('Y-m-d')),
	]);
	return (int) db()->lastInsertId();
}

function treatments_delete(int $id): void
{
	db()->prepare('DELETE FROM treatments WHERE id = ?')->execute([$id]);
}

function treatments_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE 1=1';
	$params = [];
	if ($q !== '') {
		$where .= ' AND (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR t.diagnosis LIKE ?)';
		$like = "%$q%";
		$params = [$like, $like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT t.*, p.first_name AS pf, p.last_name AS pl, d.first_name AS df, d.last_name AS dl FROM treatments t JOIN patients p ON p.id=t.patient_id LEFT JOIN doctors d ON d.id=t.doctor_id ' . $where . ' ORDER BY t.treatment_date DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM treatments t JOIN patients p ON p.id=t.patient_id LEFT JOIN doctors d ON d.id=t.doctor_id ' . ($q !== '' ? ' WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR t.diagnosis LIKE ?)' : ''));
	if ($q !== '') {
		$cnt->execute([$like, $like, $like, $like, $like]);
	} else {
		$cnt->execute();
	}
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function treatments_list_for_doctor(int $doctorId, string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE t.doctor_id = ?';
	$params = [$doctorId];
	if ($q !== '') {
		$where .= ' AND (p.first_name LIKE ? OR p.last_name LIKE ? OR t.diagnosis LIKE ?)';
		$like = "%$q%";
		$params = [$doctorId, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT t.*, p.first_name AS pf, p.last_name AS pl, d.first_name AS df, d.last_name AS dl FROM treatments t JOIN patients p ON p.id=t.patient_id LEFT JOIN doctors d ON d.id=t.doctor_id ' . $where . ' ORDER BY t.treatment_date DESC, t.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM treatments t JOIN patients p ON p.id=t.patient_id LEFT JOIN doctors d ON d.id=t.doctor_id ' . $where);
	$cnt->execute($params);
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function treatments_options_recent_with_patient(int $limit = 200): array
{
	return db()->query('SELECT t.id, t.treatment_date, p.first_name, p.last_name FROM treatments t JOIN patients p ON p.id=t.patient_id ORDER BY t.id DESC LIMIT ' . (int)$limit)->fetchAll();
}

function treatments_options_recent_with_patient_for_doctor(int $doctorId, int $limit = 200): array
{
	$limit = (int)$limit;
	$stmt = db()->prepare('SELECT t.id, t.treatment_date, p.first_name, p.last_name FROM treatments t JOIN patients p ON p.id=t.patient_id WHERE t.doctor_id = ? ORDER BY t.id DESC LIMIT ' . $limit);
	$stmt->execute([$doctorId]);
	return $stmt->fetchAll();
}

function treatments_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM treatments WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function treatments_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE treatments SET patient_id = ?, doctor_id = ?, appointment_id = ?, diagnosis = ?, treatment_date = ? WHERE id = ?');
	$stmt->execute([
		(int)($data['patient_id'] ?? 0),
		($data['doctor_id'] ?? null) !== null && $data['doctor_id'] !== '' ? (int)$data['doctor_id'] : null,
		($data['appointment_id'] ?? null) !== null && $data['appointment_id'] !== '' ? (int)$data['appointment_id'] : null,
		($data['diagnosis'] ?? null),
		($data['treatment_date'] ?? date('Y-m-d')),
		$id,
	]);
}

/* =============== Prescriptions =============== */
function prescriptions_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO prescriptions (treatment_id, medication_id, dosage, frequency, duration_days) VALUES (?,?,?,?,?)');
	$stmt->execute([
		(int)($data['treatment_id'] ?? 0),
		(int)($data['medication_id'] ?? 0),
		trim($data['dosage'] ?? ''),
		trim($data['frequency'] ?? ''),
		(int)($data['duration_days'] ?? 0),
	]);
	return (int) db()->lastInsertId();
}

function prescriptions_delete(int $id): void
{
	db()->prepare('DELETE FROM prescriptions WHERE id = ?')->execute([$id]);
}

function prescriptions_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE 1=1';
	$params = [];
	if ($q !== '') {
		$where .= ' AND (m.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR pr.dosage LIKE ? OR pr.frequency LIKE ?)';
		$like = "%$q%";
		$params = [$like, $like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT pr.*, m.name AS med_name, t.treatment_date, p.first_name, p.last_name FROM prescriptions pr JOIN medications m ON m.id=pr.medication_id JOIN treatments t ON t.id=pr.treatment_id JOIN patients p ON p.id=t.patient_id ' . $where . ' ORDER BY pr.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM prescriptions pr JOIN medications m ON m.id=pr.medication_id JOIN treatments t ON t.id=pr.treatment_id JOIN patients p ON p.id=t.patient_id ' . ($q !== '' ? ' WHERE (m.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR pr.dosage LIKE ? OR pr.frequency LIKE ?)' : ''));
	if ($q !== '') {
		$cnt->execute([$like, $like, $like, $like, $like]);
	} else {
		$cnt->execute();
	}
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function prescriptions_list_for_doctor(int $doctorId, string $q, int $limit, int $offset): array
{
	$pdo = db();
	$where = 'WHERE t.doctor_id = ?';
	$params = [$doctorId];
	if ($q !== '') {
		$where .= ' AND (m.name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR pr.dosage LIKE ? OR pr.frequency LIKE ?)';
		$like = "%$q%";
		$params = [$doctorId, $like, $like, $like, $like, $like];
	}
	$limit = (int)$limit;
	$offset = (int)$offset;
	$list = $pdo->prepare('SELECT pr.*, m.name AS med_name, t.treatment_date, p.first_name, p.last_name FROM prescriptions pr JOIN medications m ON m.id=pr.medication_id JOIN treatments t ON t.id=pr.treatment_id JOIN patients p ON p.id=t.patient_id ' . $where . ' ORDER BY pr.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$list->execute($params);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM prescriptions pr JOIN medications m ON m.id=pr.medication_id JOIN treatments t ON t.id=pr.treatment_id JOIN patients p ON p.id=t.patient_id ' . $where);
	$cnt->execute($params);
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function prescriptions_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM prescriptions WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function prescriptions_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE prescriptions SET treatment_id = ?, medication_id = ?, dosage = ?, frequency = ?, duration_days = ? WHERE id = ?');
	$stmt->execute([
		(int)($data['treatment_id'] ?? 0),
		(int)($data['medication_id'] ?? 0),
		trim($data['dosage'] ?? ''),
		trim($data['frequency'] ?? ''),
		(int)($data['duration_days'] ?? 0),
		$id,
	]);
}

/* =============== Medications =============== */
function medications_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO medications (name, description) VALUES (?, ?)');
	$stmt->execute([trim($data['name'] ?? ''), trim($data['description'] ?? '')]);
	return (int) db()->lastInsertId();
}

function medications_delete(int $id): void
{
	db()->prepare('DELETE FROM medications WHERE id = ?')->execute([$id]);
}

function medications_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$limit = (int)$limit;
	$offset = (int)$offset;
	if ($q === '') {
		$list = $pdo->prepare('SELECT * FROM medications ORDER BY name LIMIT ' . $limit . ' OFFSET ' . $offset);
		$list->execute();
		$rows = $list->fetchAll();
		$cnt = $pdo->query('SELECT COUNT(*) FROM medications');
		$total = (int)$cnt->fetchColumn();
		return ['rows' => $rows, 'total' => $total];
	}
	$where = 'WHERE (name LIKE ? OR description LIKE ?)';
	$list = $pdo->prepare('SELECT * FROM medications ' . $where . ' ORDER BY name LIMIT ' . $limit . ' OFFSET ' . $offset);
	$like = "%$q%";
	$list->execute([$like, $like]);
	$rows = $list->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM medications ' . $where);
	$cnt->execute([$like, $like]);
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function medications_options(): array
{
	return db()->query('SELECT id, name FROM medications ORDER BY name')->fetchAll();
}

function medications_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM medications WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function medications_update(int $id, array $data): void
{
	$stmt = db()->prepare('UPDATE medications SET name = ?, description = ? WHERE id = ?');
	$stmt->execute([
		trim($data['name'] ?? ''),
		trim($data['description'] ?? ''),
		$id,
	]);
}

/* =============== Rooms & Admissions =============== */
function rooms_create(array $data): int
{
	$stmt = db()->prepare('INSERT INTO rooms (room_number, type, status) VALUES (?,?,?)');
	$stmt->execute([trim($data['room_number'] ?? ''), ($data['type'] ?? null) ?: null, ($data['status'] ?? 'available')]);
	return (int) db()->lastInsertId();
}

function rooms_delete(int $id): void
{
	db()->prepare('DELETE FROM rooms WHERE id = ?')->execute([$id]);
}

function rooms_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$limit = (int)$limit;
	$offset = (int)$offset;
	if ($q === '') {
		$roomsStmt = $pdo->prepare('SELECT * FROM rooms ORDER BY room_number LIMIT ' . $limit . ' OFFSET ' . $offset);
		$roomsStmt->execute();
		$rows = $roomsStmt->fetchAll();
		$total = (int)$pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
		return ['rows' => $rows, 'total' => $total];
	}
	$like = "%$q%";
	$roomsStmt = $pdo->prepare('SELECT * FROM rooms WHERE (room_number LIKE ? OR type LIKE ? OR status LIKE ?) ORDER BY room_number LIMIT ' . $limit . ' OFFSET ' . $offset);
	$roomsStmt->execute([$like, $like, $like]);
	$rows = $roomsStmt->fetchAll();
	$roomsCntStmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE (room_number LIKE ? OR type LIKE ? OR status LIKE ?)');
	$roomsCntStmt->execute([$like, $like, $like]);
	$total = (int)$roomsCntStmt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function rooms_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM rooms WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function rooms_update(int $id, array $data): void
{
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$roomStmt = $pdo->prepare('SELECT status FROM rooms WHERE id = ? FOR UPDATE');
		$roomStmt->execute([$id]);
		$cur = $roomStmt->fetch();
		if (!$cur) {
			throw new RuntimeException('Room not found');
		}
		$newStatus = ($data['status'] ?? 'available');
		$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
		$cntStmt->execute([$id]);
		$open = (int)$cntStmt->fetchColumn();
		if ($open > 0 && $newStatus !== 'occupied') {
			throw new RuntimeException('Cannot change status: room has open admissions');
		}
		if ($open === 0 && $newStatus === 'occupied') {
			throw new RuntimeException('Cannot set occupied: no open admissions');
		}
		$stmt = $pdo->prepare('UPDATE rooms SET room_number = ?, type = ?, status = ? WHERE id = ?');
		$stmt->execute([
			trim($data['room_number'] ?? ''),
			($data['type'] ?? null) ?: null,
			$newStatus,
			$id,
		]);
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
}

function rooms_options_available(): array
{
	return db()->query("SELECT id, room_number FROM rooms WHERE status = 'available' ORDER BY room_number")->fetchAll();
}

function admissions_create(array $data): int
{
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$roomId = (int)($data['room_id'] ?? 0);
		$lock = $pdo->prepare('SELECT status FROM rooms WHERE id = ? FOR UPDATE');
		$lock->execute([$roomId]);
		$roomRow = $lock->fetch();
		if (!$roomRow) {
			throw new RuntimeException('Room not found');
		}
		if ($roomRow['status'] !== 'available') {
			throw new RuntimeException('Room is not available');
		}
		$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
		$cntStmt->execute([$roomId]);
		if ((int)$cntStmt->fetchColumn() > 0) {
			throw new RuntimeException('Room already has an open admission');
		}
		$stmt = $pdo->prepare('INSERT INTO admissions (patient_id, room_id, admitted_on, discharged_on, notes) VALUES (?,?,?,NULL,?)');
		$stmt->execute([
			(int)($data['patient_id'] ?? 0),
			$roomId,
			($data['admitted_on'] ?? ''),
			($data['notes'] ?? null),
		]);
		$newId = (int) $pdo->lastInsertId();
		$upd = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
		$upd->execute([$roomId]);
		$pdo->commit();
		return $newId;
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
}

function admissions_discharge(int $id): void
{
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$stmt = $pdo->prepare('SELECT room_id FROM admissions WHERE id = ? FOR UPDATE');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		if ($row) {
			$roomId = (int)$row['room_id'];
			$pdo->prepare('UPDATE admissions SET discharged_on = NOW() WHERE id = ? AND discharged_on IS NULL')->execute([$id]);
			$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
			$cntStmt->execute([$roomId]);
			$open = (int)$cntStmt->fetchColumn();
			if ($open === 0) {
				$pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$roomId]);
			}
		}
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
}

function admissions_delete(int $id): void
{
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$stmt = $pdo->prepare('SELECT room_id, discharged_on FROM admissions WHERE id = ? FOR UPDATE');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		$pdo->prepare('DELETE FROM admissions WHERE id = ?')->execute([$id]);
		if ($row) {
			$roomId = (int)$row['room_id'];
			$wasOpen = $row['discharged_on'] === null;
			if ($wasOpen) {
				$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
				$cntStmt->execute([$roomId]);
				$open = (int)$cntStmt->fetchColumn();
				if ($open === 0) {
					$pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$roomId]);
				}
			}
		}
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
}

function admissions_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$limit = (int)$limit;
	$offset = (int)$offset;
	if ($q === '') {
		$admStmt = $pdo->prepare('SELECT a.*, p.first_name AS pf, p.last_name AS pl, r.room_number FROM admissions a JOIN patients p ON p.id=a.patient_id JOIN rooms r ON r.id=a.room_id ORDER BY a.admitted_on DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
		$admStmt->execute();
		$rows = $admStmt->fetchAll();
		$admCntStmt = $pdo->query('SELECT COUNT(*) FROM admissions a JOIN patients p ON p.id=a.patient_id JOIN rooms r ON r.id=a.room_id');
		$total = (int)$admCntStmt->fetchColumn();
		return ['rows' => $rows, 'total' => $total];
	}
	$like = "%$q%";
	$admStmt = $pdo->prepare('SELECT a.*, p.first_name AS pf, p.last_name AS pl, r.room_number FROM admissions a JOIN patients p ON p.id=a.patient_id JOIN rooms r ON r.id=a.room_id WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR r.room_number LIKE ?) ORDER BY a.admitted_on DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$admStmt->execute([$like, $like, $like]);
	$rows = $admStmt->fetchAll();
	$admCntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions a JOIN patients p ON p.id=a.patient_id JOIN rooms r ON r.id=a.room_id WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR r.room_number LIKE ?)');
	$admCntStmt->execute([$like, $like, $like]);
	$total = (int)$admCntStmt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function admissions_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM admissions WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function admissions_update(int $id, array $data): void
{
	$pdo = db();
	$pdo->beginTransaction();
	try {
		$curStmt = $pdo->prepare('SELECT room_id, discharged_on FROM admissions WHERE id = ? FOR UPDATE');
		$curStmt->execute([$id]);
		$cur = $curStmt->fetch();
		$oldRoomId = $cur ? (int)$cur['room_id'] : 0;
		$isOpen = $cur ? ($cur['discharged_on'] === null) : false;
		$newRoomId = (int)($data['room_id'] ?? 0);
		if ($isOpen && $newRoomId && $newRoomId !== $oldRoomId) {
			$lockNew = $pdo->prepare('SELECT status FROM rooms WHERE id = ? FOR UPDATE');
			$lockNew->execute([$newRoomId]);
			$newRoomRow = $lockNew->fetch();
			if (!$newRoomRow) {
				throw new RuntimeException('New room not found');
			}
			if ($newRoomRow['status'] !== 'available') {
				throw new RuntimeException('New room is not available');
			}
			$cntNew = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
			$cntNew->execute([$newRoomId]);
			if ((int)$cntNew->fetchColumn() > 0) {
				throw new RuntimeException('New room already has an open admission');
			}
		}
		$stmt = $pdo->prepare('UPDATE admissions SET patient_id = ?, room_id = ?, admitted_on = ?, notes = ? WHERE id = ?');
		$stmt->execute([
			(int)($data['patient_id'] ?? 0),
			$newRoomId,
			($data['admitted_on'] ?? ''),
			($data['notes'] ?? null),
			$id,
		]);
		if ($isOpen) {
			if ($newRoomId && $newRoomId !== $oldRoomId) {
				$pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?")->execute([$newRoomId]);
				if ($oldRoomId) {
					$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE room_id = ? AND discharged_on IS NULL');
					$cntStmt->execute([$oldRoomId]);
					$open = (int)$cntStmt->fetchColumn();
					if ($open === 0) {
						$pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$oldRoomId]);
					}
				}
			}
		}
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
}

/* =============== Users =============== */
function users_delete(int $id): void
{
	db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

function users_list(string $q, int $limit, int $offset): array
{
	$pdo = db();
	$limit = (int)$limit;
	$offset = (int)$offset;
	if ($q === '') {
		$stmt = $pdo->prepare('SELECT * FROM users ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
		return ['rows' => $rows, 'total' => $total];
	}
	$like = "%$q%";
	$stmt = $pdo->prepare('SELECT * FROM users WHERE (username LIKE ? OR email LIKE ? OR role LIKE ?) ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
	$stmt->execute([$like, $like, $like]);
	$rows = $stmt->fetchAll();
	$cnt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username LIKE ? OR email LIKE ? OR role LIKE ?)');
	$cnt->execute([$like, $like, $like]);
	$total = (int)$cnt->fetchColumn();
	return ['rows' => $rows, 'total' => $total];
}

function users_get(int $id): ?array
{
	$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function users_update(int $id, array $data): void
{
	$fields = [
		'username' => trim($data['username'] ?? ''),
		'email' => trim($data['email'] ?? ''),
		'role' => (string)($data['role'] ?? ROLE_STAFF),
		'linked_doctor_id' => ($data['linked_doctor_id'] ?? null) !== null && $data['linked_doctor_id'] !== '' ? (int)$data['linked_doctor_id'] : null,
	];
	$sql = 'UPDATE users SET username = ?, email = ?, role = ?, linked_doctor_id = ?';
	$params = [
		$fields['username'],
		$fields['email'],
		$fields['role'],
		$fields['linked_doctor_id'],
	];
	if (!empty($data['password'])) {
		$sql .= ', password_hash = ?';
		$params[] = password_hash((string)$data['password'], PASSWORD_BCRYPT);
	}
	$sql .= ' WHERE id = ?';
	$params[] = $id;
	$stmt = db()->prepare($sql);
	$stmt->execute($params);
}
