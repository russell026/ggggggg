<?php
require_once 'config.php';

// Get activities data
$openActivities = [];
$closedActivities = [];

try {
    // Get open activities
    $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE status_pendaftaran = 'open' ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $openActivities[] = $row;
    }
    
    // Get closed activities
    $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE status_pendaftaran = 'closed' ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $closedActivities[] = $row;
    }
} catch (Exception $e) {
    showAlert('Terjadi kesalahan saat memuat data: ' . $e->getMessage(), 'error');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Universitas Sam Ratulangi</title>
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
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="btn btn-outline">Dashboard Admin</a>
                        <a href="logout.php" class="btn btn-secondary">Logout</a>
                    <?php else: ?>
                        <span style="margin-right: 1rem;">Halo, <?php echo sanitize(getCurrentUser()['nama']); ?></span>
                        <a href="registration.php" class="btn btn-outline">Pendaftaran Saya</a>
                        <a href="logout.php" class="btn btn-secondary">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php displayAlert(); ?>
            
            <!-- Hero Section -->
            <div class="section-title">
                <h2>Ekstrakurikuler UNSRAT</h2>
                <p>Bergabunglah dengan berbagai kegiatan ekstrakurikuler untuk mengembangkan bakat dan minat Anda</p>
            </div>

            <!-- Open Activities Section -->
            <?php if (!empty($openActivities)): ?>
            <div class="activities-section">
                <h3 style="color: #059669; font-size: 1.8rem; margin-bottom: 1.5rem; text-align: center;">
                    🟢 Pendaftaran Terbuka
                </h3>
                <div class="activities-grid">
                    <?php foreach ($openActivities as $activity): ?>
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
                                <?php if ($activity['peserta_terdaftar'] < $activity['kuota']): ?>
                                    <?php if (isStudent()): ?>
                                        <a href="registration.php?activity=<?php echo $activity['id']; ?>" 
                                           class="btn btn-success">Daftar Sekarang</a>
                                    <?php else: ?>
                                        <a href="login.php?redirect=registration.php&activity=<?php echo $activity['id']; ?>" 
                                           class="btn btn-success">Daftar Sekarang</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="btn btn-secondary" style="cursor: not-allowed;">Kuota Penuh</span>
                                <?php endif; ?>
                                <button onclick="showActivityDetails(<?php echo $activity['id']; ?>)" 
                                        class="btn btn-primary">Detail</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Closed Activities Section -->
            <?php if (!empty($closedActivities)): ?>
            <div class="activities-section">
                <h3 style="color: #ef4444; font-size: 1.8rem; margin-bottom: 1.5rem; text-align: center;">
                    🔴 Pendaftaran Ditutup
                </h3>
                <div class="activities-grid">
                    <?php foreach ($closedActivities as $activity): ?>
                    <div class="activity-card" style="opacity: 0.8;">
                        <div style="position: relative;">
                            <img src="<?php echo getImagePath($activity['gambar']); ?>" 
                                 alt="<?php echo sanitize($activity['nama_kegiatan']); ?>" 
                                 class="activity-image"
                                 onerror="this.src='<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                            <div class="closed-overlay">
                                PENDAFTARAN DITUTUP
                            </div>
                        </div>
                        
                        <div class="activity-content">
                            <h4 class="activity-title"><?php echo sanitize($activity['nama_kegiatan']); ?></h4>
                            <p class="activity-description"><?php echo sanitize($activity['deskripsi']); ?></p>
                            
                            <div class="activity-meta">
                                <div class="activity-quota">
                                    <span>👥</span>
                                    <span><?php echo $activity['peserta_terdaftar']; ?>/<?php echo $activity['kuota']; ?> peserta</span>
                                </div>
                                <span class="activity-status status-closed">Ditutup</span>
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
                                <button onclick="showActivityDetails(<?php echo $activity['id']; ?>)" 
                                        class="btn btn-primary">Detail</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- No Activities Message -->
            <?php if (empty($openActivities) && empty($closedActivities)): ?>
            <div class="text-center" style="padding: 3rem 0;">
                <h3 style="color: #6b7280; margin-bottom: 1rem;">Belum Ada Kegiatan Ekstrakurikuler</h3>
                <p style="color: #9ca3af;">Kegiatan ekstrakurikuler akan ditampilkan di sini setelah admin menambahkannya.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Activity Details Modal -->
    <div id="activityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Detail Kegiatan</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Universitas Sam Ratulangi.</p>
            <p style="font-size: 0.9rem; opacity: 0.8; margin-top: 0.5rem;">
                Jl. Kampus Unsrat Bahu, Manado 95115, Sulawesi Utara
            </p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
        // Activity details modal
        function showActivityDetails(activityId) {
            fetch(`get_activity_details.php?id=${activityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = data.activity.nama_kegiatan;
                        document.getElementById('modalBody').innerHTML = `
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

        function closeModal() {
            document.getElementById('activityModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('activityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>