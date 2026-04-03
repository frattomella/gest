<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Catalog extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_vehicle_catalog';
    }

    public function get_title() {
        return 'Gestpark - Catalogo con filtri';
    }

    public function get_icon() {
        return 'eicon-search-results';
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
            'default' => 12,
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

        $this->add_control('show', [
            'label' => 'Elementi visibili card (CSV)',
            'type' => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'image,title,price,primary_button',
        ]);

        $this->add_control('filter_fields', [
            'label' => 'Campi filtro visibili (CSV)',
            'type' => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'search,brand,fuel,sort',
        ]);

        $this->add_control('outer_padding_x', [
            'label' => 'Padding laterale contenitore',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 18,
        ]);

        $this->add_control('section_gap', [
            'label' => 'Spazio verticale tra moduli',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 24,
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        GPO_Elementor::decorate_widget_wrapper($this);
        echo do_shortcode('[gestpark_vehicle_catalog limit="' . absint($settings['limit']) . '" columns="' . absint($settings['columns']) . '" show="' . esc_attr($settings['show']) . '" filter_fields="' . esc_attr($settings['filter_fields']) . '" outer_padding_x="' . absint($settings['outer_padding_x']) . '" section_gap="' . absint($settings['section_gap']) . '"]');
    }
}
