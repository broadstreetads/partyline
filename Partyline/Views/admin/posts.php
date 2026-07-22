<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Main Partyline admin screen — the newsroom inbox of pending draft submissions.
 *
 * @var WP_Post[]|false $posts
 * @var array           $errors
 */
$settings_url    = admin_url( 'admin.php?page=Partyline-Settings' );
$partyliners_url = admin_url( 'admin.php?page=Partyline-Partyliners' );
$howto_url       = admin_url( 'admin.php?page=Partyline-HowTo' );
$app_url         = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' );
$count           = is_array( $posts ) ? count( $posts ) : 0;
?>
<?php Partyline_View::load( 'admin/global/plg-styles' ); ?>

<div class="plg">
  <div class="plg-wrap">

    <?php Partyline_View::load( 'admin/global/plg-hero', array(
        'hero_lead' => 'Your community newsroom. Every Partyline lands here as a draft, ready for you to review and publish.',
        'hero_toc'  => array(
            array( 'href' => $settings_url,    'label' => 'Settings' ),
            array( 'href' => $partyliners_url, 'label' => 'Partyliners' ),
            array( 'href' => $howto_url,       'label' => 'How-To' ),
        ),
    ) ); ?>

    <?php if ( ! empty( $errors ) ): ?>
      <section class="plg-section">
        <div class="plg-callout warn">
          <strong>A few things to take care of:</strong>
          <ul style="margin:8px 0 0; padding-left:18px;">
            <?php foreach ( $errors as $error ): ?>
              <li><?php echo wp_kses_post( $error ); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    <?php endif; ?>

    <section class="plg-section">
      <div class="plg-eyebrow">Newsroom</div>
      <h2>Pending Partylines <?php if ( $count ): ?><span class="plg-count" style="font-size:15px;vertical-align:middle;margin-left:6px;"><?php echo (int) $count; ?></span><?php endif; ?></h2>
      <p class="plg-intro">Draft submissions from your community, newest first. Click one to review, edit, and publish it.</p>

      <?php if ( $posts ): ?>
        <div class="plg-list">
          <?php foreach ( $posts as $post ): ?>
            <a class="plg-list-item" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
              <div class="t"><?php echo esc_html( $post->post_title ? $post->post_title : '(Untitled Partyline)' ); ?></div>
              <div class="m">
                <span><?php echo esc_html( get_the_date( 'M j, Y \a\t g:i A', $post->ID ) ); ?></span>
                <span class="plg-pill">Draft</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="plg-empty">
          <div class="big">📭</div>
          <p style="margin:0 0 6px;font-weight:700;color:#3f3f46;">No pending Partylines right now.</p>
          <p style="margin:0;font-size:13.5px;">New submissions from the app or by text will show up here for review.</p>
        </div>
      <?php endif; ?>
    </section>

    <section class="plg-section">
      <div class="plg-eyebrow">Get more submissions</div>
      <h2>Share your Partyline</h2>
      <p class="plg-intro">The single best thing you can do is keep a persistent call to action. Put this link everywhere.</p>
      <div class="plg-linkbox">
        <span class="url" id="plg-main-applink"><?php echo esc_html( $app_url ); ?></span>
        <button type="button" class="plg-copy" onclick="plgMainCopy(this)">Copy link</button>
      </div>
      <p style="margin-top:14px;font-size:13.5px;color:#6b7280;">New here? The <a href="<?php echo esc_url( $howto_url ); ?>">How-To guide</a> walks through setup and how to make Partyline thrive in your town.</p>
    </section>

    <div class="plg-tag">LONG LIVE LOCAL NEWS</div>
    <div class="plg-footer">Questions or a bug to report? Email <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>.</div>

  </div>
</div>

<script>
function plgMainCopy(btn){
  var el = document.getElementById('plg-main-applink');
  if (!el) return;
  var text = (el.textContent || '').trim();
  var done = function(){ var o = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = o; }, 1500); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done); }
  else { var t=document.createElement('input'); document.body.appendChild(t); t.value=text; t.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(t); done(); }
}
</script>
