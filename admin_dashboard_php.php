<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('../login.php?type=admin');
}

$currentAdmin = getCurrentUser();

// Get statistics
$stats = [];

try {
    // Total activities
    $result = $db->query("SELECT COUNT(*) as total FROM extracurricular_activities");
    $stats['total_activities'] = $result->fetch_assoc()['total'];
    
    // Open activities
    $result = $db->query("SELECT COUNT(*) as total FROM extracurricular_activities WHERE status_pendaftaran = 'open'");
    $stats['open_activities'] = $result->fetch_assoc()['total'];
    
    // Total students
    $result = $db->query("SELECT COUNT(*) as total FROM students");
    $stats['total_students'] = $result->fetch_assoc()['total'];
    
    // Total registrations
    $result = $db->query("SELECT COUNT(*) as total FROM registrations");
    $stats['total_registrations'] = $result->fetch_assoc()['total'];
    
    // Pending registrations
    $result = $db->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'pending'");
    $stats['pending_registrations'] = $result->fetch_assoc()['total'];
    
    // Approved registrations
    $result = $db->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'approved'");
    $stats['approved_registrations'] = $result->fetch_assoc()['total'];
    
} catch (Exception $e) {
    showAlert('Terjadi kesalahan saat memuat statistik: ' . $e->getMessage(), 'error');
}

// Get recent registrations
$recentRegistrations = [];
try {
    $stmt = $db->prepare("SELECT r.*, s.nama as student_name, s.nim, a.nama_kegiatan 
                         FROM registrations r 
                         JOIN students s ON r.student_id = s.id 
                         JOIN extracurricular_activities a ON r.activity_id = a.id 
                         ORDER BY r.tanggal_daftar DESC 
                         LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentRegistrations[] = $row;
    }
} catch (Exception $e) {
    showAlert('Terjadi kesalahan saat memuat data pendaftaran terbaru: ' . $e->getMessage(), 'error');
}

// Get activities with low quota
$lowQuotaActivities = [];
try {
    $stmt = $db->prepare("SELECT *, (kuota - peserta_terdaftar) as remaining_quota 
                         FROM extracurricular_activities 
                         WHERE status_pendaftaran = 'open' AND (kuota - peserta_terdaftar) <= 5 
                         ORDER BY remaining_quota ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lowQuotaActivities[] = $row;
    }
} catch (Exception $e) {
    // Silent error for this section
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/unsrat-logo.png">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../assets/images/unsrat-logo.png" alt="UNSRAT Logo" onerror="this.style.display='none'">
                <div>
                    <h1><?php echo SITE_NAME; ?></h1>
                    <p style="font-size: 0.9rem; opacity: 0.9;">Dashboard Admin</p>
                </div>
            </div>
            <nav class="header-nav">
                <span style="margin-right: 1rem;">Admin: <?php echo sanitize($currentAdmin['nama']); ?></span>
                <a href="../index.php" class="btn btn-outline">Lihat Portal</a>
                <a href="../logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Admin Navigation -->
    <nav style="background-color: #f8fafc; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
        <div class="container">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                <a href="manage-activities.php" class="btn btn-secondary">Kelola Kegiatan</a>
                <a href="manage-students.php" class="btn btn-secondary">Kelola Mahasiswa</a>
                <a href="manage-registrations.php" class="btn btn-secondary">Kelola Pendaftaran</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php displayAlert(); ?>
            
            <div class="section-title">
                <h2>Dashboard Admin</h2>
                <p>Kelola Portal Ekstrakurikuler UNSRAT</p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_activities']; ?></div>
                    <div class="stat-label">Total Kegiatan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['open_activities']; ?></div>
                    <div class="stat-label">Kegiatan Terbuka</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Mahasiswa</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_registrations']; ?></div>
                    <div class="stat-label">Total Pendaftaran</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['pending_registrations']; ?></div>
                    <div class="stat-label">Menunggu Persetujuan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #10b981;"><?php echo $stats['approved_registrations']; ?></div>
                    <div class="stat-label">Disetujui</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: #1e40af;">Aksi Cepat</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="manage-activities.php?action=add" class="btn btn-success">Tambah Kegiatan</a>
                    <a href="manage-registrations.php?filter=pending" class="btn btn-warning">Tinjau Pendaftaran</a>
                    <a href="manage-students.php?action=add" class="btn btn-primary">Tambah Mahasiswa</a>
                    <a href="reports.php" class="btn btn-secondary">Lihat Laporan</a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Recent Registrations -->
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h3 style="margin-bottom: 1.5rem; color: #1e40af;">Pendaftaran Terbaru</h3>
                    <?php if (!empty($recentRegistrations)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach (array_slice($recentRegistrations, 0, 5) as $registration): ?>
                            <div style="padding: 1rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?php echo sanitize($registration['student_name']); ?></strong>
                                    <br><small><?php echo sanitize($registration['nim']); ?></small>
                                    <br><small style="color: #6b7280;"><?php echo sanitize($registration['nama_kegiatan']); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($registration['status']) {
                                        case 'pending':
                                            $statusClass = 'status-warning';
                                            $statusText = 'Menunggu';
                                            break;
                                        case 'approved':
                                            $statusClass = 'status-open';
                                            $statusText = 'Diterima';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'status-closed';
                                            $statusText = 'Ditolak';
                                            break;
                                    }
                                    ?>
                                    <span class="activity-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    <br><small style="color: #6b7280;"><?php echo formatDateTime($registration['tanggal_daftar']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="manage-registrations.php" class="btn btn-primary">Lihat Semua</a>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280; text-align: center;">Belum ada pendaftaran</p>
                    <?php endif; ?>
                </div>

                <!-- Low Quota Alert -->
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h3 style="margin-bottom: 1.5rem; color: #1e40af;">Peringatan Kuota</h3>
                    <?php if (!empty($lowQuotaActivities)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($lowQuotaActivities as $activity): ?>
                            <div style="padding: 1rem; border-bottom: 1px solid #e5e7eb;">
                                <strong><?php echo sanitize($activity['nama_kegiatan']); ?></strong>
                                <div style="margin-top: 0.5rem;">
                                    <span style="color: #ef4444; font-weight: 500;">
                                        Sisa kuota: <?php echo $activity['remaining_quota']; ?> dari <?php echo $activity['kuota']; ?>
                                    </span>
                                </div>
                                <div style="margin-top: 0.5rem;">
                                    <a href="manage-activities.php?action=edit&id=<?php echo $activity['id']; ?>" 
                                       class="btn btn-warning btn-sm">Edit Kuota</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #10b981; text-align: center;">✅ Semua kegiatan memiliki kuota yang cukup</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                <h3 style="margin-bottom: 1.5rem; color: #1e40af;">Informasi Sistem</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong>Versi Portal:</strong><br>
                        <span style="color: #6b7280;">v1.0.0</span>
                    </div>
                    <div>
                        <strong>Database:</strong><br>
                        <span style="color: #6b7280;"><?php echo DB_NAME; ?></span>
                    </div>
                    <div>
                        <strong>Server Time:</strong><br>
                        <span style="color: #6b7280;"><?php echo date('d M Y H:i:s'); ?></span>
                    </div>
                    <div>
                        <strong>Upload Folder:</strong><br>
                        <span style="color: #6b7280;"><?php echo is_writable('../' . UPLOAD_PATH) ? '✅ Writable' : '❌ Not Writable'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Dashboard Admin.</p>
        </div>
    </footer>

    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            // You can implement auto-refresh here if needed
        }, 30000);

        // Welcome message
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard Admin Portal Ekstrakurikuler UNSRAT loaded successfully');
        });
    </script>
</body>
</html>