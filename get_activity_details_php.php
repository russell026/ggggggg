<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID kegiatan tidak valid']);
    exit;
}

$activityId = (int)$_GET['id'];

try {
    $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE id = ?");
    $stmt->bind_param("i", $activityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Kegiatan tidak ditemukan']);
        exit;
    }
    
    $activity = $result->fetch_assoc();
    
    // Format dates
    $activity['tanggal_mulai_formatted'] = !empty($activity['tanggal_mulai']) ? formatDate($activity['tanggal_mulai']) : '';
    $activity['tanggal_selesai_formatted'] = !empty($activity['tanggal_selesai']) ? formatDate($activity['tanggal_selesai']) : '';
    
    // Get image path
    $activity['image_path'] = getImagePath($activity['gambar']);
    
    // Sanitize output
    $activity['nama_kegiatan'] = sanitize($activity['nama_kegiatan']);
    $activity['deskripsi'] = sanitize($activity['deskripsi']);
    $activity['tempat'] = sanitize($activity['tempat']);
    $activity['pembimbing'] = sanitize($activity['pembimbing']);
    $activity['kategori'] = ucfirst($activity['kategori']);
    
    echo json_encode([
        'success' => true,
        'activity' => $activity
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}
?>