<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Brand_Carousel extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_brand_carousel';
    }

    public function get_title() {
        return 'GestPark - Carosello marchi';
    }

    public function get_icon() {
        return 'eicon-slider-album';
    }

    public function get_categories() {
        return [GPO_Elementor::widget_category()];
    }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Contenuto',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('page_id', [
            'label' => 'Pagina catalogo target',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => '0',
            'options' => GPO_Elementor::page_options(),
        ]);

        $this->add_control('catalog_ref', [
            'label' => 'Riferimento catalogo',
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'default',
        ]);

        $this->add_control('card_size', [
            'label' => 'Dimensione card marchio',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 168,
            'min' => 120,
            'max' => 240,
        ]);

        $this->add_control('logo_size', [
            'label' => 'Grandezza logo',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 96,
            'min' => 56,
            'max' => 180,
        ]);

        $this->add_control('autoplay', [
            'label' => 'Scorrimento automatico',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('interval', [
            'label' => 'Tempo autoplay (ms)',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 6500,
            'min' => 1500,
            'max' => 10000,
            'step' => 100,
        ]);

        $this->add_control('speed', [
            'label' => 'Velocita animazione (ms)',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 900,
            'min' => 300,
            'max' => 2400,
            'step' => 50,
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        GPO_Elementor::decorate_widget_wrapper($this);
        echo do_shortcode(
            '[gestpark_brand_carousel page_id="' . absint($settings['page_id']) . '" catalog_ref="' . esc_attr($settings['catalog_ref']) . '" card_size="' . absint($settings['card_size']) . '" logo_size="' . absint($settings['logo_size']) . '" autoplay="' . esc_attr($settings['autoplay']) . '" interval="' . absint($settings['interval']) . '" speed="' . absint($settings['speed']) . '"]'
        );
    }
}
