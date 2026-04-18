<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Search extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_vehicle_search';
    }

    public function get_title() {
        return 'GestPark - Ricerca veicoli';
    }

    public function get_icon() {
        return 'eicon-search';
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

        $this->add_control('placeholder', [
            'label' => 'Placeholder',
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'Cerca veicolo',
        ]);

        $this->add_control('width', [
            'label' => 'Larghezza percentuale',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 100,
            'min' => 20,
            'max' => 100,
        ]);

        $this->add_control('radius', [
            'label' => 'Raggio angoli',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 999,
            'min' => 36,
            'max' => 999,
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        GPO_Elementor::decorate_widget_wrapper($this);
        echo do_shortcode(
            '[gestpark_vehicle_search page_id="' . absint($settings['page_id']) . '" catalog_ref="' . esc_attr($settings['catalog_ref']) . '" placeholder="' . esc_attr($settings['placeholder']) . '" width="' . absint($settings['width']) . '" radius="' . absint($settings['radius']) . '"]'
        );
    }
}
