<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('../login.php?type=admin');
}

$currentAdmin = getCurrentUser();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        // Add new student
        try {
            $nim = sanitize($_POST['nim']);
            $nama = sanitize($_POST['nama']);
            $email = sanitize($_POST['email']);
            $fakultas = sanitize($_POST['fakultas']);
            $jurusan = sanitize($_POST['jurusan']);
            $semester = (int)$_POST['semester'];
            $telepon = sanitize($_POST['telepon']);
            $password = hashPassword($_POST['password']);
            
            // Check if NIM already exists
            $stmt = $db->prepare("SELECT id FROM students WHERE nim = ?");
            $stmt->bind_param("s", $nim);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('NIM sudah terdaftar');
            }
            
            $stmt = $db->prepare("INSERT INTO students (nim, password, nama, email, fakultas, jurusan, semester, telepon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssiss", $nim, $password, $nama, $email, $fakultas, $jurusan, $semester, $telepon);
            
            if ($stmt->execute()) {
                $message = 'Mahasiswa berhasil ditambahkan!';
                $messageType = 'success';
                $action = 'list';
            } else {
                throw new Exception('Gagal menyimpan data mahasiswa');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['edit_student'])) {
        // Edit student
        try {
            $id = (int)$_POST['id'];
            $nim = sanitize($_POST['nim']);
            $nama = sanitize($_POST['nama']);
            $email = sanitize($_POST['email']);
            $fakultas = sanitize($_POST['fakultas']);
            $jurusan = sanitize($_POST['jurusan']);
            $semester = (int)$_POST['semester'];
            $telepon = sanitize($_POST['telepon']);
            
            // Check if NIM already exists for other students
            $stmt = $db->prepare("SELECT id FROM students WHERE nim = ? AND id != ?");
            $stmt->bind_param("si", $nim, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('NIM sudah digunakan oleh mahasiswa lain');
            }
            
            $sql = "UPDATE students SET nim = ?, nama = ?, email = ?, fakultas = ?, jurusan = ?, semester = ?, telepon = ?";
            $params = [$nim, $nama, $email, $fakultas, $jurusan, $semester, $telepon];
            $types = "sssssiss";
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = hashPassword($_POST['password']);
                $sql .= ", password = ?";
                $params[] = $password;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $message = 'Data mahasiswa berhasil diperbarui!';
                $messageType = 'success';
                $action = 'list';
            } else {
                throw new Exception('Gagal memperbarui data mahasiswa');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['delete_student'])) {
        // Delete student
        try {
            $id = (int)$_POST['id'];
            
            // Check if student exists
            $stmt = $db->prepare("SELECT nama FROM students WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Mahasiswa tidak ditemukan');
            }
            
            // Delete related registrations first
            $stmt = $db->prepare("DELETE FROM registrations WHERE student_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            // Delete student
            $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Mahasiswa berhasil dihapus!';
                $messageType = 'success';
            } else {
                throw new Exception('Gagal menghapus mahasiswa');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get students for list view
$students = [];
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$fakultasFilter = isset($_GET['fakultas']) ? sanitize($_GET['fakultas']) : '';

if ($action === 'list') {
    try {
        $sql = "SELECT s.*, COUNT(r.id) as total_registrations 
                FROM students s 
                LEFT JOIN registrations r ON s.id = r.student_id";
        $params = [];
        $types = "";
        
        $whereConditions = [];
        
        if (!empty($searchQuery)) {
            $whereConditions[] = "(s.nim LIKE ? OR s.nama LIKE ? OR s.email LIKE ?)";
            $searchTerm = "%$searchQuery%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types .= "sss";
        }
        
        if (!empty($fakultasFilter)) {
            $whereConditions[] = "s.fakultas = ?";
            $params[] = $fakultasFilter;
            $types .= "s";
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
        
        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } catch (Exception $e) {
        $message = 'Terjadi kesalahan saat memuat data: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get fakultas list for filter
$fakultasList = [];
try {
    $result = $db->query("SELECT DISTINCT fakultas FROM students WHERE fakultas IS NOT NULL AND fakultas != '' ORDER BY fakultas");
    while ($row = $result->fetch_assoc()) {
        $fakultasList[] = $row['fakultas'];
    }
} catch (Exception $e) {
    // Silent error
}

// Get student data for edit
$editStudent = null;
if ($action === 'edit' && $studentId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editStudent = $result->fetch_assoc();
        
        if (!$editStudent) {
            $action = 'list';
            $message = 'Mahasiswa tidak ditemukan';
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
    <title>Kelola Mahasiswa - <?php echo SITE_NAME; ?></title>
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
                    <p style="font-size: 0.9rem; opacity: 0.9;">Kelola Mahasiswa</p>
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
                <a href="manage-students.php" class="btn btn-primary">Kelola Mahasiswa</a>
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
            <!-- List Students -->
            <div class="section-title">
                <h2>Kelola Data Mahasiswa</h2>
                <p>Tambah, edit, atau hapus data mahasiswa</p>
            </div>

            <!-- Search and Filter -->
            <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 2rem;">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                    <input type="hidden" name="action" value="list">
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Cari Mahasiswa</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Cari berdasarkan NIM, nama, atau email..."
                               value="<?php echo sanitize($searchQuery); ?>">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Fakultas</label>
                        <select name="fakultas" class="form-select">
                            <option value="">Semua Fakultas</option>
                            <?php foreach ($fakultasList as $fakultas): ?>
                                <option value="<?php echo sanitize($fakultas); ?>" 
                                        <?php echo $fakultasFilter === $fakultas ? 'selected' : ''; ?>>
                                    <?php echo sanitize($fakultas); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Cari</button>
                        <a href="?action=list" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div style="margin-bottom: 2rem;">
                <a href="?action=add" class="btn btn-success">Tambah Mahasiswa Baru</a>
                <span style="margin-left: 1rem; color: #6b7280;">
                    Total: <?php echo count($students); ?> mahasiswa
                </span>
            </div>

            <?php if (!empty($students)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Fakultas/Jurusan</th>
                            <th>Semester</th>
                            <th>Email</th>
                            <th>Pendaftaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($student['nim']); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo sanitize($student['nama']); ?></strong>
                                <?php if (!empty($student['telepon'])): ?>
                                    <br><small style="color: #6b7280;">📞 <?php echo sanitize($student['telepon']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo sanitize($student['fakultas']); ?>
                                <?php if (!empty($student['jurusan'])): ?>
                                    <br><small style="color: #6b7280;"><?php echo sanitize($student['jurusan']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $student['semester'] > 0 ? 'Semester ' . $student['semester'] : '-'; ?>
                            </td>
                            <td>
                                <?php if (!empty($student['email'])): ?>
                                    <a href="mailto:<?php echo sanitize($student['email']); ?>" 
                                       style="color: #3b82f6;"><?php echo sanitize($student['email']); ?></a>
                                <?php else: ?>
                                    <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: #059669; font-weight: 500;">
                                    <?php echo $student['total_registrations']; ?> kegiatan
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <button onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo addslashes($student['nama']); ?>')" 
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
                <h3 style="color: #6b7280; margin-bottom: 1rem;">
                    <?php echo !empty($searchQuery) || !empty($fakultasFilter) ? 'Tidak Ada Hasil' : 'Belum Ada Mahasiswa'; ?>
                </h3>
                <p style="color: #9ca3af;">
                    <?php if (!empty($searchQuery) || !empty($fakultasFilter)): ?>
                        Tidak ditemukan mahasiswa yang sesuai dengan kriteria pencarian.
                    <?php else: ?>
                        Mulai dengan menambahkan data mahasiswa pertama.
                    <?php endif; ?>
                </p>
                <?php if (empty($searchQuery) && empty($fakultasFilter)): ?>
                    <a href="?action=add" class="btn btn-primary mt-2">Tambah Mahasiswa</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Student Form -->
            <div class="section-title">
                <h2><?php echo $action === 'add' ? 'Tambah' : 'Edit'; ?> Data Mahasiswa</h2>
                <p><?php echo $action === 'add' ? 'Buat akun mahasiswa baru' : 'Perbarui data mahasiswa'; ?></p>
            </div>

            <div style="margin-bottom: 2rem;">
                <a href="?action=list" class="btn btn-secondary">← Kembali ke Daftar</a>
            </div>

            <div class="form-container" style="max-width: 600px;">
                <form method="POST" action="">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $editStudent['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nim" class="form-label">NIM (Nomor Induk Mahasiswa) *</label>
                        <input type="text" id="nim" name="nim" class="form-input" 
                               value="<?php echo $editStudent ? sanitize($editStudent['nim']) : ''; ?>" 
                               placeholder="Contoh: 220212060374" required>
                        <small style="color: #6b7280;">NIM akan digunakan sebagai username untuk login</small>
                    </div>

                    <div class="form-group">
                        <label for="nama" class="form-label">Nama Lengkap *</label>
                        <input type="text" id="nama" name="nama" class="form-input" 
                               value="<?php echo $editStudent ? sanitize($editStudent['nama']) : ''; ?>" 
                               placeholder="Nama lengkap mahasiswa" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo $editStudent ? sanitize($editStudent['email']) : ''; ?>" 
                               placeholder="email@student.unsrat.ac.id">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="fakultas" class="form-label">Fakultas</label>
                            <select id="fakultas" name="fakultas" class="form-select">
                                <option value="">Pilih Fakultas</option>
                                <option value="MIPA" <?php echo ($editStudent && $editStudent['fakultas'] === 'MIPA') ? 'selected' : ''; ?>>MIPA</option>
                                <option value="Teknik" <?php echo ($editStudent && $editStudent['fakultas'] === 'Teknik') ? 'selected' : ''; ?>>Teknik</option>
                                <option value="Pertanian" <?php echo ($editStudent && $editStudent['fakultas'] === 'Pertanian') ? 'selected' : ''; ?>>Pertanian</option>
                                <option value="Peternakan" <?php echo ($editStudent && $editStudent['fakultas'] === 'Peternakan') ? 'selected' : ''; ?>>Peternakan</option>
                                <option value="Kedokteran" <?php echo ($editStudent && $editStudent['fakultas'] === 'Kedokteran') ? 'selected' : ''; ?>>Kedokteran</option>
                                <option value="Ekonomi dan Bisnis" <?php echo ($editStudent && $editStudent['fakultas'] === 'Ekonomi dan Bisnis') ? 'selected' : ''; ?>>Ekonomi dan Bisnis</option>
                                <option value="Hukum" <?php echo ($editStudent && $editStudent['fakultas'] === 'Hukum') ? 'selected' : ''; ?>>Hukum</option>
                                <option value="Ilmu Sosial dan Politik" <?php echo ($editStudent && $editStudent['fakultas'] === 'Ilmu Sosial dan Politik') ? 'selected' : ''; ?>>Ilmu Sosial dan Politik</option>
                                <option value="Sastra" <?php echo ($editStudent && $editStudent['fakultas'] === 'Sastra') ? 'selected' : ''; ?>>Sastra</option>
                                <option value="Kesehatan Masyarakat" <?php echo ($editStudent && $editStudent['fakultas'] === 'Kesehatan Masyarakat') ? 'selected' : ''; ?>>Kesehatan Masyarakat</option>
                                <option value="Kelautan dan Perikanan" <?php echo ($editStudent && $editStudent['fakultas'] === 'Kelautan dan Perikanan') ? 'selected' : ''; ?>>Kelautan dan Perikanan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="semester" class="form-label">Semester</label>
                            <select id="semester" name="semester" class="form-select">
                                <option value="0">Pilih Semester</option>
                                <?php for ($i = 1; $i <= 14; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($editStudent && $editStudent['semester'] == $i) ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="jurusan" class="form-label">Program Studi/Jurusan</label>
                        <input type="text" id="jurusan" name="jurusan" class="form-input" 
                               value="<?php echo $editStudent ? sanitize($editStudent['jurusan']) : ''; ?>" 
                               placeholder="Contoh: Teknik Informatika">
                    </div>

                    <div class="form-group">
                        <label for="telepon" class="form-label">Nomor Telepon</label>
                        <input type="tel" id="telepon" name="telepon" class="form-input" 
                               value="<?php echo $editStudent ? sanitize($editStudent['telepon']) : ''; ?>" 
                               placeholder="Contoh: 081234567890">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            Password <?php echo $action === 'edit' ? '(Kosongkan jika tidak ingin mengubah)' : '*'; ?>
                        </label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Masukkan password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                        <small style="color: #6b7280;">Password minimal 6 karakter</small>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="<?php echo $action === 'add' ? 'add_student' : 'edit_student'; ?>" 
                                class="btn btn-success w-full">
                            <?php echo $action === 'add' ? 'Tambah' : 'Update'; ?> Mahasiswa
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="id" id="deleteStudentId">
        <input type="hidden" name="delete_student" value="1">
    </form>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Dashboard Admin.</p>
        </div>
    </footer>

    <script>
        function deleteStudent(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus mahasiswa "${name}"?\n\nSemua data pendaftaran terkait juga akan dihapus!`)) {
                document.getElementById('deleteStudentId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (form && form.querySelector('input[name="nim"]')) {
                form.addEventListener('submit', function(e) {
                    const nim = document.getElementById('nim').value.trim();
                    const nama = document.getElementById('nama').value.trim();
                    const password = document.getElementById('password').value;
                    const isEdit = document.querySelector('input[name="edit_student"]');
                    
                    if (!nim) {
                        e.preventDefault();
                        alert('NIM harus diisi');
                        return;
                    }
                    
                    if (nim.length < 5) {
                        e.preventDefault();
                        alert('NIM minimal 5 karakter');
                        return;
                    }
                    
                    if (!nama) {
                        e.preventDefault();
                        alert('Nama lengkap harus diisi');
                        return;
                    }
                    
                    // Password validation for add form
                    if (!isEdit && password.length < 6) {
                        e.preventDefault();
                        alert('Password minimal 6 karakter');
                        return;
                    }
                    
                    // Password validation for edit form (if password is provided)
                    if (isEdit && password && password.length < 6) {
                        e.preventDefault();
                        alert('Password minimal 6 karakter');
                        return;
                    }
                });
            }
        });

        // Auto-format NIM input
        document.getElementById('nim')?.addEventListener('input', function(e) {
            // Remove non-numeric characters
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        // Auto-format phone input
        document.getElementById('telepon')?.addEventListener('input', function(e) {
            // Remove non-numeric characters
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>