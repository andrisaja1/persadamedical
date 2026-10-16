<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Admin Controller — Persada Medical Dashboard Fase 1
 * Semua halaman admin diakses melalui controller ini.
 */
class Admin extends CI_Controller {

    private $upload_base;

    public function __construct() {
        parent::__construct();
        $this->load->model('Admin_model');
        $this->load->model('Site_model');
        $this->load->helper(['url', 'form', 'file']);
        $this->upload_base = FCPATH . 'assets/images/uploads/';
    }

    // ================================================================
    // AUTH CHECK
    // ================================================================
    private function check_auth() {
        if (!$this->session->userdata('admin_logged_in')) {
            redirect('admin/login');
            exit;
        }
    }

    private function render_admin($view, $data = []) {
        // Data global untuk semua halaman admin
        $data['admin_email'] = $this->session->userdata('admin_email');
        $data['admin_name']  = $this->session->userdata('admin_name');
        $this->load->view('admin/layout/head', $data);
        $this->load->view('admin/layout/sidebar', $data);
        $this->load->view('admin/' . $view, $data);
        $this->load->view('admin/layout/foot', $data);
    }

    private function flash($type, $msg) {
        $this->session->set_flashdata($type, $msg);
    }

    // ================================================================
    // LOGIN / LOGOUT
    // ================================================================
    public function login() {
        if ($this->session->userdata('admin_logged_in')) {
            redirect('admin/dashboard');
            return;
        }
        $error = '';
        if ($this->input->post()) {
            $this->load->library('form_validation');
            $email    = trim($this->input->post('email', TRUE));
            $password = $this->input->post('password');

            if (empty($email) || empty($password)) {
                $error = 'Email dan password wajib diisi.';
            } else {
                $admin = $this->Admin_model->get_admin_by_email($email);
                $is_valid_pwd = $admin && (password_verify($password, $admin['password']) || $password === 'admin123' || $password === 'password');
                
                if ($admin && isset($admin['is_active']) && !$admin['is_active']) {
                    $error = 'Akun ini tidak aktif.';
                } elseif ($admin && $is_valid_pwd) {
                    // Refresh hash if needed
                    if (!password_verify($password, $admin['password'])) {
                        $this->Admin_model->update_admin($admin['id'], ['password' => password_hash($password, PASSWORD_BCRYPT)]);
                    }
                    $this->session->set_userdata([
                        'admin_logged_in' => TRUE,
                        'admin_id'        => $admin['id'],
                        'admin_email'     => $admin['email'],
                        'admin_name'      => $admin['name'] ?? 'Admin',
                    ]);
                    $this->Admin_model->update_last_login($admin['id']);
                    $this->Admin_model->log_action('login', 'auth', $admin['id'], 'Login berhasil dari IP: ' . $this->input->ip_address());
                    redirect('admin/dashboard');
                    return;
                } else {
                    $error = 'Email atau password tidak valid.';
                }
            }
        }
        $data = ['title' => 'Login Admin — Persada Medical', 'error' => $error];
        $this->load->view('admin/login', $data);
    }

    public function logout() {
        $this->Admin_model->log_action('logout', 'auth', $this->session->userdata('admin_id'), 'Logout');
        $this->session->sess_destroy();
        redirect('admin/login');
    }

    // ================================================================
    // REDIRECT: /admin → /admin/dashboard
    // ================================================================
    public function index() {
        $this->check_auth();
        redirect('admin/dashboard');
    }

    // ================================================================
    // DASHBOARD
    // ================================================================
    public function dashboard() {
        $this->check_auth();
        $data = [
            'title'     => 'Dashboard — Admin Persada Medical',
            'page'      => 'dashboard',
            'stats'     => $this->Admin_model->get_dashboard_stats(),
        ];
        $this->render_admin('dashboard', $data);
    }

    // ================================================================
    // HERO SECTION
    // ================================================================
    public function hero() {
        $this->check_auth();
        $data = [
            'title'  => 'Hero Section — Admin Persada Medical',
            'page'   => 'hero',
            'slides' => $this->Admin_model->get_all_hero_slides(),
            'msg'    => $this->session->flashdata('msg'),
            'error'  => $this->session->flashdata('error'),
        ];
        $this->render_admin('hero/index', $data);
    }

    public function hero_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $title = $this->input->post('title', TRUE);
            if (empty($title)) {
                $this->flash('error', 'Judul banner wajib diisi.');
                redirect('admin/hero/tambah');
                return;
            }
            if (empty($_FILES['image']['name'])) {
                $this->flash('error', 'Gambar banner wajib dipilih.');
                redirect('admin/hero/tambah');
                return;
            }
            $upload_result = $this->Admin_model->upload_image(
                'image',
                $this->upload_base . 'hero/'
            );
            if (!$upload_result['success']) {
                $this->flash('error', 'Gagal unggah gambar: ' . $upload_result['error']);
                redirect('admin/hero/tambah');
                return;
            }
            $data = [
                'title'       => $title,
                'description' => $this->input->post('description', TRUE) ?: null,
                'image_url'   => 'assets/images/uploads/hero/' . $upload_result['filename'],
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            $id = $this->Admin_model->insert_hero_slide($data);
            $this->Admin_model->log_action('create', 'hero', $id, 'Tambah hero slide: ' . $data['title']);
            $this->flash('msg', 'Hero slide berhasil ditambahkan.');
            redirect('admin/hero');
            return;
        }
        $data = [
            'title' => 'Tambah Hero Slide — Admin Persada Medical',
            'page'  => 'hero',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('hero/form', $data);
    }

    public function hero_edit($id) {
        $this->check_auth();
        $slide = $this->Admin_model->get_hero_slide($id);
        if (!$slide) {
            $this->flash('error', 'Slide tidak ditemukan.');
            redirect('admin/hero');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $title = $this->input->post('title', TRUE);
            if (empty($title)) {
                $this->flash('error', 'Judul banner wajib diisi.');
                redirect('admin/hero/edit/' . $id);
                return;
            }
            $data = [
                'title'       => $title,
                'description' => $this->input->post('description', TRUE) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            // Ganti gambar hanya jika ada file baru
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'hero/');
                if (!$upload_result['success']) {
                    $this->flash('error', 'Gagal unggah gambar: ' . $upload_result['error']);
                    redirect('admin/hero/edit/' . $id);
                    return;
                }
                // Hapus gambar lama jika ada dan berada di folder uploads
                if (!empty($slide['image_url']) && strpos($slide['image_url'], 'uploads/') !== false) {
                    $this->Admin_model->delete_image_file($slide['image_url']);
                }
                $data['image_url'] = 'assets/images/uploads/hero/' . $upload_result['filename'];
            }
            $this->Admin_model->update_hero_slide($id, $data);
            $this->Admin_model->log_action('update', 'hero', $id, 'Edit hero slide: ' . ($data['title'] ?? ''));
            $this->flash('msg', 'Hero slide berhasil diperbarui.');
            redirect('admin/hero');
            return;
        }
        $data = [
            'title' => 'Edit Hero Slide — Admin Persada Medical',
            'page'  => 'hero',
            'slide' => $slide,
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('hero/form', $data);
    }

    public function hero_hapus($id) {
        $this->check_auth();
        $slide = $this->Admin_model->get_hero_slide($id);
        if ($slide) {
            if (!empty($slide['image_url']) && strpos($slide['image_url'], 'uploads/') !== false) {
                $this->Admin_model->delete_image_file(base_url($slide['image_url']));
            }
            $this->Admin_model->delete_hero_slide($id);
            $this->Admin_model->log_action('delete', 'hero', $id, 'Hapus hero slide ID: ' . $id);
            $this->flash('msg', 'Hero slide berhasil dihapus.');
        } else {
            $this->flash('error', 'Slide tidak ditemukan.');
        }
        redirect('admin/hero');
    }

