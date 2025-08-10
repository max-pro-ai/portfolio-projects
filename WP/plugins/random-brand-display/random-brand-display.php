<?php
/*
Plugin Name: Random Brand Display (PBN test)
Description: Плагин для вывода случайного бренда из папки logos/ через REST и шорткод [random_brand]
Version: 1.0
Author: Твое Имя
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class RBD_Plugin {
    public function __construct() {
        add_action('rest_api_init', array($this,'register_routes'));
        add_action('wp_enqueue_scripts', array($this,'enqueue_assets'));
        add_shortcode('random_brand', array($this,'shortcode'));
    }

    private function logos_dir_path() {
        return plugin_dir_path(__FILE__) . 'logos/';
    }
    private function logos_dir_url() {
        return plugin_dir_url(__FILE__) . 'logos/';
    }
    private function brands_file() {
        return $this->logos_dir_path() . 'brands.txt';
    }

    public function register_routes() {
        register_rest_route('rbd/v1','/brands', array(
            'methods' => 'GET',
            'callback' => array($this,'rest_get_brands'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_get_brands($request) {
        $file = $this->brands_file();
        if (!file_exists($file)) {
            return new WP_Error('no_file', 'Brands file not found', array('status' => 404));
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = array();

        foreach ($lines as $line) {
            // формат строки: logo.png | Описание бренда | https://brand-link.com
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) continue;

            $logo_file = sanitize_file_name($parts[0]);
            $desc = wp_strip_all_tags($parts[1]);
            $link = esc_url_raw($parts[2]);

            // только существующие файлы
            if (!file_exists($this->logos_dir_path() . $logo_file)) {
                // пропускаем, чтобы не возвращать несуществующие картинки
                continue;
            }

            $result[] = array(
                'logo' => $this->logos_dir_url() . $logo_file,
                'logo_file' => $logo_file,
                'desc' => $desc,
                'link' => $link,
            );
        }

        if (empty($result)) {
            return new WP_Error('no_entries', 'No valid brands found', array('status' => 404));
        }

        return rest_ensure_response($result);
    }

    public function enqueue_assets() {
        wp_register_style('rbd-style', plugin_dir_url(__FILE__) . 'css/random-brand.css', array(), '1.0');
        wp_enqueue_style('rbd-style');

        wp_register_script('rbd-script', plugin_dir_url(__FILE__) . 'js/random-brand.js', array(), '1.0', true);
        wp_localize_script('rbd-script', 'RBD_DATA', array(
            'endpoint' => esc_url_raw( rest_url('rbd/v1/brands') ),
        ));
        wp_enqueue_script('rbd-script');
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('class' => ''), $atts, 'random_brand');
        return '<div id="rbd-container" class="rbd-container ' . esc_attr($atts['class']) . '"><div class="rbd-loading">Загрузка...</div></div>';
    }
}

new RBD_Plugin();
