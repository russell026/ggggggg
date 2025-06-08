<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('../login.php?type=admin');
}

$currentAdmin = getCurrentUser();
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_registration'])) {
        // Approve registration
        try {
            $registrationId = (int)$_POST['registration_id'];
            
            $stmt = $db->prepare("UPDATE registrations SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $registrationId);
            
            if ($stmt->execute()) {
                $message = 'Pendaftaran berhasil disetujui!';
                $messageType = 'success';
            } else {
                throw new Exception('Gagal menyetujui pendaftaran');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['reject_registration'])) {
        // Reject registration
        try {
            $registrationId = (int)$_POST['registration_id'];
            $keterangan = sanitize($_POST['rejection_reason']);
            
            $stmt = $db->prepare("UPDATE registrations SET status = 'rejected', keterangan = ? WHERE id = ?");
            $stmt->bind_param("si", $keterangan, $registrationId);
            
            if ($stmt->execute()) {
                // Update participant count
                $stmt = $db->prepare("SELECT activity_id FROM registrations WHERE id = ?");
                $stmt->bind_param("i", $registrationId);
                $stmt->execute();
                $result = $stmt->get_result();
                $registration = $result->fetch_assoc();
                
                if ($registration) {
                    $stmt = $db->prepare("UPDATE extracurricular_activities SET peserta_terdaftar = peserta_terdaftar - 1 WHERE id = ? AND peserta_terdaftar > 0");
                    $stmt->bind_param("i", $registration['activity_id']);
                    $stmt->execute();
                }
                
                $message = 'Pendaftaran berhasil ditolak!';
                $messageType = 'success';
            } else {
                throw new Exception('Gagal menolak pendaftaran');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['delete_registration'])) {
        // Delete registration
        try {
            $registrationId = (int)$_POST['registration_id'];
            
            // Get registration details first
            $stmt = $db->prepare("SELECT activity_id, status FROM registrations WHERE id = ?");
            $stmt->bind_param("i", $registrationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $registration = $result->fetch_assoc();
            
            if (!$registration) {
                throw new Exception('Pendaftaran tidak ditemukan');
            }
            
            // Delete registration
            $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
            $stmt->bind_param("i", $registrationId);
            
            if ($stmt->execute()) {
                // Update participant count if was approved
                if ($registration['status'] === 'approved') {
                    $stmt = $db->prepare("UPDATE extracurricular_activities SET peserta_terdaftar = peserta_terdaftar - 1 WHERE id = ? AND peserta_terdaftar > 0");
                    $stmt->bind_param("i", $registration['activity_id']);
                    $stmt->execute();
                }
                
                $message = 'Pendaftaran berhasil dihapus!';
                $messageType = 'success';
            } else {
                throw new Exception('Gagal menghapus pendaftaran');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$statusFilter = isset($_GET['filter']) ? sanitize($_GET['filter']) : '';
$activityFilter = isset($_GET['activity']) ? (int)$_GET['activity'] : 0;
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Get registrations
$registrations = [];
try {
    $sql = "SELECT r.*, s.nim, s.nama as student_name, s.email as student_email, s.fakultas, s.jurusan,
                   a.nama_kegiatan, a.whatsapp_link, a.gambar, a.status_pendaftaran
            FROM registrations r 
            JOIN students s ON r.student_id = s.id 
            JOIN extracurricular_activities a ON r.activity_id = a.id";
    
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "r.status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }
    
    if ($activityFilter > 0) {
        $whereConditions[] = "r.activity_id = ?";
        $params[] = $activityFilter;
        $types .= "i";
    }
    
    if (!empty($searchQuery)) {
        $whereConditions[] = "(s.nim LIKE ? OR s.nama LIKE ? OR a.nama_kegiatan LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= "sss";
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY r.tanggal_daftar DESC";
    
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
    }
} catch (Exception $e) {
    $message = 'Terjadi kesalahan saat memuat data: ' . $e->getMessage();
    $messageType = 'error';
}

// Get activities for filter
$activities = [];
try {
    $result = $db->query("SELECT id, nama_kegiatan FROM extracurricular_activities ORDER BY nama_kegiatan");
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
} catch (Exception $e) {
    // Silent error
}

// Get statistics
$stats = [];
try {
    $result = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM registrations");
    $stats = $result->fetch_assoc();
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pendaftaran - <?php echo SITE_NAME; ?></title>
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
                    <p style="font-size: 0.9rem; opacity: 0.9;">Kelola Pendaftaran</p>
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
                <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="manage-activities.php" class="btn btn-secondary">Kelola Kegiatan</a>
                <a href="manage-students.php" class="btn btn-secondary">Kelola Mahasiswa</a>
                <a href="manage-registrations.php" class="btn btn-primary">Kelola Pendaftaran</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="section-title">
                <h2>Kelola Pendaftaran Mahasiswa</h2>
                <p>Tinjau dan kelola pendaftaran ekstrakurikuler</p>
            </div>

            <!-- Statistics -->
            <div class="dashboard-stats" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Pendaftaran</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Menunggu</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #10b981;"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Disetujui</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ef4444;"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>

            <!-- Filters -->
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 2rem;">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Cari</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="NIM, nama mahasiswa, atau nama kegiatan..."
                               value="<?php echo sanitize($searchQuery); ?>">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
                        <select name="filter" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Disetujui</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Kegiatan</label>
                        <select name="activity" class="form-select">
                            <option value="0">Semua Kegiatan</option>
                            <?php foreach ($activities as $activity): ?>
                                <option value="<?php echo $activity['id']; ?>" 
                                        <?php echo $activityFilter == $activity['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($activity['nama_kegiatan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Quick Actions -->
            <div style="margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="?filter=pending" class="btn btn-warning">
                    Tinjau Menunggu (<?php echo $stats['pending']; ?>)
                </a>
                <a href="?filter=approved" class="btn btn-success">
                    Lihat Disetujui (<?php echo $stats['approved']; ?>)
                </a>
                <a href="?filter=rejected" class="btn btn-danger">
                    Lihat Ditolak (<?php echo $stats['rejected']; ?>)
                </a>
            </div>

            <!-- Registrations List -->
            <?php if (!empty($registrations)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Kegiatan</th>
                            <th>Tanggal Daftar</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo sanitize($registration['student_name']); ?></strong>
                                    <br><small style="color: #6b7280;">NIM: <?php echo sanitize($registration['nim']); ?></small>
                                    <?php if (!empty($registration['student_email'])): ?>
                                        <br><small style="color: #6b7280;">📧 <?php echo sanitize($registration['student_email']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($registration['fakultas'])): ?>
                                        <br><small style="color: #6b7280;">🏛️ <?php echo sanitize($registration['fakultas']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <img src="../<?php echo getImagePath($registration['gambar']); ?>" 
                                         alt="<?php echo sanitize($registration['nama_kegiatan']); ?>"
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;"
                                         onerror="this.src='../<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                                    <div>
                                        <strong><?php echo sanitize($registration['nama_kegiatan']); ?></strong>
                                        <br><small style="color: #6b7280;">
                                            <?php echo $registration['status_pendaftaran'] === 'open' ? '🟢 Terbuka' : '🔴 Ditutup'; ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo formatDateTime($registration['tanggal_daftar']); ?>
                            </td>
                            <td>
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
                                        $statusText = 'Disetujui';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'status-closed';
                                        $statusText = 'Ditolak';
                                        break;
                                }
                                ?>
                                <span class="activity-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                            <td>
                                <?php if (!empty($registration['keterangan'])): ?>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo sanitize($registration['keterangan']); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($registration['status'] === 'pending'): ?>
                                        <button onclick="approveRegistration(<?php echo $registration['id']; ?>)" 
                                                class="btn btn-success btn-sm">Setujui</button>
                                        <button onclick="rejectRegistration(<?php echo $registration['id']; ?>)" 
                                                class="btn btn-danger btn-sm">Tolak</button>
                                    <?php elseif ($registration['status'] === 'approved' && !empty($registration['whatsapp_link'])): ?>
                                        <a href="<?php echo sanitize($registration['whatsapp_link']); ?>" 
                                           target="_blank" class="btn btn-success btn-sm">WhatsApp</a>
                                    <?php endif; ?>
                                    <button onclick="deleteRegistration(<?php echo $registration['id']; ?>)" 
                                            class="btn btn-secondary btn-sm">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center" style="padding: 3rem 0;">
                <h3 style="color: #6b7280; margin-bottom: 1rem;">
                    <?php echo !empty($searchQuery) || !empty($statusFilter) || $activityFilter > 0 ? 'Tidak Ada Hasil' : 'Belum Ada Pendaftaran'; ?>
                </h3>
                <p style="color: #9ca3af;">
                    <?php if (!empty($searchQuery) || !empty($statusFilter) || $activityFilter > 0): ?>
                        Tidak ditemukan pendaftaran yang sesuai dengan filter.
                    <?php else: ?>
                        Pendaftaran mahasiswa akan muncul di sini.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tolak Pendaftaran</h3>
                <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="registration_id" id="rejectRegistrationId">
                <div class="form-group">
                    <label for="rejection_reason" class="form-label">Alasan Penolakan</label>
                    <textarea name="rejection_reason" id="rejection_reason" class="form-textarea" 
                              placeholder="Jelaskan alasan penolakan pendaftaran..." required></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="reject_registration" class="btn btn-danger w-full">
                        Tolak Pendaftaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Forms -->
    <form id="approveForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="registration_id" id="approveRegistrationId">
        <input type="hidden" name="approve_registration" value="1">
    </form>

    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="registration_id" id="deleteRegistrationId">
        <input type="hidden" name="delete_registration" value="1">
    </form>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Dashboard Admin.</p>
        </div>
    </footer>

    <script>
        function approveRegistration(id) {
            if (confirm('Apakah Anda yakin ingin menyetujui pendaftaran ini?')) {
                document.getElementById('approveRegistrationId').value = id;
                document.getElementById('approveForm').submit();
            }
        }

        function rejectRegistration(id) {
            document.getElementById('rejectRegistrationId').value = id;
            document.getElementById('rejection_reason').value = '';
            document.getElementById('rejectModal').classList.add('show');
        }

        function deleteRegistration(id) {
            if (confirm('Apakah Anda yakin ingin menghapus pendaftaran ini?')) {
                document.getElementById('deleteRegistrationId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('rejectModal');
            }
        });

        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            // You can implement auto-refresh here if needed
        }, 30000);
    </script>
</body>
</html>