<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('gpo-public');

$is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();

if ($is_block_theme) {
    ?><!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php
    wp_body_open();
    echo '<div class="wp-site-blocks">';
    if (function_exists('block_template_part')) {
        block_template_part('header');
    }
} else {
    get_header();
}

$post_id = get_the_ID();
$vehicle = GPO_Frontend::vehicle_data($post_id);
$promotion = $vehicle['promotion'] ?? null;
$display = GPO_Frontend::single_display();
$visible = $display['visible'];
$layout = $display['layout'];
$specs = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post_id, '_gpo_specs', true))));
$accessories = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post_id, '_gpo_accessories', true))));
$public_notes = get_post_meta($post_id, '_gpo_public_notes', true);
$badge = $vehicle['badge'] ?? get_post_meta($post_id, '_gpo_badge', true);
$price = $vehicle['price'] ?? get_post_meta($post_id, '_gpo_price', true);
$promo_price = $vehicle['promo_price'] ?? get_post_meta($post_id, '_gpo_price_promo', true);
$current_price = $vehicle['current_price'] ?? ($promo_price ?: $price);
$meta = [
    'Condizione' => get_post_meta($post_id, '_gpo_condition', true),
    'Anno' => get_post_meta($post_id, '_gpo_year', true),
    'Alimentazione' => get_post_meta($post_id, '_gpo_fuel', true),
    'Chilometraggio' => get_post_meta($post_id, '_gpo_mileage', true) ? number_format_i18n((float) get_post_meta($post_id, '_gpo_mileage', true), 0) . ' km' : '',
    'Carrozzeria' => get_post_meta($post_id, '_gpo_body_type', true),
    'Cambio' => get_post_meta($post_id, '_gpo_transmission', true),
    'Cilindrata' => get_post_meta($post_id, '_gpo_engine_size', true) ? get_post_meta($post_id, '_gpo_engine_size', true) . ' cc' : '',
    'Potenza' => get_post_meta($post_id, '_gpo_power', true),
    'Colore' => get_post_meta($post_id, '_gpo_color', true),
    'Porte' => get_post_meta($post_id, '_gpo_doors', true),
    'Posti' => get_post_meta($post_id, '_gpo_seats', true),
    'Sede' => get_post_meta($post_id, '_gpo_location', true),
];
$show = function ($key) use ($visible) {
    return in_array($key, $visible, true);
};
?>
<div class="gpo-single-wrap gpo-single-layout-<?php echo esc_attr($layout); ?>">
    <?php echo GPO_Frontend::back_button_markup($post_id); ?>
    <section class="gpo-single-hero">
        <?php if ($show('gallery')) : ?>
            <?php echo GPO_Frontend::gallery_markup($post_id, true); ?>
        <?php endif; ?>

        <?php if ($show('summary')) : ?>
            <div class="gpo-single-summary">
                <div class="gpo-single-hero-top">
                    <div>
                        <span class="gpo-kicker">Scheda veicolo</span>
                        <p class="gpo-single-subtitle"><?php echo esc_html(trim(get_post_meta($post_id, '_gpo_brand', true) . ' ' . get_post_meta($post_id, '_gpo_model', true))); ?></p>
                    </div>
                    <div class="gpo-single-summary__badges">
                        <?php if ($badge) : ?>
                            <span class="gpo-badge"><?php echo esc_html($badge); ?></span>
                        <?php endif; ?>
                        <?php if ($promotion) : ?>
                            <span class="gpo-badge gpo-badge--promo"><?php echo esc_html($promotion['badge']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <h1><?php the_title(); ?></h1>

                <div class="gpo-single-price-row">
                    <div class="<?php echo $promotion ? 'is-promoted' : ''; ?>">
                        <?php if ($promo_price && $price && $promo_price !== $price) : ?>
                            <small><?php echo esc_html(GPO_Frontend::format_price_public((float) $price)); ?></small>
                        <?php endif; ?>
                        <strong class="<?php echo $promotion ? 'gpo-price-current--promo' : ''; ?>"><?php echo esc_html($current_price ? GPO_Frontend::format_price_public((float) $current_price) : 'Prezzo su richiesta'); ?></strong>
                        <?php if ($promotion) : ?>
                            <span class="gpo-promo-copy"><?php echo esc_html($promotion['promo_text'] ?: $promotion['discount_label']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="gpo-spec-pill-list">
                        <?php foreach (['Condizione' => $meta['Condizione'], 'Alimentazione' => $meta['Alimentazione'], 'Cambio' => $meta['Cambio']] as $value) : ?>
                            <?php if ($value) : ?>
                                <span class="gpo-chip"><?php echo esc_html($value); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="gpo-meta-grid">
                    <?php foreach ($meta as $label => $value) : ?>
                        <?php if (!$value) { continue; } ?>
                        <div><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($value); ?></span></div>
                    <?php endforeach; ?>
                </div>

                <?php if ($show('contact_box')) : ?>
                    <?php
                    echo GPO_Frontend::lead_form_markup($post_id, [
                        'title' => 'Richiedi informazioni',
                        'text' => 'Compila il modulo per ricevere disponibilita, valutazione permuta e proposta commerciale personalizzata su questo veicolo.',
                        'button_label' => 'Invia richiesta',
                        'wrapper_class' => 'gpo-inline-lead-card',
                    ]);
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="gpo-content-grid">
        <div class="gpo-main-stack">
            <?php if ($show('description')) : ?>
                <div class="gpo-content-block gpo-content-block--description">
                    <h2>Descrizione</h2>
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <?php if ($show('notes') && $public_notes) : ?>
                <div class="gpo-content-block gpo-content-block--notes"><h2>Note</h2><p><?php echo nl2br(esc_html($public_notes)); ?></p></div>
            <?php endif; ?>

            <?php if ($show('specs') && !empty($specs)) : ?>
                <div class="gpo-content-block gpo-content-block--specs"><h2>Specifiche</h2><ul class="gpo-icon-list"><?php foreach ($specs as $item) { echo '<li>' . esc_html($item) . '</li>'; } ?></ul></div>
            <?php endif; ?>

            <?php if ($show('accessories') && !empty($accessories)) : ?>
                <div class="gpo-content-block gpo-content-block--accessories">
                    <details class="gpo-content-disclosure">
                        <summary class="gpo-content-disclosure__summary">
                            <span class="gpo-content-disclosure__title">Accessori</span>
                            <span class="gpo-content-disclosure__meta"><?php echo esc_html((string) count($accessories)); ?> accessori</span>
                        </summary>
                        <div class="gpo-content-disclosure__body">
                            <ul class="gpo-icon-list"><?php foreach ($accessories as $item) { echo '<li>' . esc_html($item) . '</li>'; } ?></ul>
                        </div>
                    </details>
                </div>
            <?php endif; ?>
        </div>

        <aside class="gpo-side-stack">
            <?php if ($show('strengths')) : ?>
                <div class="gpo-side-card">
                    <h3>Punti di forza</h3>
                    <ul class="gpo-icon-list">
                        <li>Scheda completa e personalizzabile</li>
                        <li>Dati importabili da API e modificabili localmente</li>
                        <li>Compatibile con editor WordPress e tema del sito</li>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>
    </section>
</div>
<?php
if ($is_block_theme) {
    if (function_exists('block_template_part')) {
        block_template_part('footer');
    }
    echo '</div>';
    wp_footer();
    ?>
    </body>
    </html>
    <?php
} else {
    get_footer();
}
