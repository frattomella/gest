<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Carousel extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_featured_carousel';
    }

    public function get_title() {
        return 'Gestpark - Carosello vetrina';
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return [GPO_Elementor::widget_category()];
    }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Contenuto',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('limit', [
            'label' => 'Numero veicoli',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
        ]);

        $this->add_control('autoplay', [
            'label' => 'Autoplay',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('items_per_page', [
            'label' => 'Veicoli visibili per pagina',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        GPO_Elementor::decorate_widget_wrapper($this);
        echo do_shortcode('[gestpark_featured_carousel limit="' . absint($settings['limit']) . '" autoplay="' . esc_attr($settings['autoplay']) . '" items_per_page="' . absint($settings['items_per_page']) . '"]');
    }
}
