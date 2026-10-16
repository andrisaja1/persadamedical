<?php
defined("BASEPATH") OR exit("No direct script access allowed");

class Pages extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model("Site_model");
    }

    private function render($view, $data) {
        $this->load->view("templates/header", $data);
        $this->load->view("pages/" . $view, $data);
        $this->load->view("templates/footer", $data);
    }

    public function tentang() {
        redirect('tentang/persada-medical');
    }

    public function tentang_persada() {
        $this->tentang_klinik_detail(1);
    }

    public function tentang_asahi() {
        $this->tentang_klinik_detail(3);
    }

    private function tentang_klinik_detail($clinic_id) {
        $clinic = $this->Site_model->get_clinic_by_id($clinic_id);
        if (!$clinic) {
            show_404();
            return;
        }
        $profile = $this->Site_model->get_clinic_profile($clinic_id);
        $awards = $this->Site_model->get_clinic_awards($clinic_id);
        $facilities = $this->Site_model->get_clinic_facilities($clinic_id);

        $clinic_name = $clinic['name'];
        $page_title = 'Tentang ' . $clinic_name . ' — PT Persada Medis Indonesia';
        $desc = !empty($profile['description']) 
            ? strip_tags($profile['description']) 
            : 'Profil resmi ' . $clinic_name . ', visi misi, struktur organisasi, dan penghargaan fasilitas pelayanan kesehatan.';

        $data = [
            'title'       => $page_title,
            'description' => mb_substr($desc, 0, 160),
            'clinic'      => $clinic,
            'clinic_id'   => (int)$clinic_id,
            'profile'     => $profile ?: [],
            'awards'      => $awards,
            'facilities'  => $facilities,
            'photos'      => $this->Site_model->get_photos(),
            'statistics'  => $this->Site_model->get_statistics(),
        ];
        $this->render('tentang_klinik', $data);
    }

    public function layanan() {
        $menu = $this->Site_model->get_services_menu();
        $target = !empty($menu[0]['slug']) ? $menu[0]['slug'] : 'poli-umum';
        redirect('layanan/' . $target);
    }

    public function layanan_detail($slug = '') {
        $service = $this->Site_model->get_service_by_slug($slug);
        if (!$service) {
            $menu = $this->Site_model->get_services_menu();
            $target = !empty($menu[0]['slug']) ? $menu[0]['slug'] : 'poli-umum';
            redirect('layanan/' . $target);
            return;
        }

        $catalog = $this->Site_model->get_all_services_catalog();
        
        $data = array(
            "title"          => $service['title'] . " — " . $service['unit_name'] . " | PT Persada Medis Indonesia",
            "description"    => $service['short_desc'],
            "photos"         => $this->Site_model->get_photos(),
            "service"        => $service,
            "services_menu"  => $this->Site_model->get_services_menu(),
            "catalog"        => $catalog,
        );
        $this->render("layanan_detail", $data);
    }


    public function dokter() {
        $data = array(
            "title"       => "Dokter & Tenaga Medis — Persada Medical",
            "description" => "Kenali dokter dan tenaga medis Persada Medical. Profil resmi dan jadwal praktik akan diperbarui.",
            "photos"      => $this->Site_model->get_photos(),
            "doctors"     => $this->Site_model->get_doctors(),
        );
        $this->render("dokter", $data);
    }

    public function klinik() {
        $data = array(
            "title"       => "Jaringan Klinik — Persada Medical",
            "description" => "Kenali Persada Medical sebagai klinik utama dan Klinik Asahi sebagai cabang kedua dalam jaringan kami.",
            "photos"      => $this->Site_model->get_photos(),
            "clinics"     => $this->Site_model->get_clinics(),
        );
        $this->render("klinik", $data);
    }

    public function klinik_asahi() {
        $data = array(
            "title"            => "Klinik Asahi — Bagian dari Persada Medical",
            "description"      => "Klinik Asahi, cabang kedua Persada Medical: layanan dokter, konsultasi, dan pemeriksaan kesehatan yang nyaman.",
            "photos"           => $this->Site_model->get_photos(),
            "clinics"          => $this->Site_model->get_clinics(),
            "doctors"          => $this->Site_model->get_doctors(),
            "asahi_services"   => $this->Site_model->get_asahi_services(),
            "asahi_statistics" => $this->Site_model->get_asahi_statistics(),
            "asahi_carousel"   => $this->Site_model->get_asahi_carousel(),
            "ads"              => $this->Site_model->get_ads("asahi"),
            "facilities"       => $this->Site_model->get_clinic_facilities(3),
        );
        $this->render("klinik_asahi", $data);
    }

    public function promo() {
        $category = $this->input->get('kategori', TRUE);
        $data = array(
            "title"       => "Promo & Penawaran Spesial — Persada Medical",
            "description" => "Jelajahi paket promo spesial dari Klinik Persada Medical, Aesthetic Care, dan Klinik Asahi. Ambil dan konsultasikan promo langsung via WhatsApp.",
            "photos"      => $this->Site_model->get_photos(),
            "promos"      => $this->Site_model->get_promos($category),
            "selected_cat"=> $category ? $category : 'all',
        );
        $this->render("promo", $data);
    }

    public function kontak() {
        $data = array(
            "title"        => "Hubungi Kami — Persada Medical",
            "description"  => "Hubungi Persada Medical untuk informasi mengenai layanan, klinik, dan fasilitas kami.",
            "photos"       => $this->Site_model->get_photos(),
            "clinics"      => $this->Site_model->get_clinics(),
            "success_msg"  => $this->session->flashdata('success_msg'),
            "error_msg"    => $this->session->flashdata('error_msg'),
        );
        $this->render("kontak", $data);
    }

    public function kontak_kirim() {
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            redirect('kontak');
            return;
        }

        $name = trim((string)$this->input->post('name', TRUE));
        $message = trim((string)$this->input->post('message', TRUE));

        if (empty($name) || empty($message)) {
            $this->session->set_flashdata('error_msg', 'Nama dan pesan wajib diisi.');
            redirect('kontak#form-pesan');
            return;
        }

        $email = trim((string)$this->input->post('email', TRUE));
        $phone = trim((string)$this->input->post('phone', TRUE));
        $subject = trim((string)$this->input->post('subject', TRUE)) ?: 'Pesan dari Formulir Kontak Website';

        $data = [
            'name'       => $name,
            'email'      => $email ?: null,
            'phone'      => $phone ?: null,
            'subject'    => $subject,
            'message'    => $message,
            'status'     => 'unread',
            'ip_address' => $this->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('contact_messages', $data);

        $this->session->set_flashdata('success_msg', 'Terima kasih, pesan Anda telah berhasil dikirim! Tim Persada Medical akan segera merespons Anda.');
        redirect('kontak#form-pesan');
    }

    public function artikel() {
        $data = array(
            "title"       => "Informasi & Insight — Persada Medical",
            "description" => "Informasi dan inspirasi mengenai kesehatan, gaya hidup sehat, dan perawatan kulit dari Persada Medical.",
            "photos"      => $this->Site_model->get_photos(),
            "articles"    => $this->Site_model->get_articles(),
        );
        $this->render("artikel", $data);
    }

    public function baca($id = 1) {
        $articles = $this->Site_model->get_articles();
        $index = (int)$id - 1;
        if (!isset($articles[$index])) {
            show_404();
            return;
        }
        $article = $articles[$index];
        $data = array(
            "title"       => $article["title"] . " — Persada Medical",
            "description" => $article["excerpt"],
            "article"     => $article,
        );
        $this->render("baca", $data);
    }

    public function profil_dokter($id = 1) {
        $doctor = $this->Site_model->get_doctor_by_id($id);
        if (!$doctor) {
            show_404();
            return;
        }
        $data = array(
            "title"       => $doctor["name"] . " — Persada Medical",
            "description" => !empty($doctor["bio"]) ? $doctor["bio"] : "Informasi profil dokter dan jadwal praktik Persada Medical.",
            "doctor"      => $doctor,
            "doctor_id"   => $id,
        );
        $this->render("profil_dokter", $data);
    }

    public function profil_klinik($id = 1) {
        $clinics = $this->Site_model->get_clinics();
        $index = (int)$id - 1;
        if (!isset($clinics[$index])) {
            show_404();
            return;
        }
        $clinic = $clinics[$index];
        // Aesthetic (id=2) and Persada Medical Klinik (id=1) use clinic_id=1 facilities
        $target_facility_clinic_id = ((int)$id === 3) ? 3 : 1;
        $facilities = $this->Site_model->get_clinic_facilities($target_facility_clinic_id);

        $data = array(
            "title"       => $clinic["name"] . " — Persada Medical",
            "description" => $clinic["description"],
            "clinic"      => $clinic,
            "clinic_id"   => $id,
            "facilities"  => $facilities,
        );
        $this->render("profil_klinik", $data);
    }

    // ================================================================
    // BERITA & ARTIKEL — Public Pages
    // ================================================================
    public function berita() {
        $search    = $this->input->get('q');
        $category  = $this->input->get('kategori');
        $page      = max(1, (int)$this->input->get('halaman'));
        $per_page  = 9;
        $offset    = ($page - 1) * $per_page;

        $news      = $this->Site_model->get_all_published_news($search, $category, $per_page, $offset);
        $total     = $this->Site_model->count_published_news($search, $category);
        $categories = $this->Site_model->get_news_categories_published();
        $total_pages = ceil($total / $per_page);

        $data = array(
            "title"       => "Berita & Artikel Kesehatan — Persada Medical",
            "description" => "Baca berita terbaru, artikel kesehatan, dan informasi layanan dari Persada Medical Klinik, Persada Medical Aesthetic, dan Klinik Asahi.",
            "photos"      => $this->Site_model->get_photos(),
            "news"        => $news,
            "total"       => $total,
            "page"        => $page,
            "per_page"    => $per_page,
            "total_pages" => $total_pages,
            "search"      => $search,
            "category"    => $category,
            "categories"  => $categories,
        );
        $this->render("berita", $data);
    }

    public function berita_detail($slug = '') {
        if (empty($slug)) {
            redirect('berita');
            return;
        }
        $news = $this->Site_model->get_news_by_slug($slug);
        if (!$news) {
            $data = array(
                "title"       => "Berita Tidak Ditemukan — Persada Medical",
                "description" => "Artikel yang Anda cari tidak tersedia.",
                "photos"      => $this->Site_model->get_photos(),
            );
            $this->load->view("templates/header", $data);
            $this->load->view("pages/berita_404", $data);
            $this->load->view("templates/footer", $data);
            return;
        }
        $related = $this->Site_model->get_related_news($news['id'], $news['category'], 3);
        $data = array(
            "title"       => $news['title'] . " — Persada Medical",
            "description" => !empty($news['summary']) ? strip_tags($news['summary']) : 'Baca artikel dari Persada Medical Indonesia.',
            "photos"      => $this->Site_model->get_photos(),
            "news"        => $news,
            "related"     => $related,
        );
        $this->render("berita_detail", $data);
    }
}

