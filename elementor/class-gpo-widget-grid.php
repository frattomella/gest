<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Grid extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_vehicle_grid';
    }

    public function get_title() {
        return 'Gestpark - Griglia veicoli';
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Contenuto',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('limit', [
            'label' => 'Numero veicoli',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 6,
        ]);

        $this->add_control('columns', [
            'label' => 'Colonne desktop',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
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
        echo do_shortcode('[gestpark_vehicle_grid limit="' . absint($settings['limit']) . '" columns="' . absint($settings['columns']) . '"]');
    }
}
