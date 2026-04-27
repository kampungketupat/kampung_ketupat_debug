<?php
// Database config dan auto-migration ringan agar schema selalu sinkron dengan model.

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kampung_ketupat');
define('DB_CHARSET', 'utf8mb4');

$koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($koneksi->connect_error) {
    die('Koneksi MySQL gagal: ' . $koneksi->connect_error);
}

$koneksi->set_charset(DB_CHARSET);

$koneksi->query(
    'CREATE DATABASE IF NOT EXISTS ' . DB_NAME . '
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci'
);

$koneksi->select_db(DB_NAME);

$queries = [
    "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        nama_lengkap VARCHAR(150),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS galeri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        judul VARCHAR(200),
        deskripsi TEXT,
        foto VARCHAR(255),
        kategori ENUM('wisata','kuliner','budaya','fasilitas','umum') DEFAULT 'umum',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_publish TINYINT(1) DEFAULT 1
    )",
    "CREATE TABLE IF NOT EXISTS event (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_event VARCHAR(200),
        deskripsi TEXT,
        tanggal_mulai DATE,
        tanggal_selesai DATE,
        lokasi VARCHAR(255),
        foto VARCHAR(255),
        status ENUM('akan_datang','berlangsung','selesai') DEFAULT 'akan_datang',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        jam_mulai TIME NULL,
        jam_selesai TIME NULL,
        link_info VARCHAR(255) NULL
    )",
    "CREATE TABLE IF NOT EXISTS umkm (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_umkm VARCHAR(200),
        pemilik VARCHAR(150),
        kategori ENUM('kuliner','kerajinan','souvenir','jasa','lainnya') DEFAULT 'lainnya',
        deskripsi TEXT,
        produk_unggulan VARCHAR(255),
        kontak VARCHAR(100),
        alamat VARCHAR(255),
        foto VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS kritik_saran (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_pengunjung VARCHAR(150),
        email VARCHAR(150),
        jenis ENUM('kritik','saran','pertanyaan','apresiasi') DEFAULT 'saran',
        pesan TEXT,
        sudah_dibaca TINYINT(1) DEFAULT 0,
        status ENUM('pending','diterima','publik') DEFAULT 'pending',
        tampil_mulai DATE NULL,
        tampil_selesai DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
];

foreach ($queries as $sql) {
    $koneksi->query($sql);
}

// Migrasi schema lama -> baru (kompatibel MySQL 5.7+).
if (!function_exists('kkw_column_exists')) {
    function kkw_column_exists(mysqli $db, string $table, string $column): bool
    {
        $tableEscaped = $db->real_escape_string($table);
        $columnEscaped = $db->real_escape_string($column);
        $result = $db->query("SHOW COLUMNS FROM `$tableEscaped` LIKE '$columnEscaped'");
        return $result && $result->num_rows > 0;
    }
}

if (!kkw_column_exists($koneksi, 'galeri', 'is_publish')) {
    $koneksi->query("ALTER TABLE galeri ADD COLUMN is_publish TINYINT(1) DEFAULT 1");
}

if (!kkw_column_exists($koneksi, 'event', 'jam_mulai')) {
    $koneksi->query('ALTER TABLE event ADD COLUMN jam_mulai TIME NULL');
}

if (!kkw_column_exists($koneksi, 'event', 'jam_selesai')) {
    $koneksi->query('ALTER TABLE event ADD COLUMN jam_selesai TIME NULL');
}

if (!kkw_column_exists($koneksi, 'event', 'link_info')) {
    $koneksi->query('ALTER TABLE event ADD COLUMN link_info VARCHAR(255) NULL');
}

if (!kkw_column_exists($koneksi, 'kritik_saran', 'status')) {
    $koneksi->query("ALTER TABLE kritik_saran ADD COLUMN status ENUM('pending','diterima','publik') DEFAULT 'pending'");
}

if (!kkw_column_exists($koneksi, 'kritik_saran', 'tampil_mulai')) {
    $koneksi->query('ALTER TABLE kritik_saran ADD COLUMN tampil_mulai DATE NULL');
}

