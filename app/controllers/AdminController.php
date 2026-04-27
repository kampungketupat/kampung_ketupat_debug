<?php
// ============================================================
// FILE: controller/AdminController.php
// Controller untuk login admin & dashboard admin
// ============================================================

require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/AdminModel.php';
require_once BASE_PATH . '/app/models/GaleriModel.php';
require_once BASE_PATH . '/app/models/EventModel.php';
require_once BASE_PATH . '/app/models/UMKMModel.php';
require_once BASE_PATH . '/app/models/KritikSaranModel.php';

class AdminController extends Controller
{
    private $db;
    private $adminModel;
    private $galeriModel;
    private $eventModel;
    private $umkmModel;
    private $kritikModel;

    public function __construct()
    {
        global $koneksi;

        $this->db = $koneksi;

        $this->adminModel  = new AdminModel($koneksi);
        $this->galeriModel = new GaleriModel($koneksi);
        $this->eventModel  = new EventModel($koneksi);
        $this->umkmModel   = new UMKMModel($koneksi);
        $this->kritikModel = new KritikSaranModel($koneksi);
    }

    // =========================
    // LOGIN (GET + POST)
    // =========================
    public function login()
    {
        // kalau sudah login → dashboard
        if (isset($_SESSION['admin'])) {
            header('Location: ' . BASE_URL . '/admin/dashboard');
            exit;
        }

        // =========================
        // HANDLE POST (LOGIN)
        // =========================
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            // validasi sederhana
            if ($username === '' || $password === '') {
                $_SESSION['login_error'] = 'Username dan password wajib diisi';
                header('Location: ' . BASE_URL . '/admin/login');
                exit;
            }

            // cek ke database
            $admin = $this->adminModel->login($username, $password);

            if ($admin) {
                // simpan session
                $_SESSION['admin'] = $admin;

                header('Location: ' . BASE_URL . '/admin/dashboard');
                exit;
            } else {
                $_SESSION['login_error'] = 'Username atau password salah';
                header('Location: ' . BASE_URL . '/admin/login');
                exit;
            }
        }

        // =========================
        // TAMPILKAN LOGIN (GET)
        // =========================
        $data['pesan_error'] = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        $data['judul_halaman'] = 'Login Admin — Kampung Ketupat';

        $this->view('auth/login', $data);
    }

    // =========================
    // DASHBOARD
    // =========================
    public function dashboard()
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: ' . BASE_URL . '/admin/login');
            exit;
        }

        $data['stats'] = [
            'galeri' => $this->galeriModel->countAll(),
            'event'  => $this->eventModel->countAll(),
            'umkm'   => $this->umkmModel->countAll(),
            'kritik' => $this->kritikModel->countBelumDibaca()
        ];

        $data['pesan_terbaru'] = array_slice(
            $this->kritikModel->getPending(),
            0,
            5
        );

        // Data chart 12 bulan terakhir (rolling), real-time dari tabel galeri.
        $rangeStart = new DateTime('first day of -11 months');
        $query = "
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
            FROM galeri
            WHERE created_at >= ?
            GROUP BY ym
            ORDER BY ym
        ";

        $stmt = mysqli_prepare($this->db, $query);
        $countByMonth = [];

        if ($stmt) {
            $startStr = $rangeStart->format('Y-m-d 00:00:00');
            mysqli_stmt_bind_param($stmt, 's', $startStr);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $countByMonth[$row['ym']] = (int)$row['total'];
            }

            mysqli_stmt_close($stmt);
        }

        $labelBulan = [];
        $dataBulanan = [];
        $dataBulanIni = [];
        $currentYm = (new DateTime())->format('Y-m');
        $namaBulanId = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        for ($i = 0; $i < 12; $i++) {
            $dt = clone $rangeStart;
            $dt->modify("+{$i} months");

            $ym = $dt->format('Y-m');
            $count = $countByMonth[$ym] ?? 0;

            $bulanKe = (int)$dt->format('n');
            $tahun = $dt->format('Y');
            $labelBulan[] = ($namaBulanId[$bulanKe] ?? $dt->format('F')) . ' ' . $tahun;
            $dataBulanan[] = $count;
            $dataBulanIni[] = ($ym === $currentYm) ? $count : null;
        }

        $data['labelBulan'] = $labelBulan;
        $data['dataBulanan'] = $dataBulanan;
        $data['dataBulanIni'] = $dataBulanIni;

        $nama = $_SESSION['admin']['nama'] ?? 'Admin';

        $initials = strtoupper(
            implode('', array_map(fn($w) => $w[0], explode(' ', $nama)))
        );

        $data['admin_nama'] = $nama;
        $data['initials'] = $initials;
        $data['judul_halaman'] = 'Dashboard Admin — Kampung Ketupat';

        $this->view('admin/dashboard', $data);
    }

    // =========================
    // LOGOUT
    // =========================
    public function logout()
    {
        session_destroy();
        header('Location: ' . BASE_URL . '/admin/login');
        exit;
    }
}
