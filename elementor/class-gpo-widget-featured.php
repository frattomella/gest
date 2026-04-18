<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPO_Elementor_Widget_Featured extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gpo_featured_vehicle';
    }

    public function get_title() {
        return 'GestPark - Veicolo in evidenza';
    }

    public function get_icon() {
        return 'eicon-star';
    }

    public function get_categories() {
        return [GPO_Elementor::widget_category()];
    }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Contenuto',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('layout', [
            'label' => 'Layout',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'hero',
            'options' => [
                'hero' => 'Hero',
                'default' => 'Default',
            ],
        ]);

        $this->add_control('card_layout', [
            'label' => 'Layout card',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'default',
            'options' => [
                'default' => 'Default',
                'compact' => 'Compact',
                'minimal' => 'Minimal',
            ],
        ]);

        $this->add_control('show', [
            'label' => 'Elementi visibili card (CSV)',
            'type' => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'image,badge,brand,title,price,chips,primary_button',
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
        echo do_shortcode(
            '[gestpark_featured_vehicle layout="' . esc_attr($settings['layout']) . '" card_layout="' . esc_attr($settings['card_layout']) . '" show="' . esc_attr($settings['show']) . '" outer_padding_x="' . absint($settings['outer_padding_x']) . '" section_gap="' . absint($settings['section_gap']) . '"]'
        );
    }
}
