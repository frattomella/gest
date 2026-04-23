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
    GPO_Frontend::render_single_header();
} else {
    GPO_Frontend::render_single_header();
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
$neo_badge = GPO_Frontend::neopatentati_badge_markup($post_id, 'gpo-neo-badge gpo-neo-badge--single', $vehicle);
$quick_panel = GPO_Frontend::quick_info_panel_markup($post_id, 'gpo-quick-info-panel gpo-quick-info-panel--single', [], $vehicle);
$technical_badges = GPO_Frontend::single_technical_badges_markup($post_id, $vehicle);
$share_actions = GPO_Frontend::share_actions_markup($post_id);
$show = function ($key) use ($visible) {
    return in_array($key, $visible, true);
};
$strengths_markup = $show('strengths') ? GPO_Frontend::single_strengths_card_markup($post_id, $vehicle) : '';
$contact_markup = '';
if ($show('contact_box')) {
    $contact_markup = GPO_Frontend::lead_form_markup($post_id, [
        'title' => 'Richiedi informazioni',
        'text' => 'Compila il modulo per ricevere disponibilita, valutazione permuta e proposta commerciale personalizzata su questo veicolo.',
        'button_label' => 'Invia richiesta',
        'wrapper_class' => 'gpo-inline-lead-card gpo-inline-lead-card--single',
    ]);
}
$followup_markup = '';
if (class_exists('GPO_Blocks') && method_exists('GPO_Blocks', 'render_vehicle_carousel')) {
    GPO_Frontend::set_template_vehicle_context($post_id);
    $followup_markup = GPO_Blocks::render_vehicle_carousel([
        'title' => 'Carosello Veicolo',
        'source' => 'related_brand',
        'limit' => 6,
        'show' => 'image,title,price,chips,neopatentati,primary_button',
        'cardLayout' => 'default',
        'primaryButtonLabel' => 'Scheda veicolo',
    ]);
    GPO_Frontend::clear_template_vehicle_context();
}
?>
<main id="primary" class="site-main gpo-theme-main">
<div class="gpo-page-shell">
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
                    <?php if ($quick_panel || $neo_badge) : ?>
                        <div class="gpo-single-summary__highlights">
                            <?php echo $quick_panel; ?>
                            <?php echo $neo_badge; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($technical_badges) : ?>
                    <?php echo $technical_badges; ?>
                <?php endif; ?>

                <?php if ($share_actions) : ?>
                    <?php echo $share_actions; ?>
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
            <?php echo $strengths_markup; ?>
            <?php echo $contact_markup; ?>
        </aside>
    </section>

    <?php if ($followup_markup) : ?>
        <section class="gpo-single-follow-up">
            <div class="gpo-single-follow-up__divider" aria-hidden="true"></div>
            <?php echo $followup_markup; ?>
        </section>
    <?php endif; ?>
</div>
</div>
</main>
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
