<?php
/**
 * Shared Partyline admin hero (gradient banner + inverted logo + lead).
 *
 * @var string $hero_lead Lead sentence shown under the logo.
 * @var array  $hero_toc  Optional [ ['href'=>'#id','label'=>'Text'], ... ] pill nav.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$plg_logo = esc_url( set_url_scheme( Partyline_Utility::getImageBaseURL() . 'partyline-black.png', 'https' ) );
$hero_lead = isset( $hero_lead ) ? $hero_lead : '';
$hero_toc  = isset( $hero_toc ) && is_array( $hero_toc ) ? $hero_toc : array();
?>
<header class="plg-hero">
    <img class="plg-hero-logo" src="<?php echo $plg_logo; ?>" alt="Partyline">
    <?php if ( $hero_lead ): ?>
        <p class="plg-lead"><?php echo wp_kses_post( $hero_lead ); ?></p>
    <?php endif; ?>
</header>
<?php if ( $hero_toc ): ?>
    <nav class="plg-toc">
        <?php foreach ( $hero_toc as $t ): ?>
            <a href="<?php echo esc_url( $t['href'] ); ?>"><?php echo esc_html( $t['label'] ); ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
