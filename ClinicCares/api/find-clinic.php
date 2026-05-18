<?php
/**
 * ClinicCare – Find Nearest Clinic/Doctor API
 * Returns JSON list of clinics & doctors sorted by distance from a given lat/lng
 */
require_once __DIR__ . '/../includes/session.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-store');

$lat    = (float)($_GET['lat']    ?? 0);
$lng    = (float)($_GET['lng']    ?? 0);
$radius = min(100, max(1, (float)($_GET['radius'] ?? 25)));
$type   = sanitize($_GET['type']  ?? 'all');
$walkin = isset($_GET['walkin'])  ? 1 : null;
$tele   = isset($_GET['tele'])    ? 1 : null;
$search = sanitize($_GET['q']     ?? '');

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'error' => 'Location is required.']);
    exit;
}

// ── helper: Haversine distance expression ─────────────────────────────────
// Uses positional ? params — avoids PDO duplicate-named-param bug
// Caller must bind: $lat, $lng in the right position
function haversine(string $latCol, string $lngCol): string {
    return "(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS($latCol))
        * COS(RADIANS($lngCol) - RADIANS(?))
        + SIN(RADIANS(?)) * SIN(RADIANS($latCol)))))";
}

$results = [];

// ── 1. Standalone Clinics ──────────────────────────────────────────────────
if (in_array($type, ['all', 'clinic', 'hospital', 'diagnostic', 'specialty', 'emergency'])) {

    // Check clinics table exists first
    $tableExists = db()->fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinics'"
    );

    if ($tableExists && $tableExists['c'] > 0) {
        $dist   = haversine('c.lat', 'c.lng');
        $where  = "c.is_active = 1 AND c.lat IS NOT NULL AND c.lng IS NOT NULL";
        $params = [$lat, $lng, $lat]; // for haversine

        if ($type === 'emergency') {
            $where .= " AND c.emergency = 1";
        } elseif (!in_array($type, ['all'])) {
            $where .= " AND c.type = ?";
            $params[] = $type;
        }
        if ($walkin) { $where .= " AND c.accepts_walkin = 1"; }
        if ($tele)   { $where .= " AND c.telemedicine = 1"; }
        if ($search) {
            $where .= " AND (c.name LIKE ? OR c.city LIKE ? OR c.specializations LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        $params[] = $radius; // for HAVING

        $clinics = db()->fetchAll(
            "SELECT c.id, c.name, c.type, c.address, c.city,
                    c.lat, c.lng, c.phone, c.email, c.website,
                    c.hours, c.accepts_walkin, c.telemedicine, c.emergency,
                    c.rating, c.description, c.specializations,
                    ROUND($dist, 2) AS distance_km
             FROM clinics c
             WHERE $where
             HAVING distance_km <= ?
             ORDER BY distance_km ASC
             LIMIT 30",
            $params
        );

        foreach ($clinics as $c) {
            $results[] = [
                'id'               => 'c_' . $c['id'],
                'kind'             => 'clinic',
                'name'             => $c['name'],
                'subtype'          => $c['type'],
                'address'          => trim(($c['address'] ?? '') . ', ' . ($c['city'] ?? ''), ', '),
                'city'             => $c['city'],
                'lat'              => (float)$c['lat'],
                'lng'              => (float)$c['lng'],
                'phone'            => $c['phone'],
                'email'            => $c['email'],
                'website'          => $c['website'],
                'hours'            => $c['hours'],
                'accepts_walkin'   => (bool)$c['accepts_walkin'],
                'telemedicine'     => (bool)$c['telemedicine'],
                'emergency'        => (bool)$c['emergency'],
                'rating'           => $c['rating'] ? (float)$c['rating'] : null,
                'description'      => $c['description'],
                'specializations'  => $c['specializations'] ? array_map('trim', explode(',', $c['specializations'])) : [],
                'distance_km'      => (float)$c['distance_km'],
                'doctor_name'      => null,
                'consultation_fee' => null,
                'book_url'         => null,
            ];
        }
    }
}

// ── 2. Doctors with clinic location set ────────────────────────────────────
if (in_array($type, ['all', 'doctor'])) {

    // Check columns exist (graceful fallback if migration not run)
    $colCheck = db()->fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors'
         AND COLUMN_NAME = 'clinic_lat'"
    );

    if ($colCheck && $colCheck['c'] > 0) {
        $dist   = haversine('d.clinic_lat', 'd.clinic_lng');
        $where  = "d.clinic_lat IS NOT NULL AND d.clinic_lng IS NOT NULL
                   AND d.clinic_lat != 0 AND d.clinic_lng != 0
                   AND u.is_active = 1";
        $params = [$lat, $lng, $lat]; // for haversine

        if ($walkin) { $where .= " AND d.accepts_walkin = 1"; }
        if ($tele)   { $where .= " AND d.telemedicine = 1"; }
        if ($search) {
            $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?
                          OR d.specialization LIKE ? OR d.clinic_name LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        $params[] = $radius; // for HAVING

        $doctors = db()->fetchAll(
            "SELECT d.id AS doctor_id,
                    CONCAT(u.first_name,' ',u.last_name) AS doctor_name,
                    d.specialization, d.consultation_fee,
                    d.clinic_name, d.clinic_address, d.clinic_city,
                    d.clinic_lat AS lat, d.clinic_lng AS lng,
                    d.clinic_phone AS phone, d.clinic_hours AS hours,
                    d.accepts_walkin, d.telemedicine, d.bio,
                    ROUND($dist, 2) AS distance_km
             FROM doctors d
             JOIN users u ON d.user_id = u.id
             WHERE $where
             HAVING distance_km <= ?
             ORDER BY distance_km ASC
             LIMIT 20",
            $params
        );

        foreach ($doctors as $d) {
            $results[] = [
                'id'               => 'd_' . $d['doctor_id'],
                'kind'             => 'doctor',
                'subtype'          => 'doctor',
                'name'             => $d['clinic_name'] ?: ('Dr. ' . $d['doctor_name'] . '\'s Clinic'),
                'address'          => trim(($d['clinic_address'] ?? '') . ', ' . ($d['clinic_city'] ?? ''), ', '),
                'city'             => $d['clinic_city'],
                'lat'              => (float)$d['lat'],
                'lng'              => (float)$d['lng'],
                'phone'            => $d['phone'],
                'email'            => null,
                'website'          => null,
                'hours'            => $d['hours'],
                'accepts_walkin'   => (bool)$d['accepts_walkin'],
                'telemedicine'     => (bool)$d['telemedicine'],
                'emergency'        => false,
                'rating'           => null,
                'description'      => $d['bio'],
                'specializations'  => $d['specialization'] ? [$d['specialization']] : [],
                'distance_km'      => (float)$d['distance_km'],
                'doctor_name'      => 'Dr. ' . $d['doctor_name'],
                'consultation_fee' => $d['consultation_fee'] ? (float)$d['consultation_fee'] : null,
                'book_url'         => '/patient/book.php',
            ];
        }
    }
}

// Sort combined results by distance
usort($results, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
$results = array_slice($results, 0, 40);

echo json_encode([
    'success'       => true,
    'count'         => count($results),
    'results'       => $results,
    'search_center' => ['lat' => $lat, 'lng' => $lng],
    'radius_km'     => $radius,
]);
