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
$public_notes = trim((string) get_post_meta($post_id, '_gpo_public_notes', true));
$price = $vehicle['price'] ?? get_post_meta($post_id, '_gpo_price', true);
$promo_price = $vehicle['promo_price'] ?? get_post_meta($post_id, '_gpo_price_promo', true);
$current_price = $vehicle['current_price'] ?? ($promo_price ?: $price);
$price_note = GPO_Frontend::single_price_note($post_id, $vehicle);
$status_badges = GPO_Frontend::single_status_badges_markup($post_id, $vehicle);
$share_actions = GPO_Frontend::share_actions_markup($post_id);
$share_modal = GPO_Frontend::single_share_modal_markup($post_id);
$phone_url = GPO_Frontend::phone_call_url();
$description_html = GPO_Frontend::single_description_content_html($post_id);
$overview_markup = GPO_Frontend::single_overview_section_markup($post_id, $vehicle, in_array('specs', $visible, true) ? $specs : []);
$description_markup = in_array('description', $visible, true)
    ? GPO_Frontend::single_text_section_markup(
        'Descrizione',
        $description_html,
        [
            'summary' => 'Dettagli editoriali e commerciali del veicolo',
            'open' => true,
            'class' => 'gpo-single-accordion--description',
        ]
    )
    : '';
$accessories_markup = in_array('accessories', $visible, true)
    ? GPO_Frontend::single_list_section_markup(
        'Equipaggiamento / Accessori',
        $accessories,
        [
            'summary' => count($accessories) > 0 ? count($accessories) . ' elementi' : 'Nessun accessorio',
            'open' => false,
            'class' => 'gpo-single-accordion--accessories',
        ]
    )
    : '';
$notes_markup = in_array('notes', $visible, true) ? GPO_Frontend::single_notes_section_markup($public_notes) : '';
$print_sheet = GPO_Frontend::print_sheet_markup($post_id, $vehicle);
$show = function ($key) use ($visible) {
    return in_array($key, $visible, true);
};
$promo_copy = '';
if (is_array($promotion)) {
    $promo_copy = trim((string) ($promotion['promo_text'] ?? ''));
    if ($promo_copy === '' && !empty($promotion['discount_label'])) {
        $promo_copy = trim((string) $promotion['discount_label']);
    }
    if ($promo_copy !== '' && strtolower(remove_accents($promo_copy)) === 'promo attiva') {
        $promo_copy = '';
    }
}
$strengths_markup = $show('strengths') ? GPO_Frontend::single_strengths_card_markup($post_id, $vehicle) : '';
$contact_markup = '';
if ($show('contact_box')) {
    $contact_markup = GPO_Frontend::lead_form_markup($post_id, [
        'title' => 'Richiedi informazioni',
        'text' => 'Compila il modulo per ricevere disponibilita, proposta commerciale e dettagli aggiuntivi su questo veicolo.',
        'button_label' => 'Invia richiesta',
        'wrapper_class' => 'gpo-inline-lead-card gpo-inline-lead-card--single',
    ]);
}
$followup_markup = '';
if (class_exists('GPO_Blocks') && method_exists('GPO_Blocks', 'render_vehicle_carousel')) {
    GPO_Frontend::set_template_vehicle_context($post_id);
    $followup_markup = GPO_Blocks::render_vehicle_carousel([
        'title' => 'Altri veicoli da vedere',
        'source' => 'related_brand',
        'limit' => 6,
        'show' => 'image,title,price,chips,year,mileage,neopatentati,primary_button',
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

    <section class="gpo-vehicle-single">
        <div class="gpo-vehicle-single__hero">
            <?php if ($show('gallery')) : ?>
                <div class="gpo-vehicle-single__gallery">
                    <?php echo GPO_Frontend::gallery_markup($post_id, true); ?>
                </div>
            <?php endif; ?>

            <?php if ($show('summary')) : ?>
                <aside class="gpo-single-summary-card">
                    <?php if ($status_badges) : ?>
                        <?php echo $status_badges; ?>
                    <?php endif; ?>

                    <div class="gpo-single-summary-card__copy">
                        <span class="gpo-kicker">Scheda veicolo</span>
                        <h1><?php the_title(); ?></h1>
                        <p class="gpo-single-subtitle"><?php echo esc_html(trim((string) get_post_meta($post_id, '_gpo_brand', true) . ' ' . (string) get_post_meta($post_id, '_gpo_model', true) . ' ' . (string) get_post_meta($post_id, '_gpo_version', true))); ?></p>
                    </div>

                    <div class="gpo-single-summary-card__pricing<?php echo $promotion ? ' is-promoted' : ''; ?>">
                        <div class="gpo-single-summary-card__price-wrap">
                            <?php if ($promo_price && $price && $promo_price !== $price) : ?>
                                <small class="gpo-single-summary-card__price-original"><?php echo esc_html(GPO_Frontend::format_price_public((float) $price)); ?></small>
                            <?php endif; ?>
                            <strong class="gpo-single-summary-card__price-current<?php echo $promotion ? ' gpo-price-current--promo' : ''; ?>">
                                <?php echo esc_html($current_price ? GPO_Frontend::format_price_public((float) $current_price) : 'Prezzo su richiesta'); ?>
                            </strong>
                        </div>
                        <?php if ($price_note !== '') : ?>
                            <span class="gpo-price-note"><?php echo esc_html($price_note); ?></span>
                        <?php endif; ?>
                        <?php if ($promo_copy !== '') : ?>
                            <span class="gpo-promo-copy"><?php echo esc_html($promo_copy); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($share_actions) : ?>
                        <?php echo $share_actions; ?>
                    <?php endif; ?>
                    <?php if ($phone_url) : ?>
                        <a class="gpo-share-cta gpo-share-cta--phone" href="<?php echo esc_url($phone_url); ?>">
                            <span class="gpo-share-action__icon" aria-hidden="true">&#9742;</span>
                            <span>CHIAMA ORA</span>
                        </a>
                    <?php endif; ?>
                    <?php echo $share_modal; ?>
                </aside>
            <?php endif; ?>
        </div>

        <div class="gpo-single-accordion-stack">
            <?php echo $overview_markup; ?>
            <?php echo $description_markup; ?>
            <?php echo $accessories_markup; ?>
            <?php echo $notes_markup; ?>
        </div>

        <?php if ($strengths_markup || $contact_markup) : ?>
            <section class="gpo-single-support-grid">
                <?php if ($strengths_markup) : ?>
                    <div class="gpo-single-support-grid__item">
                        <?php echo $strengths_markup; ?>
                    </div>
                <?php endif; ?>
                <?php if ($contact_markup) : ?>
                    <div class="gpo-single-support-grid__item">
                        <?php echo $contact_markup; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($followup_markup) : ?>
            <section class="gpo-single-follow-up">
                <div class="gpo-single-follow-up__divider" aria-hidden="true"></div>
                <?php echo $followup_markup; ?>
            </section>
        <?php endif; ?>

        <?php echo $print_sheet; ?>
    </section>
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
