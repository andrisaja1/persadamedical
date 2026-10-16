<?php
defined("BASEPATH") OR exit("No direct script access allowed");

class Home extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model("Site_model");
    }

    public function index() {
        $data = array(
            "title"       => "PT Persada Medis Indonesia — Profil Perusahaan & Layanan Kesehatan Terpadu",
            "description" => "Profil perusahaan resmi PT Persada Medis Indonesia. Ekosistem layanan kesehatan terpadu yang menaungi Persada Medical Klinik, Persada Medical Aesthetic, dan Klinik Asahi.",
            "photos"      => $this->Site_model->get_photos(),
            "announcements" => $this->Site_model->get_announcements(),
            "units"       => $this->Site_model->get_units(),
            "services"    => $this->Site_model->get_services(),
            "featured_services" => $this->Site_model->get_featured_services(),
            "doctors"     => $this->Site_model->get_doctors(),
            "clinics"     => $this->Site_model->get_clinics(),
            "articles"    => $this->Site_model->get_articles(),
            "statistics"  => $this->Site_model->get_statistics(),
            "gallery"     => $this->Site_model->get_gallery(),
            "home_carousel" => $this->Site_model->get_home_carousel(),
            "promos"      => $this->Site_model->get_promos(),
            "ads"         => $this->Site_model->get_ads("beranda"),
            "facilities_persada" => $this->Site_model->get_clinic_facilities(1),
            "facilities_asahi"   => $this->Site_model->get_clinic_facilities(3),
            "latest_news"        => $this->Site_model->get_latest_news(3),
            "partners"           => $this->Site_model->get_active_partners(),
        );
        $this->load->view("templates/header", $data);
        $this->load->view("pages/home", $data);
        $this->load->view("templates/footer", $data);
    }
}
