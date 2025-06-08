<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('index.php');
    }
}

$error = '';
$loginType = isset($_GET['type']) ? $_GET['type'] : 'student';
$redirectUrl = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$activityId = isset($_GET['activity']) ? $_GET['activity'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $loginType = $_POST['login_type'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            if ($loginType === 'admin') {
                // Admin login
                $stmt = $db->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    if (verifyPassword($password, $admin['password'])) {
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_data'] = [
                            'id' => $admin['id'],
                            'username' => $admin['username'],
                            'nama' => $admin['nama'],
                            'email' => $admin['email']
                        ];
                        
                        showAlert('Login berhasil! Selamat datang, ' . $admin['nama'], 'success');
                        redirect('admin/dashboard.php');
                    } else {
                        $error = 'Username atau password admin salah';
                    }
                } else {
                    $error = 'Username atau password admin salah';
                }
            } else {
                // Student login
                $stmt = $db->prepare("SELECT * FROM students WHERE nim = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $student = $result->fetch_assoc();
                    if (verifyPassword($password, $student['password'])) {
                        $_SESSION['user_id'] = $student['id'];
                        $_SESSION['user_data'] = [
                            'id' => $student['id'],
                            'nim' => $student['nim'],
                            'nama' => $student['nama'],
                            'email' => $student['email'],
                            'fakultas' => $student['fakultas'],
                            'jurusan' => $student['jurusan']
                        ];
                        
                        showAlert('Login berhasil! Selamat datang, ' . $student['nama'], 'success');
                        
                        // Redirect based on parameters
                        if (!empty($redirectUrl)) {
                            $url = $redirectUrl;
                            if (!empty($activityId)) {
                                $url .= '?activity=' . $activityId;
                            }
                            redirect($url);
                        } else {
                            redirect('index.php');
                        }
                    } else {
                        $error = 'NIM atau password salah';
                    }
                } else {
                    $error = 'NIM atau password salah';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
                <a href="index.php" class="btn btn-outline">Kembali ke Beranda</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <div class="text-center mb-4">
                    <h2 style="color: #1e40af; margin-bottom: 0.5rem;">Login</h2>
                    <p style="color: #6b7280;">Masuk ke akun Anda untuk mendaftar ekstrakurikuler</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Login Type Tabs -->
                <div class="nav-tabs" style="margin-bottom: 2rem;">
                    <button type="button" class="nav-tab <?php echo $loginType === 'student' ? 'active' : ''; ?>" 
                            onclick="switchLoginType('student')">Mahasiswa</button>
                    <button type="button" class="nav-tab <?php echo $loginType === 'admin' ? 'active' : ''; ?>" 
                            onclick="switchLoginType('admin')">Admin</button>
                </div>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="login_type" id="loginType" value="<?php echo $loginType; ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label" id="usernameLabel">
                            <?php echo $loginType === 'admin' ? 'Username Admin' : 'NIM (Nomor Induk Mahasiswa)'; ?>
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               placeholder="<?php echo $loginType === 'admin' ? 'Masukkan username admin' : 'Contoh: 220212060374'; ?>"
                               value="<?php echo isset($_POST['username']) ? sanitize($_POST['username']) : ''; ?>"
                               required>
                        <?php if ($loginType === 'student'): ?>
                        <small style="color: #6b7280; font-size: 0.85rem;">
                            Gunakan NIM lengkap sebagai username
                        </small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Masukkan password"
                               required>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-full">
                            Login sebagai <?php echo $loginType === 'admin' ? 'Admin' : 'Mahasiswa'; ?>
                        </button>
                    </div>
                </form>

                <!-- Demo Credentials -->
                <div style="background-color: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-top: 2rem;">
                    <h4 style="color: #374151; margin-bottom: 1rem; font-size: 1rem;">Demo Akun:</h4>
                    
                    <div id="studentDemo" style="<?php echo $loginType === 'admin' ? 'display: none;' : ''; ?>">
                        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 0.5rem;"><strong>Mahasiswa:</strong></p>
                        <p style="color: #6b7280; font-size: 0.85rem;">
                            NIM: <code style="background: #e5e7eb; padding: 0.2rem 0.4rem; border-radius: 4px;">220212060374</code><br>
                            Password: <code style="background: #e5e7eb; padding: 0.2rem 0.4rem; border-radius: 4px;">password</code>
                        </p>
                    </div>
                    
                    <div id="adminDemo" style="<?php echo $loginType === 'student' ? 'display: none;' : ''; ?>">
                        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 0.5rem;"><strong>Admin:</strong></p>
                        <p style="color: #6b7280; font-size: 0.85rem;">
                            Username: <code style="background: #e5e7eb; padding: 0.2rem 0.4rem; border-radius: 4px;">admin</code><br>
                            Password: <code style="background: #e5e7eb; padding: 0.2rem 0.4rem; border-radius: 4px;">password</code>
                        </p>
                    </div>
                </div>

                <!-- Information -->
                <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <p style="color: #6b7280; font-size: 0.9rem;">
                        Belum memiliki akun? Hubungi admin untuk pembuatan akun mahasiswa.
                    </p>
                    <p style="color: #6b7280; font-size: 0.85rem; margin-top: 0.5rem;">
                        📧 Email: <?php echo ADMIN_EMAIL; ?>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background-color: #1f2937; color: white; text-align: center; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <p>&copy; 2025 Portal Ekstrakurikuler UNSRAT. Universitas Sam Ratulangi.</p>
        </div>
    </footer>

    <script>
        function switchLoginType(type) {
            // Update form
            document.getElementById('loginType').value = type;
            
            // Update tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update labels and placeholders
            const usernameLabel = document.getElementById('usernameLabel');
            const usernameInput = document.getElementById('username');
            const submitButton = document.querySelector('button[type="submit"]');
            
            if (type === 'admin') {
                usernameLabel.textContent = 'Username Admin';
                usernameInput.placeholder = 'Masukkan username admin';
                submitButton.textContent = 'Login sebagai Admin';
                document.getElementById('studentDemo').style.display = 'none';
                document.getElementById('adminDemo').style.display = 'block';
            } else {
                usernameLabel.textContent = 'NIM (Nomor Induk Mahasiswa)';
                usernameInput.placeholder = 'Contoh: 220212060374';
                submitButton.textContent = 'Login sebagai Mahasiswa';
                document.getElementById('studentDemo').style.display = 'block';
                document.getElementById('adminDemo').style.display = 'none';
            }
            
            // Clear form
            usernameInput.value = '';
            document.getElementById('password').value = '';
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            window.history.replaceState({}, '', url);
        }

        // Auto-fill demo credentials
        function fillDemo(type) {
            if (type === 'student') {
                document.getElementById('username').value = '220212060374';
                document.getElementById('password').value = 'password';
            } else {
                document.getElementById('username').value = 'admin';
                document.getElementById('password').value = 'password';
            }
        }

        // Add click handlers for demo credentials
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('code').forEach(code => {
                code.style.cursor = 'pointer';
                code.title = 'Klik untuk mengisi otomatis';
                code.addEventListener('click', function() {
                    const currentType = document.getElementById('loginType').value;
                    fillDemo(currentType);
                });
            });
        });

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginType = document.getElementById('loginType').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Username dan password harus diisi');
                return;
            }
            
            if (loginType === 'student' && username.length < 5) {
                e.preventDefault();
                alert('NIM harus minimal 5 karakter');
                return;
            }
        });
    </script>
</body>
</html>