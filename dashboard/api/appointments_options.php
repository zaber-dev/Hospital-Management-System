<?php
require_once __DIR__ . '/../../database.php';
require_any_role([ROLE_ADMIN, ROLE_STAFF, ROLE_DOCTOR]);

header('Content-Type: application/json');

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patientId <= 0) {
    echo json_encode([]);
    exit;
}

$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : 0;

try {
    if ($isDoctor && $doctorId > 0) {
        $rows = appointments_options_for_patient_and_doctor($patientId, $doctorId, 200);
    } else {
        $rows = appointments_options_for_patient($patientId, 200);
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'appointment_date' => $r['appointment_date'] ?? '',
            'patient_id' => isset($r['patient_id']) ? (int)$r['patient_id'] : null,
            'doctor_id' => isset($r['doctor_id']) ? (int)$r['doctor_id'] : null,
        ];
    }
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
