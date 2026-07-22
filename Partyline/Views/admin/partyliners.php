<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * Manage Partyliners — a searchable table of everyone with a Partyliner account
 *  or a stored SMS phone number, plus an inline "add" form.
 *
 * @var WP_User[] $users
 * @var array|null $notice   array('success'|'error', message)
 * @var int        $category Partyline category id (for the per-user post count)
 */

if ( ! function_exists( 'partyline_count_user_partylines' ) ) {
    /**
     * Count posts authored by a user (any editorial status), optionally scoped
     *  to the Partyline category.
     */
    function partyline_count_user_partylines( $uid, $cat ) {
        $args = array(
            'author'         => (int) $uid,
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        );
        if ( $cat ) {
            $args['cat'] = (int) $cat;
        }
        $q = new WP_Query( $args );
        return (int) $q->found_posts;
    }
}

$page_url        = admin_url( 'admin.php?page=Partyline-Partyliners' );
$main_url        = admin_url( 'admin.php?page=Partyline' );
$settings_url    = admin_url( 'admin.php?page=Partyline-Settings' );
$howto_url       = admin_url( 'admin.php?page=Partyline-HowTo' );
$signup_enabled  = class_exists( 'Partyline_Pwa' ) && Partyline_Pwa::signupEnabled();
$signup_url      = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::signupUrl() : '';
?>
<?php Partyline_View::load( 'admin/global/plg-styles' ); ?>

<div class="plg">
  <div class="plg-wrap">

    <?php Partyline_View::load( 'admin/global/plg-hero', array(
        'hero_lead' => 'The people who send in your Partylines. Add them by hand, or let them sign up themselves &mdash; their phone number is matched to incoming texts automatically.',
        'hero_toc'  => array(
            array( 'href' => $main_url,     'label' => 'Newsroom' ),
            array( 'href' => $settings_url, 'label' => 'Settings' ),
            array( 'href' => $howto_url,    'label' => 'How-To' ),
        ),
    ) ); ?>

    <?php if ( $notice ): ?>
      <section class="plg-section">
        <div class="plg-flash is-<?php echo esc_attr( $notice[0] ); ?>"><?php echo esc_html( $notice[1] ); ?></div>
      </section>
    <?php endif; ?>

    <!-- ============ ADD A PARTYLINER ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Add</div>
      <h2>Add a Partyliner</h2>
      <p class="plg-intro">The phone number is normalized so a text from this person is matched to their account.</p>
      <form method="post" action="<?php echo esc_url( $page_url ); ?>">
        <?php wp_nonce_field( 'partyline_add_partyliner' ); ?>
        <div class="plg-grid">
          <div>
            <label for="pl_name">Name</label>
            <input type="text" id="pl_name" name="pl_name" required placeholder="Jane Resident" />
          </div>
          <div>
            <label for="pl_phone">Phone</label>
            <input type="text" id="pl_phone" name="pl_phone" required placeholder="(732) 555-0123" />
          </div>
          <div>
            <label for="pl_email">Email</label>
            <input type="email" id="pl_email" name="pl_email" required placeholder="jane@example.com" />
          </div>
          <div>
            <label for="pl_address">Address <span class="plg-muted" style="text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input type="text" id="pl_address" name="pl_address" placeholder="123 Broad St, Red Bank" />
          </div>
        </div>
        <button type="submit" name="partyline_add_partyliner" value="1" class="plg-btn">Add Partyliner</button>
      </form>
    </section>

    <!-- ============ ALL PARTYLINERS ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Directory</div>
      <h2>Partyliners <span class="plg-muted" style="font-weight:800;">(<?php echo count( $users ); ?>)</span></h2>

      <?php if ( $signup_enabled && $signup_url ): ?>
        <div class="plg-linkbox">
          <span class="lbl">Public signup</span>
          <span class="url" id="pl-signup-url"><?php echo esc_html( $signup_url ); ?></span>
          <button type="button" class="plg-copy" onclick="plgPlCopy(this,'pl-signup-url')">Copy</button>
        </div>
      <?php endif; ?>

      <div class="plg-search" style="margin-top:16px;">
        <input type="text" id="pl-filter" placeholder="Search name, email, phone, address&hellip;" autocomplete="off" />
      </div>

      <?php if ( empty( $users ) ): ?>
        <div class="plg-empty">
          <div class="big">👥</div>
          <p style="margin:0;">No Partyliners yet. Add one above, or share your public signup link.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="plg-table" id="pl-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Partylines</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $users as $u ):
                  $phone   = get_user_meta( $u->ID, 'partyline_phone', true );
                  $address = get_user_meta( $u->ID, 'partyline_address', true );
                  $pcount  = partyline_count_user_partylines( $u->ID, $category );
                  $joined  = $u->user_registered ? mysql2date( 'M j, Y', $u->user_registered ) : '';
                  $del_url = wp_nonce_url(
                      add_query_arg( array( 'action' => 'delete', 'user' => $u->ID ), $page_url ),
                      'partyline_delete_' . $u->ID
                  );
                  $haystack = strtolower( $u->display_name . ' ' . $u->user_email . ' ' . $phone . ' ' . $address );
              ?>
                <tr data-search="<?php echo esc_attr( $haystack ); ?>">
                  <td class="name"><?php echo esc_html( $u->display_name ? $u->display_name : $u->user_login ); ?></td>
                  <td><?php echo esc_html( $u->user_email ); ?></td>
                  <td><?php echo $phone ? esc_html( $phone ) : '<span class="plg-muted">&mdash;</span>'; ?></td>
                  <td><?php echo $address ? esc_html( $address ) : '<span class="plg-muted">&mdash;</span>'; ?></td>
                  <td><span class="plg-count"><?php echo (int) $pcount; ?></span></td>
                  <td class="plg-muted"><?php echo esc_html( $joined ); ?></td>
                  <td class="plg-actions">
                    <a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>">Edit</a>
                    <a href="<?php echo esc_url( $del_url ); ?>" class="del"
                       onclick="return confirm('Remove <?php echo esc_js( $u->display_name ); ?>? Their submissions will be reassigned to you.');">Remove</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <div class="plg-tag">LONG LIVE LOCAL NEWS</div>
    <div class="plg-footer">Questions or a bug to report? Email <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>.</div>

  </div>
</div>

<script>
(function () {
    var input = document.getElementById('pl-filter');
    var table = document.getElementById('pl-table');
    if (!input || !table) { return; }
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        for (var i = 0; i < rows.length; i++) {
            var hay = rows[i].getAttribute('data-search') || '';
            rows[i].style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
        }
    });
})();
function plgPlCopy(btn, id){
  var el = document.getElementById(id);
  if (!el) return;
  var text = (el.textContent || '').trim();
  var done = function(){ var o = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = o; }, 1500); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done); }
  else { var t=document.createElement('input'); document.body.appendChild(t); t.value=text; t.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(t); done(); }
}
</script>
