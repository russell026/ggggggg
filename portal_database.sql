-- Database: portal_ekstrakurikuler_unsrat
CREATE DATABASE IF NOT EXISTS portal_ekstrakurikuler_unsrat;
USE portal_ekstrakurikuler_unsrat;

-- Table: admin
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nim VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    fakultas VARCHAR(100),
    jurusan VARCHAR(100),
    semester INT,
    telepon VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: extracurricular_activities
CREATE TABLE extracurricular_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kegiatan VARCHAR(200) NOT NULL,
    deskripsi TEXT,
    kuota INT NOT NULL DEFAULT 0,
    peserta_terdaftar INT DEFAULT 0,
    status_pendaftaran ENUM('open', 'closed') DEFAULT 'closed',
    gambar VARCHAR(255) DEFAULT 'default-activity.jpg',
    whatsapp_link VARCHAR(500),
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    tempat VARCHAR(200),
    pembimbing VARCHAR(100),
    kategori ENUM('akademik', 'olahraga', 'seni', 'organisasi', 'lainnya') DEFAULT 'lainnya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: registrations
CREATE TABLE registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    activity_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    tanggal_daftar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    keterangan TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES extracurricular_activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (student_id, activity_id)
);

-- Table: activity_announcements
CREATE TABLE activity_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    judul VARCHAR(200) NOT NULL,
    isi TEXT NOT NULL,
    tanggal_posting TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES extracurricular_activities(id) ON DELETE CASCADE
);

-- Insert default admin
INSERT INTO admin (username, password, nama, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@unsrat.ac.id');
-- Password: password

-- Insert sample students
INSERT INTO students (nim, password, nama, email, fakultas, jurusan, semester, telepon) VALUES 
('220212060374', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'john.doe@student.unsrat.ac.id', 'MIPA', 'Informatika', 4, '081234567890'),
('220212060375', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'jane.smith@student.unsrat.ac.id', 'MIPA', 'Matematika', 3, '081234567891'),
('220212060376', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Wilson', 'bob.wilson@student.unsrat.ac.id', 'Teknik', 'Sipil', 5, '081234567892');
-- Password untuk semua: password

-- Insert sample extracurricular activities
INSERT INTO extracurricular_activities (nama_kegiatan, deskripsi, kuota, peserta_terdaftar, status_pendaftaran, gambar, whatsapp_link, tanggal_mulai, tanggal_selesai, tempat, pembimbing, kategori) VALUES 
('UKM Basket UNSRAT', 'Unit Kegiatan Mahasiswa Basket UNSRAT yang mengembangkan bakat dan minat mahasiswa dalam olahraga basket', 30, 15, 'open', 'basketball.jpg', 'https://chat.whatsapp.com/basket-unsrat', '2025-07-01', '2025-12-31', 'GOR UNSRAT', 'Dr. Ahmad Suharto', 'olahraga'),
('Paduan Suara Universitas', 'Kelompok paduan suara yang mengembangkan kemampuan vokal dan musikal mahasiswa', 25, 20, 'open', 'choir.jpg', 'https://chat.whatsapp.com/choir-unsrat', '2025-07-01', '2025-12-31', 'Aula Utama UNSRAT', 'Prof. Maria Lumowa', 'seni'),
('Himpunan Mahasiswa Informatika', 'Organisasi mahasiswa informatika yang mengembangkan kemampuan teknologi dan kepemimpinan', 50, 45, 'closed', 'himtika.jpg', 'https://chat.whatsapp.com/himtika-unsrat', '2025-06-01', '2025-12-31', 'Fakultas MIPA', 'Dr. Ir. Vecky Poekoel', 'organisasi'),
('English Club UNSRAT', 'Klub bahasa Inggris untuk meningkatkan kemampuan berbahasa Inggris mahasiswa', 40, 25, 'open', 'english-club.jpg', 'https://chat.whatsapp.com/english-unsrat', '2025-07-15', '2025-12-31', 'Gedung Bahasa', 'Dr. Jenny Telleng', 'akademik'),
('UKM Fotografi', 'Unit Kegiatan Mahasiswa Fotografi yang mengembangkan kreativitas visual mahasiswa', 20, 18, 'closed', 'photography.jpg', 'https://chat.whatsapp.com/photo-unsrat', '2025-06-01', '2025-12-31', 'Studio Fotografi', 'Drs. Michael Karundeng', 'seni');

-- Insert sample registrations
INSERT INTO registrations (student_id, activity_id, status, keterangan) VALUES 
(1, 1, 'approved', 'Mahasiswa aktif dan berprestasi'),
(1, 2, 'pending', 'Menunggu konfirmasi'),
(2, 2, 'approved', 'Memiliki pengalaman bernyanyi'),
(3, 4, 'approved', 'Kemampuan bahasa Inggris baik');