if (!kkw_column_exists($koneksi, 'kritik_saran', 'tampil_selesai')) {
    $koneksi->query('ALTER TABLE kritik_saran ADD COLUMN tampil_selesai DATE NULL');
}

$cek = $koneksi->query('SELECT COUNT(*) as total FROM admin')->fetch_assoc();
if ((int)($cek['total'] ?? 0) === 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare('INSERT INTO admin (username, password, nama_lengkap) VALUES (?, ?, ?)');
    if ($stmt) {
        $username = 'admin';
        $nama = 'Administrator';
        $stmt->bind_param('sss', $username, $password, $nama);
        $stmt->execute();
        $stmt->close();
    }
}

$cek = $koneksi->query('SELECT COUNT(*) as total FROM galeri')->fetch_assoc();
if ((int)($cek['total'] ?? 0) === 0) {
    $koneksi->query(
        "INSERT INTO galeri (judul, foto, kategori, is_publish) VALUES
        ('Monumen Ketupat', 'https://images.unsplash.com/photo-1533906966484-a9c978a3f090', 'wisata', 1),
        ('Rumah Warna Warni', 'https://images.unsplash.com/photo-1566552881560-0be862a7c445', 'wisata', 1)"
    );
}

$cek = $koneksi->query('SELECT COUNT(*) as total FROM event')->fetch_assoc();
if ((int)($cek['total'] ?? 0) === 0) {
    $koneksi->query(
        "INSERT INTO event (nama_event, tanggal_mulai, status, jam_mulai, jam_selesai, link_info)
        VALUES ('Festival Ketupat', '2026-04-15', 'akan_datang', NULL, NULL, NULL)"
    );
}

$cek = $koneksi->query('SELECT COUNT(*) as total FROM umkm')->fetch_assoc();
if ((int)($cek['total'] ?? 0) === 0) {
    $koneksi->query(
        "INSERT INTO umkm (nama_umkm, kategori)
        VALUES ('Warung Ketupat Bu Sari', 'kuliner')"
    );
}

// Seed tambahan untuk pemerataan data chart upload galeri 12 bulan.
if (!function_exists('kkw_seed_galeri_chart_data')) {
    function kkw_seed_galeri_chart_data(mysqli $db): void
    {
        $fotoPool = [
            'https://images.unsplash.com/photo-1504674900247-0877df9cc836',
            'https://images.unsplash.com/photo-1498654896293-37aacf113fd9',
            'https://images.unsplash.com/photo-1528715471579-d1bcf0ba5e83',
            'https://images.unsplash.com/photo-1489515217757-5fd1be406fef',
            'https://images.unsplash.com/photo-1473093295043-cdd812d0e601',
            'https://images.unsplash.com/photo-1528605248644-14dd04022da1',
        ];

        $kategoriPool = ['wisata', 'kuliner', 'budaya', 'fasilitas', 'umum'];
        $start = new DateTime('first day of -11 months');

        for ($i = 0; $i < 12; $i++) {
            $dt = clone $start;
            $dt->modify("+{$i} months");

            $ym = $dt->format('Y-m');
            $judul = 'Seeder Chart ' . $ym;

            $stmtCheck = $db->prepare('SELECT id FROM galeri WHERE judul = ? LIMIT 1');
            if (!$stmtCheck) {
                continue;
            }

            $stmtCheck->bind_param('s', $judul);
            $stmtCheck->execute();
            $exists = $stmtCheck->get_result();
            $already = $exists && $exists->num_rows > 0;
            $stmtCheck->close();

            if ($already) {
                continue;
            }

            $foto = $fotoPool[$i % count($fotoPool)];
            $kategori = $kategoriPool[$i % count($kategoriPool)];
            $createdAt = $dt->format('Y-m-15 10:00:00');
            $deskripsi = 'Data tambahan untuk statistik upload galeri bulan ' . $ym;
            $isPublish = 0;

            $stmtInsert = $db->prepare("
                INSERT INTO galeri (judul, deskripsi, foto, kategori, is_publish, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if (!$stmtInsert) {
                continue;
            }

            $stmtInsert->bind_param('ssssis', $judul, $deskripsi, $foto, $kategori, $isPublish, $createdAt);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }
}

kkw_seed_galeri_chart_data($koneksi);
