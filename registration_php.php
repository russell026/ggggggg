<?php
require_once 'config.php';

// Check if student is logged in
if (!isStudent()) {
    redirect('login.php?redirect=registration.php');
}

$currentUser = getCurrentUser();
$selectedActivityId = isset($_GET['activity']) ? (int)$_GET['activity'] : 0;
$message = '';
$messageType = '';

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $activityId = (int)$_POST['activity_id'];
    $keterangan = sanitize($_POST['keterangan']);
    
    try {
        // Check if activity exists and is open
        $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE id = ? AND status_pendaftaran = 'open'");
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Kegiatan tidak ditemukan atau pendaftaran sudah ditutup');
        }
        
        $activity = $result->fetch_assoc();
        
        // Check if student is already registered
        $stmt = $db->prepare("SELECT * FROM registrations WHERE student_id = ? AND activity_id = ?");
        $stmt->bind_param("ii", $currentUser['id'], $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Anda sudah terdaftar dalam kegiatan ini');
        }
        
        // Check if quota is full
        if ($activity['peserta_terdaftar'] >= $activity['kuota']) {
            throw new Exception('Kuota kegiatan sudah penuh');
        }
        
        // Register student
        $stmt = $db->prepare("INSERT INTO registrations (student_id, activity_id, keterangan) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $currentUser['id'], $activityId, $keterangan);
        
        if ($stmt->execute()) {
            // Update participant count
            $stmt = $db->prepare("UPDATE extracurricular_activities SET peserta_terdaftar = peserta_terdaftar + 1 WHERE id = ?");
            $stmt->bind_param("i", $activityId);
            $stmt->execute();
            
            $message = 'Pendaftaran berhasil! Menunggu persetujuan admin.';
            $messageType = 'success';
        } else {
            throw new Exception('Gagal menyimpan pendaftaran');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Handle unregister
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister'])) {
    $registrationId = (int)$_POST['registration_id'];
    
    try {
        // Get registration details
        $stmt = $db->prepare("SELECT r.*, a.nama_kegiatan FROM registrations r 
                             JOIN extracurricular_activities a ON r.activity_id = a.id 
                             WHERE r.id = ? AND r.student_id = ?");
        $stmt->bind_param("ii", $registrationId, $currentUser['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Pendaftaran tidak ditemukan');
        }
        
        $registration = $result->fetch_assoc();
        
        // Delete registration
        $stmt = $db->prepare("DELETE FROM registrations WHERE id = ? AND student_id = ?");
        $stmt->bind_param("ii", $registrationId, $currentUser['id']);
        
        if ($stmt->execute()) {
            // Update participant count
            $stmt = $db->prepare("UPDATE extracurricular_activities SET peserta_terdaftar = peserta_terdaftar - 1 WHERE id = ?");
            $stmt->bind_param("i", $registration['activity_id']);
            $stmt->execute();
            
            $message = 'Pendaftaran berhasil dibatalkan.';
            $messageType = 'success';
        } else {
            throw new Exception('Gagal membatalkan pendaftaran');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get open activities
$openActivities = [];
$stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE status_pendaftaran = 'open' ORDER BY nama_kegiatan");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $openActivities[] = $row;
}

// Get student's registrations
$myRegistrations = [];
$stmt = $db->prepare("SELECT r.*, a.nama_kegiatan, a.deskripsi, a.whatsapp_link, a.gambar, a.tanggal_mulai, a.tempat 
                     FROM registrations r 
                     JOIN extracurricular_activities a ON r.activity_id = a.id 
                     WHERE r.student_id = ? 
                     ORDER BY r.tanggal_daftar DESC");
$stmt->bind_param("i", $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $myRegistrations[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Ekstrakurikuler - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/images/unsrat-logo.png">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="assets/images/unsrat-logo.png" alt="UNSRAT Logo" onerror="this.style.display='none'">
                <div>
                    <h1><?php echo SITE_NAME; ?></h1>
                    <p style="font-size: 0.9rem; opacity: 0.9;">Universitas Sam Ratulangi</p>
                </div>
            </div>
            <nav class="header-nav">
                <span style="margin-right: 1rem;">Halo, <?php echo sanitize($currentUser['nama']); ?></span>
                <a href="index.php" class="btn btn-outline">Beranda</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="section-title">
                <h2>Pendaftaran Ekstrakurikuler</h2>
                <p>Kelola pendaftaran ekstrakurikuler Anda</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <button type="button" class="nav-tab active" onclick="showTab('register')">Daftar Kegiatan</button>
                <button type="button" class="nav-tab" onclick="showTab('my-registrations')">Pendaftaran Saya</button>
            </div>

            <!-- Register Tab -->
            <div id="register-tab" class="tab-content">
                <?php if (!empty($openActivities)): ?>
                    <div class="activities-grid">
                        <?php foreach ($openActivities as $activity): ?>
                        <?php
                        // Check if student is already registered
                        $isRegistered = false;
                        foreach ($myRegistrations as $reg) {
                            if ($reg['activity_id'] == $activity['id']) {
                                $isRegistered = true;
                                break;
                            }
                        }
                        ?>
                        <div class="activity-card">
                            <img src="<?php echo getImagePath($activity['gambar']); ?>" 
                                 alt="<?php echo sanitize($activity['nama_kegiatan']); ?>" 
                                 class="activity-image"
                                 onerror="this.src='<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                            
                            <div class="activity-content">
                                <h4 class="activity-title"><?php echo sanitize($activity['nama_kegiatan']); ?></h4>
                                <p class="activity-description"><?php echo sanitize($activity['deskripsi']); ?></p>
                                
                                <div class="activity-meta">
                                    <div class="activity-quota">
                                        <span>👥</span>
                                        <span><?php echo $activity['peserta_terdaftar']; ?>/<?php echo $activity['kuota']; ?> peserta</span>
                                    </div>
                                    <span class="activity-status status-open">Terbuka</span>
                                </div>
                                
                                <?php if (!empty($activity['tanggal_mulai'])): ?>
                                <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #6b7280;">
                                    📅 Mulai: <?php echo formatDate($activity['tanggal_mulai']); ?>
                                    <?php if (!empty($activity['tempat'])): ?>
                                        <br>📍 <?php echo sanitize($activity['tempat']);?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="activity-actions">
                                    <?php if ($isRegistered): ?>
                                        <span class="btn btn-secondary" style="cursor: not-allowed;">Sudah Terdaftar</span>
                                    <?php elseif ($activity['peserta_terdaftar'] >= $activity['kuota']): ?>
                                        <span class="btn btn-secondary" style="cursor: not-allowed;">Kuota Penuh</span>
                                    <?php else: ?>
                                        <button onclick="openRegisterModal(<?php echo $activity['id']; ?>, '<?php echo addslashes($activity['nama_kegiatan']); ?>')" 
                                                class="btn btn-success">Daftar</button>
                                    <?php endif; ?>
                                    <button onclick="showActivityDetails(<?php echo $activity['id']; ?>)" 
                                            class="btn btn-primary">Detail</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 3rem 0;">
                        <h3 style="color: #6b7280; margin-bottom: 1rem;">Tidak Ada Kegiatan Terbuka</h3>
                        <p style="color: #9ca3af;">Saat ini belum ada kegiatan ekstrakurikuler yang membuka pendaftaran.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Registrations Tab -->
            <div id="my-registrations-tab" class="tab-content" style="display: none;">
                <?php if (!empty($myRegistrations)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kegiatan</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Status</th>
                                    <th>WhatsApp Group</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myRegistrations as $registration): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <img src="<?php echo getImagePath($registration['gambar']); ?>" 
                                                 alt="<?php echo sanitize($registration['nama_kegiatan']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                                 onerror="this.src='<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                                            <div>
                                                <strong><?php echo sanitize($registration['nama_kegiatan']); ?></strong>
                                                <?php if (!empty($registration['tanggal_mulai'])): ?>
                                                    <br><small style="color: #6b7280;">Mulai: <?php echo formatDate($registration['tanggal_mulai']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatDateTime($registration['tanggal_daftar']); ?></td>
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
                                                $statusText = 'Diterima';
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
                                        <?php if ($registration['status'] === 'approved' && !empty($registration['whatsapp_link'])): ?>
                                            <a href="<?php echo sanitize($registration['whatsapp_link']); ?>" 
                                               target="_blank" class="btn btn-success btn-sm">Join Group</a>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($registration['status'] === 'pending'): ?>
                                            <button onclick="confirmUnregister(<?php echo $registration['id']; ?>, '<?php echo addslashes($registration['nama_kegiatan']); ?>')" 
                                                    class="btn btn-danger btn-sm">Batalkan</button>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 3rem 0;">
                        <h3 style="color: #6b7280; margin-bottom: 1rem;">Belum Ada Pendaftaran</h3>
                        <p style="color: #9ca3af;">Anda belum mendaftar ke kegiatan ekstrakurikuler manapun.</p>
                        <button onclick="showTab('register')" class="btn btn-primary mt-2">Mulai Daftar</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Registration Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Daftar Kegiatan</h3>
                <button class="modal-close" onclick="closeModal('registerModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="activity_id" id="modalActivityId">
                <div class="form-group">
                    <label class="form-label">Kegiatan</label>
                    <input type="text" id="modalActivityName" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label for="keterangan" class="form-label">Alasan Mendaftar (Opsional)</label>
                    <textarea name="keterangan" id="keterangan" class="form-textarea" 
                              placeholder="Ceritakan mengapa Anda tertarik dengan kegiatan ini..."></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="register" class="btn btn-success w-full">Daftar Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Details Modal -->
    <div id="activityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="activityModalTitle">Detail Kegiatan</h3>
                <button class="modal-close" onclick="closeModal('activityModal')">&times;</button>
            </div>
            <div id="activityModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Unregister Form -->
    <form id="unregisterForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="registration_id" id="unregisterRegistrationId">
        <input type="hidden" name="unregister" value="1">
    </form>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Universitas Sam Ratulangi.</p>
        </div>
    </footer>

    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            if (tabName === 'register') {
                document.getElementById('register-tab').style.display = 'block';
                document.querySelectorAll('.nav-tab')[0].classList.add('active');
            } else if (tabName === 'my-registrations') {
                document.getElementById('my-registrations-tab').style.display = 'block';
                document.querySelectorAll('.nav-tab')[1].classList.add('active');
            }
        }

        // Modal functions
        function openRegisterModal(activityId, activityName) {
            document.getElementById('modalActivityId').value = activityId;
            document.getElementById('modalActivityName').value = activityName;
            document.getElementById('keterangan').value = '';
            document.getElementById('registerModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function confirmUnregister(registrationId, activityName) {
            if (confirm(`Apakah Anda yakin ingin membatalkan pendaftaran "${activityName}"?`)) {
                document.getElementById('unregisterRegistrationId').value = registrationId;
                document.getElementById('unregisterForm').submit();
            }
        }

        // Activity details modal (same as in index.php)
        function showActivityDetails(activityId) {
            fetch(`get_activity_details.php?id=${activityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('activityModalTitle').textContent = data.activity.nama_kegiatan;
                        document.getElementById('activityModalBody').innerHTML = `
                            <div style="margin-bottom: 1rem;">
                                <img src="${data.activity.image_path}" alt="${data.activity.nama_kegiatan}" 
                                     style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">
                            </div>
                            <p><strong>Deskripsi:</strong><br>${data.activity.deskripsi}</p>
                            <p><strong>Kuota:</strong> ${data.activity.peserta_terdaftar}/${data.activity.kuota} peserta</p>
                            <p><strong>Status:</strong> <span class="activity-status ${data.activity.status_pendaftaran === 'open' ? 'status-open' : 'status-closed'}">${data.activity.status_pendaftaran === 'open' ? 'Terbuka' : 'Ditutup'}</span></p>
                            ${data.activity.tanggal_mulai ? `<p><strong>Tanggal Mulai:</strong> ${data.activity.tanggal_mulai_formatted}</p>` : ''}
                            ${data.activity.tanggal_selesai ? `<p><strong>Tanggal Selesai:</strong> ${data.activity.tanggal_selesai_formatted}</p>` : ''}
                            ${data.activity.tempat ? `<p><strong>Tempat:</strong> ${data.activity.tempat}</p>` : ''}
                            ${data.activity.pembimbing ? `<p><strong>Pembimbing:</strong> ${data.activity.pembimbing}</p>` : ''}
                            <p><strong>Kategori:</strong> ${data.activity.kategori}</p>
                        `;
                        document.getElementById('activityModal').classList.add('show');
                    } else {
                        alert('Gagal memuat detail kegiatan');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail');
                });
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Check for selected activity on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activityId = urlParams.get('activity');
            if (activityId) {
                // Find the activity and open register modal
                const activityCards = document.querySelectorAll('.activity-card');
                activityCards.forEach(card => {
                    const registerButton = card.querySelector('[onclick*="openRegisterModal(' + activityId + '"]');
                    if (registerButton) {
                        registerButton.click();
                    }
                });
            }
        });
    </script>
</body>
</html>