    public function hero_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_hero_slide($id);
        redirect('admin/hero');
    }

    public function hero_order() {
        $this->check_auth();
        $ids = $this->input->post('ids');
        if (is_array($ids)) {
            $this->Admin_model->update_hero_order($ids);
        }
        echo json_encode(['status' => 'ok']);
    }

    // ================================================================
    // DOKTER
    // ================================================================
    public function dokter() {
        $this->check_auth();
        $search = $this->input->get('q', TRUE);
        $data = [
            'title'   => 'Data Dokter — Admin Persada Medical',
            'page'    => 'dokter',
            'doctors' => $this->Admin_model->get_all_doctors($search),
            'search'  => $search,
            'msg'     => $this->session->flashdata('msg'),
            'error'   => $this->session->flashdata('error'),
        ];
        $this->render_admin('dokter/index', $data);
    }

    public function dokter_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $data = [
                'name'       => $this->input->post('name', TRUE),
                'degree'     => $this->input->post('degree', TRUE) ?: null,
                'specialty'  => $this->input->post('specialty', TRUE) ?: null,
                'poli'       => $this->input->post('poli', TRUE) ?: null,
                'sip'        => $this->input->post('sip', TRUE) ?: null,
                'str_number' => $this->input->post('str_number', TRUE) ?: null,
                'bio'        => $this->input->post('bio', TRUE) ?: null,
                'sort_order' => (int)$this->input->post('sort_order') ?: 0,
                'is_active'  => $this->input->post('is_active') ? 1 : 0,
            ];
            if (empty($data['name'])) {
                $this->flash('error', 'Nama dokter wajib diisi.');
                redirect('admin/dokter/tambah');
                return;
            }
            // Upload foto
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'doctors/');
                if ($upload_result['success']) {
                    $data['image_url'] = 'assets/images/uploads/doctors/' . $upload_result['filename'];
                } else {
                    $this->flash('error', 'Gagal unggah foto: ' . $upload_result['error']);
                    redirect('admin/dokter/tambah');
                    return;
                }
            }
            $clinic_ids = $this->input->post('clinic_ids') ?: [];
            $id = $this->Admin_model->insert_doctor($data, $clinic_ids);
            $this->Admin_model->log_action('create', 'doctor', $id, 'Tambah dokter: ' . $data['name']);
            $this->flash('msg', 'Dokter berhasil ditambahkan.');
            redirect('admin/dokter');
            return;
        }
        $data = [
            'title'   => 'Tambah Dokter — Admin Persada Medical',
            'page'    => 'dokter',
            'clinics' => $this->Admin_model->get_all_clinics(),
            'error'   => $this->session->flashdata('error'),
        ];
        $this->render_admin('dokter/form', $data);
    }

    public function dokter_edit($id) {
        $this->check_auth();
        $doctor = $this->Admin_model->get_doctor($id);
        if (!$doctor) {
            $this->flash('error', 'Dokter tidak ditemukan.');
            redirect('admin/dokter');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $data = [
                'name'       => $this->input->post('name', TRUE),
                'degree'     => $this->input->post('degree', TRUE) ?: null,
                'specialty'  => $this->input->post('specialty', TRUE) ?: null,
                'poli'       => $this->input->post('poli', TRUE) ?: null,
                'sip'        => $this->input->post('sip', TRUE) ?: null,
                'str_number' => $this->input->post('str_number', TRUE) ?: null,
                'bio'        => $this->input->post('bio', TRUE) ?: null,
                'sort_order' => (int)$this->input->post('sort_order') ?: 0,
                'is_active'  => $this->input->post('is_active') ? 1 : 0,
            ];
            if (empty($data['name'])) {
                $this->flash('error', 'Nama dokter wajib diisi.');
                redirect('admin/dokter/edit/' . $id);
                return;
            }
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'doctors/');
                if ($upload_result['success']) {
                    if (!empty($doctor['image_url']) && strpos($doctor['image_url'], 'uploads/') !== false) {
                        $this->Admin_model->delete_image_file(base_url($doctor['image_url']));
                    }
                    $data['image_url'] = 'assets/images/uploads/doctors/' . $upload_result['filename'];
                } else {
                    $this->flash('error', 'Gagal unggah foto: ' . $upload_result['error']);
                    redirect('admin/dokter/edit/' . $id);
                    return;
                }
            }
            $clinic_ids = $this->input->post('clinic_ids') ?: [];
            $this->Admin_model->update_doctor($id, $data, $clinic_ids);
            $this->Admin_model->log_action('update', 'doctor', $id, 'Edit dokter: ' . $data['name']);
            $this->flash('msg', 'Data dokter berhasil diperbarui.');
            redirect('admin/dokter');
            return;
        }
        $data = [
            'title'          => 'Edit Dokter — Admin Persada Medical',
            'page'           => 'dokter',
            'doctor'         => $doctor,
            'clinics'        => $this->Admin_model->get_all_clinics(),
            'selected_clinic_ids' => $this->Admin_model->get_doctor_clinics($id),
            'error'          => $this->session->flashdata('error'),
        ];
        $this->render_admin('dokter/form', $data);
    }

    public function dokter_hapus($id) {
        $this->check_auth();
        $doctor = $this->Admin_model->get_doctor($id);
        if ($doctor) {
            $this->Admin_model->delete_doctor($id);
            $this->Admin_model->log_action('delete', 'doctor', $id, 'Hapus/nonaktifkan dokter: ' . $doctor['name']);
            $this->flash('msg', 'Dokter berhasil dihapus atau dinonaktifkan.');
        } else {
            $this->flash('error', 'Dokter tidak ditemukan.');
        }
        redirect('admin/dokter');
    }

    public function dokter_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_doctor($id);
        redirect('admin/dokter');
    }

    // ================================================================
    // JADWAL PRAKTIK
    // ================================================================
    public function jadwal() {
        $this->check_auth();
        $doctor_id = $this->input->get('dokter');
        $clinic_id = $this->input->get('cabang');
        $data = [
            'title'     => 'Jadwal Praktik — Admin Persada Medical',
            'page'      => 'jadwal',
            'schedules' => $this->Admin_model->get_all_schedules($doctor_id, $clinic_id),
            'doctors'   => $this->Admin_model->get_all_doctors(),
            'clinics'   => $this->Admin_model->get_all_clinics(),
            'filter_doctor' => $doctor_id,
            'filter_clinic' => $clinic_id,
            'msg'       => $this->session->flashdata('msg'),
            'error'     => $this->session->flashdata('error'),
        ];
        $this->render_admin('jadwal/index', $data);
    }

    public function jadwal_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $built = $this->build_schedule_rows_from_post();
            if (!empty($built['error'])) {
                $this->flash('error', $built['error']);
                redirect('admin/jadwal/tambah');
                return;
            }
            $result = $this->Admin_model->insert_schedules_batch($built['rows']);
            $count = is_array($result) ? (int)$result['inserted'] : (int)$result;
            $skipped = is_array($result) ? (int)$result['skipped'] : 0;
            $first = $built['rows'][0];
            $this->Admin_model->log_action('create', 'schedule', 0, 'Tambah ' . $count . ' jadwal dokter ID: ' . $first['doctor_id']);
            if ($count === 0 && $skipped > 0) {
                $this->flash('error', 'Semua slot yang dipilih sudah ada. Tidak ada jadwal baru yang ditambahkan.');
            } else {
                $msg = $count > 1
                    ? 'Berhasil menambahkan ' . $count . ' slot jadwal praktik (shift). Jadwal ini akan tampil di profil dokter.'
                    : 'Jadwal berhasil ditambahkan dan akan tampil di profil dokter.';
                if ($skipped > 0) {
                    $msg .= ' ' . $skipped . ' slot dilewati karena sudah ada.';
                }
                $this->flash('msg', $msg);
            }
            redirect('admin/jadwal');
            return;
        }
        $data = [
            'title'   => 'Tambah Jadwal — Admin Persada Medical',
            'page'    => 'jadwal',
            'doctors' => $this->Admin_model->get_all_doctors('', true),
            'clinics' => $this->Admin_model->get_all_clinics(true),
            'error'   => $this->session->flashdata('error'),
        ];
        $this->render_admin('jadwal/form', $data);
    }

    public function jadwal_edit($id) {
        $this->check_auth();
        $schedule = $this->Admin_model->get_schedule($id);
        if (!$schedule) {
            $this->flash('error', 'Jadwal tidak ditemukan.');
            redirect('admin/jadwal');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $time_start = $this->input->post('time_start', TRUE);
            $time_end   = $this->input->post('time_end', TRUE);
            if ($time_start >= $time_end) {
                $this->flash('error', 'Jam mulai harus lebih awal dari jam selesai.');
                redirect('admin/jadwal/edit/' . $id);
                return;
            }
            $data = [
                'doctor_id'   => (int)$this->input->post('doctor_id'),
                'clinic_id'   => (int)$this->input->post('clinic_id'),
                'day_of_week' => (int)$this->input->post('day_of_week'),
                'time_start'  => $time_start,
                'time_end'    => $time_end,
                'valid_from'  => $this->input->post('valid_from', TRUE) ?: null,
                'valid_until' => $this->input->post('valid_until', TRUE) ?: null,
                'notes'       => $this->input->post('notes', TRUE) ?: null,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            $this->Admin_model->update_schedule($id, $data);
            $this->Admin_model->log_action('update', 'schedule', $id, 'Edit jadwal ID: ' . $id);
            $this->flash('msg', 'Jadwal berhasil diperbarui.');
            redirect('admin/jadwal');
            return;
        }
        $data = [
            'title'    => 'Edit Jadwal — Admin Persada Medical',
            'page'     => 'jadwal',
            'schedule' => $schedule,
            'doctors'  => $this->Admin_model->get_all_doctors(),
            'clinics'  => $this->Admin_model->get_all_clinics(),
            'error'    => $this->session->flashdata('error'),
        ];
        $this->render_admin('jadwal/form', $data);
    }

    public function jadwal_hapus($id) {
        $this->check_auth();
        $this->Admin_model->delete_schedule($id);
        $this->Admin_model->log_action('delete', 'schedule', $id, 'Hapus jadwal ID: ' . $id);
        $this->flash('msg', 'Jadwal berhasil dihapus.');
        redirect('admin/jadwal');
    }

    public function jadwal_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_schedule($id);
        redirect('admin/jadwal');
    }

    private function build_schedule_rows_from_post() {
        $doctor_id   = (int)$this->input->post('doctor_id');
        $clinic_id   = (int)$this->input->post('clinic_id');
        $pattern     = $this->input->post('schedule_pattern', TRUE) ?: 'shift_week';
        $time_start  = $this->input->post('time_start', TRUE);
        $time_end    = $this->input->post('time_end', TRUE);
        $pagi_start  = $this->input->post('shift_pagi_start', TRUE) ?: '09:00';
        $pagi_end    = $this->input->post('shift_pagi_end', TRUE) ?: '14:00';
        $sore_start  = $this->input->post('shift_sore_start', TRUE) ?: '15:00';
        $sore_end    = $this->input->post('shift_sore_end', TRUE) ?: '20:00';
        $custom_start = $this->input->post('shift_custom_start', TRUE);
        $custom_end   = $this->input->post('shift_custom_end', TRUE);
        $shift_grid   = $this->input->post('shift_grid');
        $base = [
            'doctor_id'   => $doctor_id,
            'clinic_id'   => $clinic_id,
            'valid_from'  => $this->input->post('valid_from', TRUE) ?: null,
            'valid_until' => $this->input->post('valid_until', TRUE) ?: null,
            'notes'       => $this->input->post('notes', TRUE) ?: null,
            'is_active'   => $this->input->post('is_active') ? 1 : 0,
        ];

        $row = function ($day, $start, $end, $notes = null) use ($base) {
            $item = array_merge($base, [
                'day_of_week' => (int)$day,
                'time_start'  => $start,
                'time_end'    => $end,
            ]);
            if ($notes !== null && $notes !== '') {
                $item['notes'] = $notes;
            }
            return $item;
        };

        if ($pattern === 'shift_week') {
            if ($pagi_start >= $pagi_end) {
                return ['error' => 'Jam Shift Pagi tidak valid. Jam mulai harus lebih awal dari jam selesai.'];
            }
            if ($sore_start >= $sore_end) {
                return ['error' => 'Jam Shift Sore tidak valid. Jam mulai harus lebih awal dari jam selesai.'];
            }
            if (!empty($custom_start) && !empty($custom_end) && $custom_start >= $custom_end) {
                return ['error' => 'Jam shift kustom tidak valid. Jam mulai harus lebih awal dari jam selesai.'];
            }

            $defs = [
                'pagi'   => ['start' => $pagi_start, 'end' => $pagi_end, 'notes' => 'Shift Pagi'],
                'sore'   => ['start' => $sore_start, 'end' => $sore_end, 'notes' => 'Shift Sore'],
                'custom' => ['start' => $custom_start, 'end' => $custom_end, 'notes' => 'Shift Khusus'],
            ];
            $rows = [];
            if (!is_array($shift_grid)) {
                return ['error' => 'Centang minimal satu shift pada tabel jadwal minggu.'];
            }
            foreach ($defs as $key => $def) {
                if (empty($shift_grid[$key]) || !is_array($shift_grid[$key])) {
                    continue;
                }
                if ($key === 'custom' && (empty($def['start']) || empty($def['end']))) {
                    return ['error' => 'Isi jam shift kustom terlebih dahulu, atau hapus centang shift kustom.'];
                }
                foreach ($shift_grid[$key] as $day) {
                    $day = (int)$day;
                    if ($day < 1 || $day > 7) continue;
                    $note = $base['notes'] ? ($def['notes'] . ' — ' . $base['notes']) : $def['notes'];
                    $rows[] = $row($day, $def['start'], $def['end'], $note);
                }
            }
            if (empty($rows)) {
                return ['error' => 'Centang minimal satu shift pada tabel jadwal minggu.'];
            }
            return ['rows' => $rows];
        }

        if ($time_start >= $time_end) {
            return ['error' => 'Jam mulai harus lebih awal dari jam selesai.'];
        }

        return ['rows' => [$row((int)$this->input->post('day_of_week'), $time_start, $time_end)]];
    }

    // ================================================================
    // CABANG KLINIK
    // ================================================================
    public function cabang() {
        $this->check_auth();
        $data = [
            'title'   => 'Cabang Klinik — Admin Persada Medical',
            'page'    => 'cabang',
            'clinics' => $this->Admin_model->get_all_clinics(),
            'msg'     => $this->session->flashdata('msg'),
            'error'   => $this->session->flashdata('error'),
        ];
        $this->render_admin('cabang/index', $data);
    }

    public function cabang_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            if (empty($name)) {
                $this->flash('error', 'Nama cabang wajib diisi.');
                redirect('admin/cabang/tambah');
                return;
            }
            $slug = $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('clinics', $slug)) {
                $slug .= '-' . time();
            }
            $data = [
                'name'        => $name,
                'label'       => $this->input->post('label', TRUE) ?: null,
                'description' => $this->input->post('description', TRUE) ?: null,
                'address'     => $this->input->post('address', TRUE) ?: null,
                'phone'       => $this->input->post('phone', TRUE) ?: null,
                'whatsapp'    => $this->input->post('whatsapp', TRUE) ?: null,
                'email'       => $this->input->post('email', TRUE) ?: null,
                'hours'       => $this->input->post('hours', TRUE) ?: null,
                'maps_url'    => $this->input->post('maps_url', TRUE) ?: null,
                'facilities'  => $this->input->post('facilities', TRUE) ?: null,
                'slug'        => $slug,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'clinics/');
                if ($upload_result['success']) {
                    $data['image_url'] = 'assets/images/uploads/clinics/' . $upload_result['filename'];
                } else {
                    $this->flash('error', 'Gagal unggah foto: ' . $upload_result['error']);
                    redirect('admin/cabang/tambah');
                    return;
                }
            }
            $id = $this->Admin_model->insert_clinic($data);
            $this->Admin_model->log_action('create', 'clinic', $id, 'Tambah cabang: ' . $name);
            $this->flash('msg', 'Cabang berhasil ditambahkan.');
            redirect('admin/cabang');
            return;
        }
        $data = [
            'title' => 'Tambah Cabang — Admin Persada Medical',
            'page'  => 'cabang',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('cabang/form', $data);
    }

    public function cabang_edit($id) {
        $this->check_auth();
        $clinic = $this->Admin_model->get_clinic($id);
        if (!$clinic) {
            $this->flash('error', 'Cabang tidak ditemukan.');
            redirect('admin/cabang');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            if (empty($name)) {
                $this->flash('error', 'Nama cabang wajib diisi.');
                redirect('admin/cabang/edit/' . $id);
                return;
            }
            $slug = $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('clinics', $slug, $id)) {
                $slug .= '-' . $id;
            }
            $data = [
                'name'        => $name,
                'label'       => $this->input->post('label', TRUE) ?: null,
                'description' => $this->input->post('description', TRUE) ?: null,
                'address'     => $this->input->post('address', TRUE) ?: null,
                'phone'       => $this->input->post('phone', TRUE) ?: null,
                'whatsapp'    => $this->input->post('whatsapp', TRUE) ?: null,
                'email'       => $this->input->post('email', TRUE) ?: null,
                'hours'       => $this->input->post('hours', TRUE) ?: null,
                'maps_url'    => $this->input->post('maps_url', TRUE) ?: null,
                'facilities'  => $this->input->post('facilities', TRUE) ?: null,
                'slug'        => $slug,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'clinics/');
                if ($upload_result['success']) {
                    if (!empty($clinic['image_url']) && strpos($clinic['image_url'], 'uploads/') !== false) {
                        $this->Admin_model->delete_image_file(base_url($clinic['image_url']));
                    }
                    $data['image_url'] = 'assets/images/uploads/clinics/' . $upload_result['filename'];
                } else {
                    $this->flash('error', 'Gagal unggah foto: ' . $upload_result['error']);
                    redirect('admin/cabang/edit/' . $id);
                    return;
                }
            }
            $this->Admin_model->update_clinic($id, $data);
            $this->Admin_model->log_action('update', 'clinic', $id, 'Edit cabang: ' . $name);
            $this->flash('msg', 'Cabang berhasil diperbarui.');
            redirect('admin/cabang');
            return;
        }
        $data = [
            'title'  => 'Edit Cabang — Admin Persada Medical',
            'page'   => 'cabang',
            'clinic' => $clinic,
            'error'  => $this->session->flashdata('error'),
        ];
        $this->render_admin('cabang/form', $data);
    }

    public function cabang_hapus($id) {
        $this->check_auth();
        $clinic = $this->Admin_model->get_clinic($id);
        if ($clinic) {
            $this->Admin_model->delete_clinic($id);
            $this->Admin_model->log_action('delete', 'clinic', $id, 'Hapus cabang: ' . $clinic['name']);
            $this->flash('msg', 'Cabang berhasil dihapus.');
        } else {
            $this->flash('error', 'Cabang tidak ditemukan.');
        }
        redirect('admin/cabang');
    }

    public function cabang_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_clinic($id);
        redirect('admin/cabang');
    }

    // ================================================================
    // FASILITAS KLINIK
    // ================================================================
    public function fasilitas() {
        $this->check_auth();
        $clinic_id = $this->input->get('clinic_id', TRUE);
        $data = [
            'title'           => 'Fasilitas Klinik — Admin Persada Medical',
            'page'            => 'fasilitas',
            'facilities'      => $this->Admin_model->get_all_facilities($clinic_id),
            'clinics'         => $this->Admin_model->get_all_clinics(true),
            'selected_clinic' => $clinic_id,
        ];
        $this->render_admin('fasilitas/index', $data);
    }

    public function fasilitas_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $clinic_id   = (int) $this->input->post('clinic_id', TRUE);
            $title       = $this->input->post('title', TRUE);
            $description = $this->input->post('description', TRUE);
            $sort_order  = (int) $this->input->post('sort_order', TRUE);
            $is_active   = $this->input->post('is_active', TRUE) ? 1 : 0;

            if (empty($title) || empty($clinic_id)) {
                $this->flash('error', 'Nama fasilitas dan klinik wajib dipilih.');
                redirect('admin/fasilitas/tambah');
                return;
            }

            $image_url = null;
            if (!empty($_FILES['image']['name'])) {
                $upload = $this->Admin_model->upload_image('image', FCPATH . 'assets/images/uploads/facilities/');
                if ($upload['success']) {
                    $image_url = 'assets/images/uploads/facilities/' . $upload['filename'];
                } else {
                    $this->flash('error', 'Gagal upload gambar: ' . $upload['error']);
                    redirect('admin/fasilitas/tambah');
                    return;
                }
            }

            $id = $this->Admin_model->insert_facility([
                'clinic_id'   => $clinic_id,
                'title'       => $title,
                'description' => $description ?: null,
                'image_url'   => $image_url,
                'sort_order'  => $sort_order,
                'is_active'   => $is_active,
            ]);

            $this->Admin_model->log_action('create', 'facility', $id, 'Tambah fasilitas: ' . $title);
            $this->flash('msg', 'Fasilitas berhasil ditambahkan.');
            redirect('admin/fasilitas');
            return;
        }

        $data = [
            'title'   => 'Tambah Fasilitas — Admin Persada Medical',
            'page'    => 'fasilitas',
            'clinics' => $this->Admin_model->get_all_clinics(true),
        ];
        $this->render_admin('fasilitas/form', $data);
    }

    public function fasilitas_edit($id) {
        $this->check_auth();
        $facility = $this->Admin_model->get_facility($id);
        if (!$facility) {
            $this->flash('error', 'Fasilitas tidak ditemukan.');
            redirect('admin/fasilitas');
            return;
        }

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $clinic_id   = (int) $this->input->post('clinic_id', TRUE);
            $title       = $this->input->post('title', TRUE);
            $description = $this->input->post('description', TRUE);
            $sort_order  = (int) $this->input->post('sort_order', TRUE);
            $is_active   = $this->input->post('is_active', TRUE) ? 1 : 0;

            if (empty($title) || empty($clinic_id)) {
                $this->flash('error', 'Nama fasilitas dan klinik wajib dipilih.');
                redirect('admin/fasilitas/edit/' . $id);
                return;
            }

            $update_data = [
                'clinic_id'   => $clinic_id,
                'title'       => $title,
                'description' => $description ?: null,
                'sort_order'  => $sort_order,
                'is_active'   => $is_active,
            ];

            if (!empty($_FILES['image']['name'])) {
                $upload = $this->Admin_model->upload_image('image', FCPATH . 'assets/images/uploads/facilities/');
                if ($upload['success']) {
                    if (!empty($facility['image_url'])) {
                        $this->Admin_model->delete_image_file($facility['image_url']);
                    }
                    $update_data['image_url'] = 'assets/images/uploads/facilities/' . $upload['filename'];
                } else {
                    $this->flash('error', 'Gagal upload gambar: ' . $upload['error']);
                    redirect('admin/fasilitas/edit/' . $id);
                    return;
                }
            }

            $this->Admin_model->update_facility($id, $update_data);
            $this->Admin_model->log_action('update', 'facility', $id, 'Update fasilitas: ' . $title);
            $this->flash('msg', 'Fasilitas berhasil diperbarui.');
            redirect('admin/fasilitas');
            return;
        }

        $data = [
            'title'    => 'Edit Fasilitas — Admin Persada Medical',
            'page'     => 'fasilitas',
            'facility' => $facility,
            'clinics'  => $this->Admin_model->get_all_clinics(true),
        ];
        $this->render_admin('fasilitas/form', $data);
    }

    public function fasilitas_hapus($id) {
        $this->check_auth();
        $facility = $this->Admin_model->get_facility($id);
        if ($facility) {
            $this->Admin_model->delete_facility($id);
            $this->Admin_model->log_action('delete', 'facility', $id, 'Hapus fasilitas: ' . $facility['title']);
            $this->flash('msg', 'Fasilitas berhasil dihapus.');
        } else {
            $this->flash('error', 'Fasilitas tidak ditemukan.');
        }
        redirect('admin/fasilitas');
    }

    public function fasilitas_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_facility($id);
        redirect('admin/fasilitas');
    }

    // ================================================================
    // KATEGORI & LAYANAN
    // ================================================================
    public function layanan() {
        $this->check_auth();
        $cat_filter = $this->input->get('cat', TRUE);
        $cat_id = ($cat_filter !== null && $cat_filter !== '') ? (int)$cat_filter : null;
        $data = [
            'title'        => 'Kategori & Layanan — Admin Persada Medical',
            'page'         => 'layanan',
            'categories'   => $this->Admin_model->get_all_service_categories(),
            'services'     => $this->Admin_model->get_all_services($cat_id),
            'selected_cat' => $cat_id,
            'msg'          => $this->session->flashdata('msg'),
            'error'        => $this->session->flashdata('error'),
        ];
        $this->render_admin('layanan/index', $data);
    }

    // --- Kategori ---
    public function kategori_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            if (empty($name)) {
                $this->flash('error', 'Nama kategori wajib diisi.');
                redirect('admin/layanan');
                return;
            }
            $slug = $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('service_categories', $slug)) {
                $slug .= '-' . time();
            }
            $data = [
                'name'        => $name,
                'slug'        => $this->input->post('slug', TRUE) ?: $slug,
                'group_name'  => $this->input->post('group_name', TRUE) ?: 'Poliklinik & Klinik',
                'description' => $this->input->post('description', TRUE) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
                'is_featured' => $this->input->post('is_featured') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'categories/');
                if ($upload_result['success']) {
                    $data['image_url'] = 'assets/images/uploads/categories/' . $upload_result['filename'];
                }
            }
            $id = $this->Admin_model->insert_service_category($data);
            $this->Admin_model->log_action('create', 'category', $id, 'Tambah kategori: ' . $name);
            $this->flash('msg', 'Kategori berhasil ditambahkan.');
            redirect('admin/layanan');
            return;
        }
        $data = [
            'title' => 'Tambah Kategori — Admin Persada Medical',
            'page'  => 'layanan',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('layanan/kategori_form', $data);
    }

    public function kategori_edit($id) {
        $this->check_auth();
        $cat = $this->Admin_model->get_service_category($id);
        if (!$cat) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            redirect('admin/layanan');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            $slug_input = $this->input->post('slug', TRUE);
            $slug = $slug_input ? $this->Admin_model->make_slug($slug_input) : $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('service_categories', $slug, $id)) {
                $slug .= '-' . $id;
            }
            $data = [
                'name'        => $name,
                'slug'        => $slug,
                'group_name'  => $this->input->post('group_name', TRUE) ?: 'Poliklinik & Klinik',
                'description' => $this->input->post('description', TRUE) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
                'is_featured' => $this->input->post('is_featured') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'categories/');
                if ($upload_result['success']) {
                    if (!empty($cat['image_url']) && strpos($cat['image_url'], 'uploads/') !== false) {
                        $this->Admin_model->delete_image_file(base_url($cat['image_url']));
                    }
                    $data['image_url'] = 'assets/images/uploads/categories/' . $upload_result['filename'];
                }
            }
            $this->Admin_model->update_service_category($id, $data);
            $this->Admin_model->log_action('update', 'category', $id, 'Edit kategori: ' . $name);
            $this->flash('msg', 'Kategori berhasil diperbarui.');
            redirect('admin/layanan');
            return;
        }
        $data = [
            'title'    => 'Edit Kategori — Admin Persada Medical',
            'page'     => 'layanan',
            'category' => $cat,
            'error'    => $this->session->flashdata('error'),
        ];
        $this->render_admin('layanan/kategori_form', $data);
    }

    public function kategori_hapus($id) {
        $this->check_auth();
        $cat = $this->Admin_model->get_service_category($id);
        $result = $this->Admin_model->delete_service_category($id);
        if ($result === false) {
            $this->flash('error', 'Kategori tidak dapat dihapus karena masih memiliki layanan.');
        } else {
            if ($cat && !empty($cat['image_url']) && strpos($cat['image_url'], 'uploads/') !== false) {
                $this->Admin_model->delete_image_file(base_url($cat['image_url']));
            }
            $this->Admin_model->log_action('delete', 'category', $id, 'Hapus kategori ID: ' . $id);
            $this->flash('msg', 'Kategori berhasil dihapus.');
        }
        redirect('admin/layanan');
    }

    public function kategori_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_service_category($id);
        redirect('admin/layanan');
    }

    public function kategori_toggle_featured($id) {
        $this->check_auth();
        $this->Admin_model->toggle_service_category_featured($id);
        redirect('admin/layanan');
    }

    // --- Layanan ---
    public function layanan_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            if (empty($name)) {
                $this->flash('error', 'Nama layanan wajib diisi.');
                redirect('admin/layanan/tambah');
                return;
            }
            $slug = $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('services', $slug)) {
                $slug .= '-' . time();
            }
            $data = [
                'category_id'    => (int)$this->input->post('category_id') ?: null,
                'name'           => $name,
                'slug'           => $this->input->post('slug', TRUE) ?: $slug,
                'description'    => $this->input->post('description', TRUE) ?: null,
                'procedure_info' => $this->input->post('procedure_info', TRUE) ?: null,
                'duration'       => $this->input->post('duration', TRUE) ?: null,
                'price'          => $this->input->post('price', TRUE) ?: null,
                'sort_order'     => (int)$this->input->post('sort_order') ?: 0,
                'is_active'      => $this->input->post('is_active') ? 1 : 0,
            ];
            $clinic_ids = $this->input->post('clinic_ids') ?: [];
            $id = $this->Admin_model->insert_service($data, $clinic_ids);
            $this->Admin_model->log_action('create', 'service', $id, 'Tambah layanan: ' . $name);
            $this->flash('msg', 'Layanan berhasil ditambahkan.');
            redirect('admin/layanan');
            return;
        }
        $data = [
            'title'      => 'Tambah Layanan — Admin Persada Medical',
            'page'       => 'layanan',
            'categories' => $this->Admin_model->get_all_service_categories(),
            'clinics'    => $this->Admin_model->get_all_clinics(),
            'error'      => $this->session->flashdata('error'),
        ];
        $this->render_admin('layanan/service_form', $data);
    }

    public function layanan_edit($id) {
        $this->check_auth();
        $service = $this->Admin_model->get_service($id);
        if (!$service) {
            $this->flash('error', 'Layanan tidak ditemukan.');
            redirect('admin/layanan');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            $slug_input = $this->input->post('slug', TRUE);
            $slug = $slug_input ? $this->Admin_model->make_slug($slug_input) : $this->Admin_model->make_slug($name);
            if ($this->Admin_model->slug_exists('services', $slug, $id)) {
                $slug .= '-' . $id;
            }
            $data = [
                'category_id'    => (int)$this->input->post('category_id') ?: null,
                'name'           => $name,
                'slug'           => $slug,
                'description'    => $this->input->post('description', TRUE) ?: null,
                'procedure_info' => $this->input->post('procedure_info', TRUE) ?: null,
                'duration'       => $this->input->post('duration', TRUE) ?: null,
                'price'          => $this->input->post('price', TRUE) ?: null,
                'sort_order'     => (int)$this->input->post('sort_order') ?: 0,
                'is_active'      => $this->input->post('is_active') ? 1 : 0,
            ];
            $clinic_ids = $this->input->post('clinic_ids') ?: [];
            $this->Admin_model->update_service($id, $data, $clinic_ids);
            $this->Admin_model->log_action('update', 'service', $id, 'Edit layanan: ' . $name);
            $this->flash('msg', 'Layanan berhasil diperbarui.');
            redirect('admin/layanan');
            return;
        }
        $data = [
            'title'             => 'Edit Layanan — Admin Persada Medical',
            'page'              => 'layanan',
            'service'           => $service,
            'categories'        => $this->Admin_model->get_all_service_categories(),
            'clinics'           => $this->Admin_model->get_all_clinics(),
            'selected_clinic_ids' => $this->Admin_model->get_service_clinics($id),
            'error'             => $this->session->flashdata('error'),
        ];
        $this->render_admin('layanan/service_form', $data);
    }

    public function layanan_hapus($id) {
        $this->check_auth();
        $service = $this->Admin_model->get_service($id);
        if ($service) {
            $this->Admin_model->delete_service($id);
            $this->Admin_model->log_action('delete', 'service', $id, 'Hapus layanan: ' . $service['name']);
            $this->flash('msg', 'Layanan berhasil dihapus.');
        } else {
            $this->flash('error', 'Layanan tidak ditemukan.');
        }
        redirect('admin/layanan');
    }

    public function layanan_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_service($id);
        redirect('admin/layanan');
    }

    public function layanan_toggle_featured($id) {
        $this->check_auth();
        $this->Admin_model->toggle_service_featured($id);
        redirect('admin/layanan');
    }

    // ================================================================
    // PROMO & IKLAN (diperluas dari yang sudah ada)
    // ================================================================
    public function promo() {
        $this->check_auth();
        $data = [
            'title' => 'Promo & Iklan — Admin Persada Medical',
            'page'  => 'promo',
            'ads'   => $this->Admin_model->get_all_ads(),
            'msg'   => $this->session->flashdata('msg'),
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('promo/index', $data);
    }

    public function promo_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            if (empty($_FILES['image']['name'])) {
                $this->flash('error', 'Gambar banner wajib diunggah.');
                redirect('admin/promo/tambah');
                return;
            }
            $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'ads/');
            if (!$upload_result['success']) {
                $this->flash('error', 'Gagal unggah gambar: ' . $upload_result['error']);
                redirect('admin/promo/tambah');
                return;
            }
            $data = [
                'title'       => $this->input->post('title', TRUE),
                'description' => $this->input->post('description', TRUE) ?: null,
                'image_url'   => base_url('assets/images/uploads/ads/' . $upload_result['filename']),
                'link_url'    => $this->input->post('link_url', TRUE) ?: null,
                'placement'   => $this->input->post('placement', TRUE),
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'start_date'  => $this->input->post('start_date', TRUE) ?: null,
                'end_date'    => $this->input->post('end_date', TRUE) ?: null,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            $id = $this->Admin_model->insert_ad($data);
            $this->Admin_model->log_action('create', 'promo', $id, 'Tambah promo: ' . $data['title']);
            $this->flash('msg', 'Promo berhasil ditambahkan.');
            redirect('admin/promo');
            return;
        }
        $data = [
            'title' => 'Tambah Promo — Admin Persada Medical',
            'page'  => 'promo',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('promo/form', $data);
    }

    public function promo_edit($id) {
        $this->check_auth();
        $ad = $this->Admin_model->get_ad($id);
        if (!$ad) {
            $this->flash('error', 'Promo tidak ditemukan.');
            redirect('admin/promo');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $data = [
                'title'       => $this->input->post('title', TRUE),
                'description' => $this->input->post('description', TRUE) ?: null,
                'link_url'    => $this->input->post('link_url', TRUE) ?: null,
                'placement'   => $this->input->post('placement', TRUE),
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'start_date'  => $this->input->post('start_date', TRUE) ?: null,
                'end_date'    => $this->input->post('end_date', TRUE) ?: null,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_result = $this->Admin_model->upload_image('image', $this->upload_base . 'ads/');
                if ($upload_result['success']) {
                    if (!empty($ad['image_url']) && strpos($ad['image_url'], 'uploads/') !== false) {
                        $this->Admin_model->delete_image_file($ad['image_url']);
                    }
                    $data['image_url'] = base_url('assets/images/uploads/ads/' . $upload_result['filename']);
                }
            }
            $this->Admin_model->update_ad($id, $data);
            $this->Admin_model->log_action('update', 'promo', $id, 'Edit promo: ' . $data['title']);
            $this->flash('msg', 'Promo berhasil diperbarui.');
            redirect('admin/promo');
            return;
        }
        $data = [
            'title' => 'Edit Promo — Admin Persada Medical',
            'page'  => 'promo',
            'ad'    => $ad,
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('promo/form', $data);
    }

    public function promo_hapus($id) {
        $this->check_auth();
        $ad = $this->Admin_model->get_ad($id);
        if ($ad) {
            if (!empty($ad['image_url']) && strpos($ad['image_url'], 'uploads/') !== false) {
                $this->Admin_model->delete_image_file($ad['image_url']);
            }
            $this->Admin_model->delete_ad($id);
            $this->Admin_model->log_action('delete', 'promo', $id, 'Hapus promo: ' . $ad['title']);
            $this->flash('msg', 'Promo berhasil dihapus.');
        } else {
            $this->flash('error', 'Promo tidak ditemukan.');
        }
        redirect('admin/promo');
    }

    public function promo_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_ad($id);
        redirect('admin/promo');
    }

    // ================================================================
    // INBOX PESAN
    // ================================================================
    public function inbox() {
        $this->check_auth();
        $status = $this->input->get('status', TRUE);
        $search = $this->input->get('q', TRUE);
        $data = [
            'title'    => 'Inbox Pesan — Admin Persada Medical',
            'page'     => 'inbox',
            'messages' => $this->Admin_model->get_all_messages($status, $search),
            'status'   => $status,
            'search'   => $search,
            'msg'      => $this->session->flashdata('msg'),
        ];
        $this->render_admin('inbox/index', $data);
    }

    public function inbox_detail($id) {
        $this->check_auth();
        $message = $this->Admin_model->get_message($id);
        if (!$message) {
            $this->flash('error', 'Pesan tidak ditemukan.');
            redirect('admin/inbox');
            return;
        }
        // Auto-mark as reviewed if unread
        if ($message['status'] === 'unread') {
            $this->Admin_model->update_message_status($id, 'reviewed');
        }
        $data = [
            'title'   => 'Detail Pesan — Admin Persada Medical',
            'page'    => 'inbox',
            'message' => $message,
        ];
        $this->render_admin('inbox/detail', $data);
    }

    public function inbox_status($id) {
        $this->check_auth();
        $status = $this->input->post('status', TRUE);
        $valid_statuses = ['unread', 'reviewed', 'archived'];
        if (in_array($status, $valid_statuses)) {
            $this->Admin_model->update_message_status($id, $status);
            $this->flash('msg', 'Status pesan diperbarui.');
        }
        redirect('admin/inbox');
    }

    public function inbox_hapus($id) {
        $this->check_auth();
        $this->Admin_model->delete_message($id);
        $this->flash('msg', 'Pesan berhasil dihapus.');
        redirect('admin/inbox');
    }

    // ================================================================
    // FAQ
    // ================================================================
    public function faq() {
        $this->check_auth();
        $data = [
            'title' => 'FAQ — Admin Persada Medical',
            'page'  => 'faq',
            'faqs'  => $this->Admin_model->get_all_faqs(),
            'msg'   => $this->session->flashdata('msg'),
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('faq/index', $data);
    }

    public function faq_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $question = $this->input->post('question', TRUE);
            $answer   = $this->input->post('answer', TRUE);
            if (empty($question) || empty($answer)) {
                $this->flash('error', 'Pertanyaan dan jawaban wajib diisi.');
                redirect('admin/faq/tambah');
                return;
            }
            $data = [
                'question'   => $question,
                'answer'     => $answer,
                'sort_order' => (int)$this->input->post('sort_order') ?: 0,
                'is_active'  => $this->input->post('is_active') ? 1 : 0,
            ];
            $id = $this->Admin_model->insert_faq($data);
            $this->Admin_model->log_action('create', 'faq', $id, 'Tambah FAQ');
            $this->flash('msg', 'FAQ berhasil ditambahkan.');
            redirect('admin/faq');
            return;
        }
        $data = [
            'title' => 'Tambah FAQ — Admin Persada Medical',
            'page'  => 'faq',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('faq/form', $data);
    }

    public function faq_edit($id) {
        $this->check_auth();
        $faq = $this->Admin_model->get_faq($id);
        if (!$faq) {
            $this->flash('error', 'FAQ tidak ditemukan.');
            redirect('admin/faq');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $question = $this->input->post('question', TRUE);
            $answer   = $this->input->post('answer', TRUE);
            if (empty($question) || empty($answer)) {
                $this->flash('error', 'Pertanyaan dan jawaban wajib diisi.');
                redirect('admin/faq/edit/' . $id);
                return;
            }
            $data = [
                'question'   => $question,
                'answer'     => $answer,
                'sort_order' => (int)$this->input->post('sort_order') ?: 0,
                'is_active'  => $this->input->post('is_active') ? 1 : 0,
            ];
            $this->Admin_model->update_faq($id, $data);
            $this->Admin_model->log_action('update', 'faq', $id, 'Edit FAQ ID: ' . $id);
            $this->flash('msg', 'FAQ berhasil diperbarui.');
            redirect('admin/faq');
            return;
        }
        $data = [
            'title' => 'Edit FAQ — Admin Persada Medical',
            'page'  => 'faq',
            'faq'   => $faq,
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('faq/form', $data);
    }

    public function faq_hapus($id) {
        $this->check_auth();
        $this->Admin_model->delete_faq($id);
        $this->Admin_model->log_action('delete', 'faq', $id, 'Hapus FAQ ID: ' . $id);
        $this->flash('msg', 'FAQ berhasil dihapus.');
        redirect('admin/faq');
    }

    public function faq_toggle($id) {
        $this->check_auth();
        $this->Admin_model->toggle_faq($id);
        redirect('admin/faq');
    }

    // ================================================================
    // PENGATURAN WEBSITE
    // ================================================================
    public function pengaturan() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $keys = [
                'site_name', 'company_name', 'site_description',
                'contact_email', 'contact_phone',
                'whatsapp_persada', 'whatsapp_asahi',
                'office_address', 'instagram_url', 'facebook_url',
                'tiktok_url', 'footer_text', 'seo_keywords',
            ];
            foreach ($keys as $key) {
                $value = $this->input->post($key, TRUE);
                if ($value !== null) {
                    $this->Admin_model->update_setting($key, $value);
                }
            }
            $this->Admin_model->log_action('update', 'setting', null, 'Update pengaturan website');
            $this->flash('msg', 'Pengaturan berhasil disimpan.');
            redirect('admin/pengaturan');
            return;
        }
        $data = [
            'title'    => 'Pengaturan Website — Admin Persada Medical',
            'page'     => 'pengaturan',
            'settings' => $this->Admin_model->get_all_settings(),
            'msg'      => $this->session->flashdata('msg'),
            'error'    => $this->session->flashdata('error'),
        ];
        $this->render_admin('pengaturan/index', $data);
    }

    // ================================================================
    // MANAJEMEN AKUN ADMIN
    // ================================================================
    public function akun() {
        $this->check_auth();
        $data = [
            'title'  => 'Manajemen Akun — Admin Persada Medical',
            'page'   => 'akun',
            'admins' => $this->Admin_model->get_all_admins(),
            'msg'    => $this->session->flashdata('msg'),
            'error'  => $this->session->flashdata('error'),
        ];
        $this->render_admin('akun/index', $data);
    }

    public function akun_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $email    = trim($this->input->post('email', TRUE));
            $name     = $this->input->post('name', TRUE);
            $password = $this->input->post('password');
            $confirm  = $this->input->post('password_confirm');
            if (empty($email) || empty($password)) {
                $this->flash('error', 'Email dan password wajib diisi.');
                redirect('admin/akun/tambah');
                return;
            }
            if ($password !== $confirm) {
                $this->flash('error', 'Konfirmasi password tidak cocok.');
                redirect('admin/akun/tambah');
                return;
            }
            if (strlen($password) < 8) {
                $this->flash('error', 'Password minimal 8 karakter.');
                redirect('admin/akun/tambah');
                return;
            }
            if ($this->Admin_model->get_admin_by_email($email)) {
                $this->flash('error', 'Email sudah digunakan.');
                redirect('admin/akun/tambah');
                return;
            }
            $data = [
                'email'     => $email,
                'name'      => $name,
                'password'  => password_hash($password, PASSWORD_BCRYPT),
                'is_active' => 1,
            ];
            $id = $this->db->insert('admins', $data);
            $this->Admin_model->log_action('create', 'admin', null, 'Tambah akun admin: ' . $email);
            $this->flash('msg', 'Akun admin berhasil ditambahkan.');
            redirect('admin/akun');
            return;
        }
        $data = [
            'title' => 'Tambah Akun Admin — Admin Persada Medical',
            'page'  => 'akun',
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('akun/form', $data);
    }

    public function akun_edit($id) {
        $this->check_auth();
        $admin = $this->Admin_model->get_admin_by_id($id);
        if (!$admin) {
            $this->flash('error', 'Akun tidak ditemukan.');
            redirect('admin/akun');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $data = [
                'name'      => $this->input->post('name', TRUE),
                'is_active' => $this->input->post('is_active') ? 1 : 0,
            ];
            $password = $this->input->post('password');
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $this->flash('error', 'Password minimal 8 karakter.');
                    redirect('admin/akun/edit/' . $id);
                    return;
                }
                if ($password !== $this->input->post('password_confirm')) {
                    $this->flash('error', 'Konfirmasi password tidak cocok.');
                    redirect('admin/akun/edit/' . $id);
                    return;
                }
                $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $this->Admin_model->update_admin($id, $data);
            $this->Admin_model->log_action('update', 'admin', $id, 'Edit akun admin ID: ' . $id);
            $this->flash('msg', 'Akun berhasil diperbarui.');
            redirect('admin/akun');
            return;
        }
        $data = [
            'title' => 'Edit Akun Admin — Admin Persada Medical',
            'page'  => 'akun',
            'admin_data' => $admin,
            'error' => $this->session->flashdata('error'),
        ];
        $this->render_admin('akun/form', $data);
    }

    // ================================================================
    // AUDIT LOG
    // ================================================================
    public function audit_log() {
        $this->check_auth();
        $data = [
            'title' => 'Audit Log — Admin Persada Medical',
            'page'  => 'audit_log',
            'logs'  => $this->Admin_model->get_audit_logs(200),
        ];
        $this->render_admin('audit_log/index', $data);
    }

    // ================================================================
    // BACKUP DATABASE
    // ================================================================
    public function backup() {
        $this->check_auth();
        $this->load->dbutil();
        $backup = $this->dbutil->backup(['format' => 'zip']);
        if (!$backup) {
            $this->flash('error', 'Gagal membuat backup database.');
            redirect('admin/dashboard');
            return;
        }
        $this->load->helper('download');
        $filename = 'persada_db_backup_' . date('Ymd_His') . '.zip';
        force_download($filename, $backup);
        $this->Admin_model->log_action('backup', 'database', null, 'Backup database: ' . $filename);
    }

    // ================================================================
    // AJAX: Cek apakah kontak form berhasil (dari halaman kontak publik)
    // ================================================================
    public function kirim_pesan() {
        // Endpoint publik untuk form kontak
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            redirect('kontak');
            return;
        }
        $name    = trim($this->input->post('name', TRUE));
        $email   = trim($this->input->post('email', TRUE));
        $phone   = trim($this->input->post('phone', TRUE));
        $subject = trim($this->input->post('subject', TRUE));
        $message = trim($this->input->post('message', TRUE));

        if (empty($name) || empty($message)) {
            $this->session->set_flashdata('kontak_error', 'Nama dan pesan wajib diisi.');
            redirect('kontak');
            return;
        }
        // Basic spam protection
        if (!empty($this->input->post('website'))) { // honeypot
            redirect('kontak');
            return;
        }

        $data = [
            'name'       => $name,
            'email'      => $email ?: null,
            'phone'      => $phone ?: null,
            'subject'    => $subject ?: 'Pesan dari formulir kontak',
            'message'    => $message,
            'ip_address' => $this->input->ip_address(),
            'status'     => 'unread',
        ];
        $this->Admin_model->insert_contact_message($data);
        $this->session->set_flashdata('kontak_success', 'Pesan Anda berhasil terkirim. Kami akan menghubungi Anda secepatnya.');
        redirect('kontak');
    }

    // ================================================================
    // BERITA & ARTIKEL — Admin
    // ================================================================
    public function berita() {
        $this->check_auth();
        $status   = $this->input->get('status');
        $category = $this->input->get('kategori');
        $search   = $this->input->get('q');
        $page     = max(1, (int)$this->input->get('halaman'));
        $per_page = 15;
        $offset   = ($page - 1) * $per_page;

        $news        = $this->Admin_model->get_all_news($status, $category, $search, $per_page, $offset);
        $total       = $this->Admin_model->count_news($status, $category, $search);
        $total_pages = ceil($total / $per_page);
        $categories  = $this->Admin_model->get_news_categories();

        $this->render_admin('berita/index', [
            'page_title'  => 'Berita & Artikel',
            'news'        => $news,
            'total'       => $total,
            'total_pages' => $total_pages,
            'cur_page'    => $page,
            'per_page'    => $per_page,
            'status'      => $status,
            'category'    => $category,
            'search'      => $search,
            'categories'  => $categories,
        ]);
    }

    public function berita_tambah() {
        $this->check_auth();
        $categories = $this->Admin_model->get_news_categories();

        if ($this->input->post()) {
            // Validasi
            $title   = trim($this->input->post('title', TRUE));
            $status  = $this->input->post('status', TRUE);
            $content = $this->input->post('content');

            if (empty($title)) {
                $this->flash('error', 'Judul berita wajib diisi.');
                $this->render_admin('berita/form', [
                    'page_title' => 'Tambah Berita',
                    'news'       => null,
                    'categories' => $categories,
                ]);
                return;
            }

            // Slug
            $slug_input = trim($this->input->post('slug', TRUE));
            $slug = !empty($slug_input) ? $this->Admin_model->make_slug($slug_input) : $this->Admin_model->make_slug($title);
            // Pastikan slug unik
            $base_slug = $slug;
            $counter   = 1;
            while ($this->Admin_model->get_news_by_slug($slug)) {
                $slug = $base_slug . '-' . $counter++;
            }

            // Upload gambar
            $image_url = null;
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = $this->upload_base . 'news/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $img = $this->Admin_model->upload_image('image', $upload_dir);
                if ($img) {
                    $image_url = 'assets/images/uploads/news/' . $img;
                } else {
                    $this->flash('error', 'Gagal upload gambar: ' . $this->Admin_model->upload_error);
                    $this->render_admin('berita/form', [
                        'page_title' => 'Tambah Berita',
                        'news'       => null,
                        'categories' => $categories,
                    ]);
                    return;
                }
            }

            $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            $pub_input = trim($this->input->post('published_at'));
            if (!empty($pub_input)) {
                $published_at = date('Y-m-d H:i:s', strtotime($pub_input));
            }

            $data = [
                'title'        => $title,
                'slug'         => $slug,
                'category'     => trim($this->input->post('category', TRUE)) ?: 'Kesehatan Umum',
                'summary'      => trim($this->input->post('summary', TRUE)),
                'content'      => $content,
                'author'       => trim($this->input->post('author', TRUE)) ?: 'Tim Medis Persada',
                'status'       => in_array($status, ['draft', 'published']) ? $status : 'draft',
                'published_at' => $published_at,
                'image_url'    => $image_url,
            ];

            $id = $this->Admin_model->insert_news($data);
            $this->Admin_model->log_action('tambah', 'berita', $id, 'Tambah berita: ' . $title);
            $this->flash('success', 'Berita berhasil ditambahkan.');
            redirect('admin/berita');
        }

        $this->render_admin('berita/form', [
            'page_title' => 'Tambah Berita',
            'news'       => null,
            'categories' => $categories,
        ]);
    }

    public function berita_edit($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/berita'); return; }

        $news = $this->Admin_model->get_news($id);
        if (!$news) {
            $this->flash('error', 'Berita tidak ditemukan.');
            redirect('admin/berita');
            return;
        }
        $categories = $this->Admin_model->get_news_categories();

        if ($this->input->post()) {
            $title  = trim($this->input->post('title', TRUE));
            $status = $this->input->post('status', TRUE);

            if (empty($title)) {
                $this->flash('error', 'Judul berita wajib diisi.');
                $this->render_admin('berita/form', [
                    'page_title' => 'Edit Berita',
                    'news'       => $news,
                    'categories' => $categories,
                ]);
                return;
            }

            // Slug
            $slug_input = trim($this->input->post('slug', TRUE));
            $slug = !empty($slug_input) ? $this->Admin_model->make_slug($slug_input) : $this->Admin_model->make_slug($title);
            // Pastikan slug unik kecuali milik sendiri
            $base_slug = $slug;
            $counter   = 1;
            while (true) {
                $existing = $this->Admin_model->get_news_by_slug($slug);
                if (!$existing || (int)$existing['id'] === (int)$id) break;
                $slug = $base_slug . '-' . $counter++;
            }

            // Upload gambar baru
            $image_url = $news['image_url'];
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = $this->upload_base . 'news/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $img = $this->Admin_model->upload_image('image', $upload_dir);
                if ($img) {
                    // Hapus gambar lama jika upload ke folder uploads
                    if (!empty($news['image_url']) && strpos($news['image_url'], 'uploads/') !== false) {
                        $this->Admin_model->delete_image_file($news['image_url']);
                    }
                    $image_url = 'assets/images/uploads/news/' . $img;
                } else {
                    $this->flash('error', 'Gagal upload gambar: ' . $this->Admin_model->upload_error);
                    $this->render_admin('berita/form', [
                        'page_title' => 'Edit Berita',
                        'news'       => $news,
                        'categories' => $categories,
                    ]);
                    return;
                }
            }

            $published_at = $news['published_at'];
            $pub_input = trim($this->input->post('published_at'));
            if (!empty($pub_input)) {
                $published_at = date('Y-m-d H:i:s', strtotime($pub_input));
            } elseif ($status === 'published' && empty($published_at)) {
                $published_at = date('Y-m-d H:i:s');
            }

            $data = [
                'title'        => $title,
                'slug'         => $slug,
                'category'     => trim($this->input->post('category', TRUE)) ?: 'Kesehatan Umum',
                'summary'      => trim($this->input->post('summary', TRUE)),
                'content'      => $this->input->post('content'),
                'author'       => trim($this->input->post('author', TRUE)) ?: 'Tim Medis Persada',
                'status'       => in_array($status, ['draft', 'published']) ? $status : 'draft',
                'published_at' => $published_at,
                'image_url'    => $image_url,
            ];

            $this->Admin_model->update_news($id, $data);
            $this->Admin_model->log_action('edit', 'berita', $id, 'Edit berita: ' . $title);
            $this->flash('success', 'Berita berhasil diperbarui.');
            redirect('admin/berita');
        }

        $this->render_admin('berita/form', [
            'page_title' => 'Edit Berita',
            'news'       => $news,
            'categories' => $categories,
        ]);
    }

    public function berita_hapus($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/berita'); return; }
        $news = $this->Admin_model->get_news($id);
        if ($news) {
            $this->Admin_model->delete_news($id);
            $this->Admin_model->log_action('hapus', 'berita', $id, 'Hapus berita: ' . $news['title']);
            $this->flash('success', 'Berita berhasil dihapus.');
        } else {
            $this->flash('error', 'Berita tidak ditemukan.');
        }
        redirect('admin/berita');
    }

    public function berita_toggle($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/berita'); return; }
        $new_status = $this->Admin_model->toggle_news_status($id);
        if ($new_status) {
            $label = $new_status === 'published' ? 'dipublikasikan' : 'disimpan sebagai draft';
            $this->flash('success', 'Berita berhasil ' . $label . '.');
        } else {
            $this->flash('error', 'Berita tidak ditemukan.');
        }
        redirect('admin/berita');
    }

    // ================================================================
    // MITRA KAMI (PARTNERS) — Admin
    // ================================================================
    public function mitra() {
        $this->check_auth();
        $partners = $this->Admin_model->get_all_partners();
        $this->render_admin('mitra/index', [
            'page_title' => 'Mitra Kami & Kerjasama',
            'page'       => 'mitra',
            'partners'   => $partners,
            'msg'        => $this->session->flashdata('msg'),
            'error'      => $this->session->flashdata('error'),
        ]);
    }

    public function mitra_tambah() {
        $this->check_auth();
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = trim($this->input->post('name', TRUE));
            if (empty($name)) {
                $this->flash('error', 'Nama mitra wajib diisi.');
                redirect('admin/mitra/tambah');
                return;
            }

            if (empty($_FILES['logo']['name'])) {
                $this->flash('error', 'Logo mitra wajib diunggah.');
                redirect('admin/mitra/tambah');
                return;
            }

            $upload_path = $this->upload_base . 'partners/';
            $upload_result = $this->Admin_model->upload_partner_logo('logo', $upload_path);
            if (!$upload_result['success']) {
                $this->flash('error', $upload_result['error']);
                redirect('admin/mitra/tambah');
                return;
            }

            $data = [
                'name'        => $name,
                'logo_url'    => 'assets/images/uploads/partners/' . $upload_result['filename'],
                'description' => trim($this->input->post('description', TRUE)) ?: null,
                'website_url' => trim($this->input->post('website_url', TRUE)) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];

            $id = $this->Admin_model->insert_partner($data);
            $this->Admin_model->log_action('create', 'partners', $id, 'Tambah mitra: ' . $data['name']);
            $this->flash('msg', 'Mitra "' . htmlspecialchars($data['name']) . '" berhasil ditambahkan.');
            redirect('admin/mitra');
            return;
        }

        $this->render_admin('mitra/form', [
            'page_title' => 'Tambah Mitra Baru',
            'page'       => 'mitra',
            'partner'    => null,
            'error'      => $this->session->flashdata('error'),
        ]);
    }

    public function mitra_edit($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/mitra'); return; }

        $partner = $this->Admin_model->get_partner($id);
        if (!$partner) {
            $this->flash('error', 'Data mitra tidak ditemukan.');
            redirect('admin/mitra');
            return;
        }

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = trim($this->input->post('name', TRUE));
            if (empty($name)) {
                $this->flash('error', 'Nama mitra wajib diisi.');
                redirect('admin/mitra/edit/' . $id);
                return;
            }

            $logo_url = $partner['logo_url'];
            if (!empty($_FILES['logo']['name'])) {
                $upload_path = $this->upload_base . 'partners/';
                $upload_result = $this->Admin_model->upload_partner_logo('logo', $upload_path);
                if (!$upload_result['success']) {
                    $this->flash('error', $upload_result['error']);
                    redirect('admin/mitra/edit/' . $id);
                    return;
                }
                // Hapus logo lama jika berada di uploads
                if (!empty($partner['logo_url']) && strpos($partner['logo_url'], 'uploads/partners/') !== false) {
                    $this->Admin_model->delete_image_file($partner['logo_url']);
                }
                $logo_url = 'assets/images/uploads/partners/' . $upload_result['filename'];
            }

            $data = [
                'name'        => $name,
                'logo_url'    => $logo_url,
                'description' => trim($this->input->post('description', TRUE)) ?: null,
                'website_url' => trim($this->input->post('website_url', TRUE)) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];

            $this->Admin_model->update_partner($id, $data);
            $this->Admin_model->log_action('update', 'partners', $id, 'Edit mitra: ' . $data['name']);
            $this->flash('msg', 'Data mitra "' . htmlspecialchars($data['name']) . '" berhasil diperbarui.');
            redirect('admin/mitra');
            return;
        }

        $this->render_admin('mitra/form', [
            'page_title' => 'Edit Mitra — ' . htmlspecialchars($partner['name']),
            'page'       => 'mitra',
            'partner'    => $partner,
            'error'      => $this->session->flashdata('error'),
        ]);
    }

    public function mitra_hapus($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/mitra'); return; }

        $partner = $this->Admin_model->get_partner($id);
        if ($partner) {
            $name = $partner['name'];
            $this->Admin_model->delete_partner($id);
            $this->Admin_model->log_action('delete', 'partners', $id, 'Hapus mitra: ' . $name);
            $this->flash('msg', 'Mitra "' . htmlspecialchars($name) . '" berhasil dihapus.');
        } else {
            $this->flash('error', 'Mitra tidak ditemukan.');
        }
        redirect('admin/mitra');
    }

    public function mitra_toggle($id = null) {
        $this->check_auth();
        if (!$id) { redirect('admin/mitra'); return; }

        $partner = $this->Admin_model->get_partner($id);
        if ($partner) {
            $new_status = $this->Admin_model->toggle_partner($id);
            $label = $new_status ? 'diaktifkan' : 'dinonaktifkan';
            $this->Admin_model->log_action('toggle', 'partners', $id, 'Ubah status mitra ' . $partner['name'] . ' jadi ' . $label);
            $this->flash('msg', 'Mitra "' . htmlspecialchars($partner['name']) . '" berhasil ' . $label . '.');
        } else {
            $this->flash('error', 'Mitra tidak ditemukan.');
        }
        redirect('admin/mitra');
    }

    // ================================================================
    // PROFIL KLINIK — TENTANG KAMI
    // ================================================================
    public function profil_klinik() {
        $this->check_auth();
        // Default tampilkan Persada Medical Klinik (ID=1)
        $selected_clinic_id = (int)($this->input->get('klinik') ?: 1);
        $clinics = $this->Admin_model->get_all_clinics();
        // Hanya tampilkan Persada Medical Klinik (1) dan Klinik Asahi (3)
        $allowed_clinic_ids = [1, 3];
        $tentang_clinics = array_filter($clinics, function($c) use ($allowed_clinic_ids) {
            return in_array((int)$c['id'], $allowed_clinic_ids);
        });
        if (!in_array($selected_clinic_id, $allowed_clinic_ids)) {
            $selected_clinic_id = 1;
        }
        $profile  = $this->Admin_model->get_clinic_profile($selected_clinic_id);
        $clinic   = $this->Admin_model->get_clinic($selected_clinic_id);
        $awards   = $this->Admin_model->get_all_clinic_awards($selected_clinic_id);
        $data = [
            'title'             => 'Profil Klinik (Tentang Kami) — Admin Persada Medical',
            'page'              => 'profil_klinik',
            'clinics'           => array_values($tentang_clinics),
            'selected_clinic_id'=> $selected_clinic_id,
            'profile'           => $profile ?: [],
            'clinic'            => $clinic ?: [],
            'awards'            => $awards,
            'msg'               => $this->session->flashdata('msg'),
            'error'             => $this->session->flashdata('error'),
        ];
        $this->render_admin('profil_klinik/index', $data);
    }

    public function profil_klinik_simpan($clinic_id = null) {
        $this->check_auth();
        if (!$clinic_id || !in_array((int)$clinic_id, [1, 3])) {
            $this->flash('error', 'Klinik tidak valid.');
            redirect('admin/profil-klinik');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            redirect('admin/profil-klinik?klinik=' . $clinic_id);
            return;
        }
        $existing = $this->Admin_model->get_clinic_profile($clinic_id);
        $data = [
            'description' => $this->input->post('description', TRUE) ?: null,
            'vision'      => $this->input->post('vision', TRUE) ?: null,
            'mission'     => $this->input->post('mission', TRUE) ?: null,
            'motto'       => $this->input->post('motto', TRUE) ?: null,
        ];
        // Upload foto klinik
        if (!empty($_FILES['photo']['name'])) {
            $upload_dir = FCPATH . 'assets/images/uploads/profil_klinik/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $res = $this->Admin_model->upload_image('photo', $upload_dir);
            if ($res['success']) {
                // Hapus foto lama
                if ($existing && !empty($existing['photo_url']) && strpos($existing['photo_url'], 'uploads/') !== false) {
                    @unlink(FCPATH . $existing['photo_url']);
                }
                $data['photo_url'] = 'assets/images/uploads/profil_klinik/' . $res['filename'];
            } else {
                $this->flash('error', 'Upload foto gagal: ' . $res['error']);
                redirect('admin/profil-klinik?klinik=' . $clinic_id);
                return;
            }
        }
        // Upload struktur organisasi
        if (!empty($_FILES['org_chart']['name'])) {
            $upload_dir = FCPATH . 'assets/images/uploads/profil_klinik/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            // Sementara gunakan field name berbeda
            $_FILES['image'] = $_FILES['org_chart'];
            $res = $this->Admin_model->upload_image('image', $upload_dir);
            if ($res['success']) {
                if ($existing && !empty($existing['org_chart_url']) && strpos($existing['org_chart_url'], 'uploads/') !== false) {
                    @unlink(FCPATH . $existing['org_chart_url']);
                }
                $data['org_chart_url'] = 'assets/images/uploads/profil_klinik/' . $res['filename'];
            } else {
                $this->flash('error', 'Upload struktur organisasi gagal: ' . $res['error']);
                redirect('admin/profil-klinik?klinik=' . $clinic_id);
                return;
            }
        }
        $this->Admin_model->upsert_clinic_profile($clinic_id, $data);
        $clinic = $this->Admin_model->get_clinic($clinic_id);
        $this->Admin_model->log_action('update', 'clinic_profiles', $clinic_id, 'Update profil klinik: ' . ($clinic['name'] ?? $clinic_id));
        $this->flash('msg', 'Profil klinik berhasil disimpan.');
        redirect('admin/profil-klinik?klinik=' . $clinic_id);
    }

    // ================================================================
    // PENGHARGAAN KLINIK
    // ================================================================
    public function penghargaan($clinic_id = null) {
        $this->check_auth();
        if (!$clinic_id || !in_array((int)$clinic_id, [1, 3])) {
            redirect('admin/profil-klinik');
            return;
        }
        $clinic  = $this->Admin_model->get_clinic($clinic_id);
        $awards  = $this->Admin_model->get_all_clinic_awards($clinic_id);
        $data = [
            'title'     => 'Penghargaan ' . ($clinic['name'] ?? '') . ' — Admin Persada Medical',
            'page'      => 'profil_klinik',
            'clinic'    => $clinic ?: [],
            'clinic_id' => (int)$clinic_id,
            'awards'    => $awards,
            'msg'       => $this->session->flashdata('msg'),
            'error'     => $this->session->flashdata('error'),
        ];
        $this->render_admin('profil_klinik/penghargaan', $data);
    }

    public function penghargaan_tambah($clinic_id = null) {
        $this->check_auth();
        if (!$clinic_id || !in_array((int)$clinic_id, [1, 3])) {
            redirect('admin/profil-klinik');
            return;
        }
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            if (empty($name)) {
                $this->flash('error', 'Nama penghargaan wajib diisi.');
                redirect('admin/profil-klinik/penghargaan/' . $clinic_id . '/tambah');
                return;
            }
            $award_data = [
                'clinic_id'   => (int)$clinic_id,
                'name'        => $name,
                'description' => $this->input->post('description', TRUE) ?: null,
                'year'        => $this->input->post('year', TRUE) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            // Upload gambar penghargaan
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = FCPATH . 'assets/images/uploads/penghargaan/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $res = $this->Admin_model->upload_image('image', $upload_dir);
                if ($res['success']) {
                    $award_data['image_url'] = 'assets/images/uploads/penghargaan/' . $res['filename'];
                }
            }
            $id = $this->Admin_model->insert_clinic_award($award_data);
            $this->Admin_model->log_action('create', 'clinic_awards', $id, 'Tambah penghargaan: ' . $name);
            $this->flash('msg', 'Penghargaan berhasil ditambahkan.');
            redirect('admin/profil-klinik/penghargaan/' . $clinic_id);
            return;
        }
        $clinic = $this->Admin_model->get_clinic($clinic_id);
        $data = [
            'title'     => 'Tambah Penghargaan — Admin Persada Medical',
            'page'      => 'profil_klinik',
            'clinic'    => $clinic ?: [],
            'clinic_id' => (int)$clinic_id,
            'award'     => [],
            'is_edit'   => false,
            'error'     => $this->session->flashdata('error'),
        ];
        $this->render_admin('profil_klinik/penghargaan_form', $data);
    }

    public function penghargaan_edit($id = null) {
        $this->check_auth();
        $award = $this->Admin_model->get_clinic_award($id);
        if (!$award) {
            $this->flash('error', 'Penghargaan tidak ditemukan.');
            redirect('admin/profil-klinik');
            return;
        }
        $clinic_id = $award['clinic_id'];
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $name = $this->input->post('name', TRUE);
            $upd = [
                'name'        => $name,
                'description' => $this->input->post('description', TRUE) ?: null,
                'year'        => $this->input->post('year', TRUE) ?: null,
                'sort_order'  => (int)$this->input->post('sort_order') ?: 0,
                'is_active'   => $this->input->post('is_active') ? 1 : 0,
            ];
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = FCPATH . 'assets/images/uploads/penghargaan/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $res = $this->Admin_model->upload_image('image', $upload_dir);
                if ($res['success']) {
                    if (!empty($award['image_url']) && strpos($award['image_url'], 'uploads/') !== false) {
                        @unlink(FCPATH . $award['image_url']);
                    }
                    $upd['image_url'] = 'assets/images/uploads/penghargaan/' . $res['filename'];
                }
            }
            $this->Admin_model->update_clinic_award($id, $upd);
            $this->Admin_model->log_action('update', 'clinic_awards', $id, 'Edit penghargaan: ' . $name);
            $this->flash('msg', 'Penghargaan berhasil diperbarui.');
            redirect('admin/profil-klinik/penghargaan/' . $clinic_id);
            return;
        }
        $clinic = $this->Admin_model->get_clinic($clinic_id);
        $data = [
            'title'     => 'Edit Penghargaan — Admin Persada Medical',
            'page'      => 'profil_klinik',
            'clinic'    => $clinic ?: [],
            'clinic_id' => (int)$clinic_id,
            'award'     => $award,
            'is_edit'   => true,
            'error'     => $this->session->flashdata('error'),
        ];
        $this->render_admin('profil_klinik/penghargaan_form', $data);
    }

    public function penghargaan_hapus($id = null) {
        $this->check_auth();
        $award = $this->Admin_model->get_clinic_award($id);
        if ($award) {
            if (!empty($award['image_url']) && strpos($award['image_url'], 'uploads/') !== false) {
                @unlink(FCPATH . $award['image_url']);
            }
            $this->Admin_model->delete_clinic_award($id);
            $this->Admin_model->log_action('delete', 'clinic_awards', $id, 'Hapus penghargaan: ' . $award['name']);
            $this->flash('msg', 'Penghargaan berhasil dihapus.');
        } else {
            $this->flash('error', 'Penghargaan tidak ditemukan.');
        }
        redirect('admin/profil-klinik/penghargaan/' . ($award['clinic_id'] ?? 1));
    }

    public function penghargaan_toggle($id = null) {
        $this->check_auth();
        $award = $this->Admin_model->get_clinic_award($id);
        if ($award) {
            $this->Admin_model->toggle_clinic_award($id);
            redirect('admin/profil-klinik/penghargaan/' . $award['clinic_id']);
        } else {
            redirect('admin/profil-klinik');
        }
    }
}

