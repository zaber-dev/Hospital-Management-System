<?php
require_once __DIR__ . '/../../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

try {
    verify_csrf_or_die();
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_csrf']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = trim((string)($_POST['status'] ?? ''));
if ($id <= 0 || !in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

$row = appointments_get($id);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$user = current_user();
$isDoctor = has_role(ROLE_DOCTOR);
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : 0;
if ($isDoctor) {
    if (!$doctorId || (int)$row['doctor_id'] !== $doctorId) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
}

$payload = [
    'patient_id' => (int)$row['patient_id'],
    'doctor_id' => (int)$row['doctor_id'],
    'appointment_date' => $row['appointment_date'],
    'status' => $status,
    'notes' => $row['notes'] ?? '',
];

appointments_update($id, $payload);
echo json_encode(['ok' => true]);
