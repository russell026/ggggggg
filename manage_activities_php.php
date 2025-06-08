<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('../login.php?type=admin');
}

$currentAdmin = getCurrentUser();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$activityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_activity'])) {
        // Add new activity
        try {
            $nama_kegiatan = sanitize($_POST['nama_kegiatan']);
            $deskripsi = sanitize($_POST['deskripsi']);
            $kuota = (int)$_POST['kuota'];
            $status_pendaftaran = $_POST['status_pendaftaran'];
            $whatsapp_link = sanitize($_POST['whatsapp_link']);
            $tanggal_mulai = $_POST['tanggal_mulai'] ?: null;
            $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;
            $tempat = sanitize($_POST['tempat']);
            $pembimbing = sanitize($_POST['pembimbing']);
            $kategori = $_POST['kategori'];
            
            // Handle image upload
            $gambar = 'default-activity.jpg';
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
                $uploadedImage = uploadImage($_FILES['gambar']);
                if ($uploadedImage) {
                    $gambar = $uploadedImage;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO extracurricular_activities (nama_kegiatan, deskripsi, kuota, status_pendaftaran, gambar, whatsapp_link, tanggal_mulai, tanggal_selesai, tempat, pembimbing, kategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssssss", $nama_kegiatan, $deskripsi, $kuota, $status_pendaftaran, $gambar, $whatsapp_link, $tanggal_mulai, $tanggal_selesai, $tempat, $pembimbing, $kategori);
            
            if ($stmt->execute()) {
                $message = 'Kegiatan berhasil ditambahkan!';
                $messageType = 'success';
                $action = 'list';
            } else {
                throw new Exception('Gagal menyimpan kegiatan');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['edit_activity'])) {
        // Edit activity
        try {
            $id = (int)$_POST['id'];
            $nama_kegiatan = sanitize($_POST['nama_kegiatan']);
            $deskripsi = sanitize($_POST['deskripsi']);
            $kuota = (int)$_POST['kuota'];
            $status_pendaftaran = $_POST['status_pendaftaran'];
            $whatsapp_link = sanitize($_POST['whatsapp_link']);
            $tanggal_mulai = $_POST['tanggal_mulai'] ?: null;
            $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;
            $tempat = sanitize($_POST['tempat']);
            $pembimbing = sanitize($_POST['pembimbing']);
            $kategori = $_POST['kategori'];
            
            // Get current activity data
            $stmt = $db->prepare("SELECT gambar FROM extracurricular_activities WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentActivity = $result->fetch_assoc();
            $gambar = $currentActivity['gambar'];
            
            // Handle image upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
                $uploadedImage = uploadImage($_FILES['gambar']);
                if ($uploadedImage) {
                    // Delete old image if it's not default
                    if ($gambar !== 'default-activity.jpg') {
                        deleteImage($gambar);
                    }
                    $gambar = $uploadedImage;
                }
            }
            
            $stmt = $db->prepare("UPDATE extracurricular_activities SET nama_kegiatan = ?, deskripsi = ?, kuota = ?, status_pendaftaran = ?, gambar = ?, whatsapp_link = ?, tanggal_mulai = ?, tanggal_selesai = ?, tempat = ?, pembimbing = ?, kategori = ? WHERE id = ?");
            $stmt->bind_param("ssissssssssi", $nama_kegiatan, $deskripsi, $kuota, $status_pendaftaran, $gambar, $whatsapp_link, $tanggal_mulai, $tanggal_selesai, $tempat, $pembimbing, $kategori, $id);
            
            if ($stmt->execute()) {
                $message = 'Kegiatan berhasil diperbarui!';
                $messageType = 'success';
                $action = 'list';
            } else {
                throw new Exception('Gagal memperbarui kegiatan');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['delete_activity'])) {
        // Delete activity
        try {
            $id = (int)$_POST['id'];
            
            // Get activity data
            $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $activity = $result->fetch_assoc();
            
            if (!$activity) {
                throw new Exception('Kegiatan tidak ditemukan');
            }
            
            // Delete related registrations first
            $stmt = $db->prepare("DELETE FROM registrations WHERE activity_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            // Delete activity
            $stmt = $db->prepare("DELETE FROM extracurricular_activities WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Delete image if it's not default
                if ($activity['gambar'] !== 'default-activity.jpg') {
                    deleteImage($activity['gambar']);
                }
                
                $message = 'Kegiatan berhasil dihapus!';
                $messageType = 'success';
            } else {
                throw new Exception('Gagal menghapus kegiatan');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get activities for list view
$activities = [];
if ($action === 'list') {
    try {
        $stmt = $db->prepare("SELECT * FROM extracurricular_activities ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    } catch (Exception $e) {
        $message = 'Terjadi kesalahan saat memuat data: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get activity data for edit
$editActivity = null;
if ($action === 'edit' && $activityId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM extracurricular_activities WHERE id = ?");
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editActivity = $result->fetch_assoc();
        
        if (!$editActivity) {
            $action = 'list';
            $message = 'Kegiatan tidak ditemukan';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $action = 'list';
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kegiatan - <?php echo SITE_NAME; ?></title>
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
                    <p style="font-size: 0.9rem; opacity: 0.9;">Kelola Kegiatan</p>
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
                <a href="manage-activities.php" class="btn btn-primary">Kelola Kegiatan</a>
                <a href="manage-students.php" class="btn btn-secondary">Kelola Mahasiswa</a>
                <a href="manage-registrations.php" class="btn btn-secondary">Kelola Pendaftaran</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
            <!-- List Activities -->
            <div class="section-title">
                <h2>Kelola Kegiatan Ekstrakurikuler</h2>
                <p>Tambah, edit, atau hapus kegiatan ekstrakurikuler</p>
            </div>

            <div style="margin-bottom: 2rem;">
                <a href="?action=add" class="btn btn-success">Tambah Kegiatan Baru</a>
            </div>

            <?php if (!empty($activities)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Nama Kegiatan</th>
                            <th>Kuota</th>
                            <th>Status</th>
                            <th>Kategori</th>
                            <th>Tanggal Mulai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td>
                                <img src="../<?php echo getImagePath($activity['gambar']); ?>" 
                                     alt="<?php echo sanitize($activity['nama_kegiatan']); ?>"
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                     onerror="this.src='../<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                            </td>
                            <td>
                                <strong><?php echo sanitize($activity['nama_kegiatan']); ?></strong>
                                <?php if (!empty($activity['tempat'])): ?>
                                    <br><small style="color: #6b7280;">📍 <?php echo sanitize($activity['tempat']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: #059669; font-weight: 500;">
                                    <?php echo $activity['peserta_terdaftar']; ?>/<?php echo $activity['kuota']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="activity-status <?php echo $activity['status_pendaftaran'] === 'open' ? 'status-open' : 'status-closed'; ?>">
                                    <?php echo $activity['status_pendaftaran'] === 'open' ? 'Terbuka' : 'Ditutup'; ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($activity['kategori']); ?></td>
                            <td>
                                <?php echo !empty($activity['tanggal_mulai']) ? formatDate($activity['tanggal_mulai']) : '-'; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <a href="?action=edit&id=<?php echo $activity['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <button onclick="deleteActivity(<?php echo $activity['id']; ?>, '<?php echo addslashes($activity['nama_kegiatan']); ?>')" 
                                            class="btn btn-danger btn-sm">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center" style="padding: 3rem 0;">
                <h3 style="color: #6b7280; margin-bottom: 1rem;">Belum Ada Kegiatan</h3>
                <p style="color: #9ca3af;">Mulai dengan menambahkan kegiatan ekstrakurikuler pertama.</p>
                <a href="?action=add" class="btn btn-primary mt-2">Tambah Kegiatan</a>
            </div>
            <?php endif; ?>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Activity Form -->
            <div class="section-title">
                <h2><?php echo $action === 'add' ? 'Tambah' : 'Edit'; ?> Kegiatan Ekstrakurikuler</h2>
                <p><?php echo $action === 'add' ? 'Buat kegiatan ekstrakurikuler baru' : 'Perbarui informasi kegiatan'; ?></p>
            </div>

            <div style="margin-bottom: 2rem;">
                <a href="?action=list" class="btn btn-secondary">← Kembali ke Daftar</a>
            </div>

            <div class="form-container" style="max-width: 800px;">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $editActivity['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nama_kegiatan" class="form-label">Nama Kegiatan *</label>
                        <input type="text" id="nama_kegiatan" name="nama_kegiatan" class="form-input" 
                               value="<?php echo $editActivity ? sanitize($editActivity['nama_kegiatan']) : ''; ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="4" 
                                  placeholder="Jelaskan detail kegiatan, tujuan, dan manfaatnya..."><?php echo $editActivity ? sanitize($editActivity['deskripsi']) : ''; ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="kuota" class="form-label">Kuota Peserta *</label>
                            <input type="number" id="kuota" name="kuota" class="form-input" min="1" 
                                   value="<?php echo $editActivity ? $editActivity['kuota'] : '20'; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="status_pendaftaran" class="form-label">Status Pendaftaran</label>
                            <select id="status_pendaftaran" name="status_pendaftaran" class="form-select">
                                <option value="open" <?php echo ($editActivity && $editActivity['status_pendaftaran'] === 'open') ? 'selected' : ''; ?>>Terbuka</option>
                                <option value="closed" <?php echo ($editActivity && $editActivity['status_pendaftaran'] === 'closed') ? 'selected' : ''; ?>>Ditutup</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input" 
                                   value="<?php echo $editActivity ? $editActivity['tanggal_mulai'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                            <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input" 
                                   value="<?php echo $editActivity ? $editActivity['tanggal_selesai'] : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tempat" class="form-label">Tempat Kegiatan</label>
                        <input type="text" id="tempat" name="tempat" class="form-input" 
                               placeholder="Contoh: GOR UNSRAT, Aula Utama, dll"
                               value="<?php echo $editActivity ? sanitize($editActivity['tempat']) : ''; ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="pembimbing" class="form-label">Pembimbing</label>
                            <input type="text" id="pembimbing" name="pembimbing" class="form-input" 
                                   placeholder="Nama dosen/staff pembimbing"
                                   value="<?php echo $editActivity ? sanitize($editActivity['pembimbing']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="kategori" class="form-label">Kategori</label>
                            <select id="kategori" name="kategori" class="form-select">
                                <option value="akademik" <?php echo ($editActivity && $editActivity['kategori'] === 'akademik') ? 'selected' : ''; ?>>Akademik</option>
                                <option value="olahraga" <?php echo ($editActivity && $editActivity['kategori'] === 'olahraga') ? 'selected' : ''; ?>>Olahraga</option>
                                <option value="seni" <?php echo ($editActivity && $editActivity['kategori'] === 'seni') ? 'selected' : ''; ?>>Seni</option>
                                <option value="organisasi" <?php echo ($editActivity && $editActivity['kategori'] === 'organisasi') ? 'selected' : ''; ?>>Organisasi</option>
                                <option value="lainnya" <?php echo ($editActivity && $editActivity['kategori'] === 'lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="whatsapp_link" class="form-label">Link WhatsApp Group</label>
                        <input type="url" id="whatsapp_link" name="whatsapp_link" class="form-input" 
                               placeholder="https://chat.whatsapp.com/..."
                               value="<?php echo $editActivity ? sanitize($editActivity['whatsapp_link']) : ''; ?>">
                        <small style="color: #6b7280;">Link grup WhatsApp untuk peserta yang diterima</small>
                    </div>

                    <div class="form-group">
                        <label for="gambar" class="form-label">Gambar Kegiatan</label>
                        <input type="file" id="gambar" name="gambar" class="form-input" 
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <small style="color: #6b7280;">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                        
                        <?php if ($editActivity && !empty($editActivity['gambar'])): ?>
                        <div style="margin-top: 1rem;">
                            <p style="font-weight: 500; margin-bottom: 0.5rem;">Gambar Saat Ini:</p>
                            <img src="../<?php echo getImagePath($editActivity['gambar']); ?>" 
                                 alt="Current image" class="current-image"
                                 onerror="this.src='../<?php echo DEFAULT_ACTIVITY_IMAGE; ?>'">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="<?php echo $action === 'add' ? 'add_activity' : 'edit_activity'; ?>" 
                                class="btn btn-success w-full">
                            <?php echo $action === 'add' ? 'Tambah' : 'Update'; ?> Kegiatan
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="id" id="deleteActivityId">
        <input type="hidden" name="delete_activity" value="1">
    </form>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Dashboard Admin.</p>
        </div>
    </footer>

    <script>
        function deleteActivity(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus kegiatan "${name}"?\n\nSemua data pendaftaran terkait juga akan dihapus!`)) {
                document.getElementById('deleteActivityId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[enctype="multipart/form-data"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const namaKegiatan = document.getElementById('nama_kegiatan').value.trim();
                    const kuota = parseInt(document.getElementById('kuota').value);
                    
                    if (!namaKegiatan) {
                        e.preventDefault();
                        alert('Nama kegiatan harus diisi');
                        return;
                    }
                    
                    if (kuota < 1) {
                        e.preventDefault();
                        alert('Kuota peserta minimal 1');
                        return;
                    }
                    
                    // Check file size if uploading
                    const fileInput = document.getElementById('gambar');
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        if (file.size > 5 * 1024 * 1024) { // 5MB
                            e.preventDefault();
                            alert('Ukuran file maksimal 5MB');
                            return;
                        }
                    }
                });
            }
        });

        // Preview image
        document.getElementById('gambar')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.querySelector('.image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'image-preview';
                        preview.style.marginTop = '1rem';
                        e.target.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = `
                        <p style="font-weight: 500; margin-bottom: 0.5rem;">Preview:</p>
                        <img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>