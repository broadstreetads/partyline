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

$page_url = admin_url( 'admin.php?page=Partyline-Partyliners' );
?>
<style>
    #main .pl-mng-wrap { max-width: 980px; }
    #main .pl-notice { padding: 12px 16px; border-radius: 6px; margin: 0 0 18px; font-size: 14px; }
    #main .pl-notice.is-success { background: #edfaef; border: 1px solid #b7e4c0; color: #1a7431; }
    #main .pl-notice.is-error   { background: #fdecec; border: 1px solid #f3bcbc; color: #b32020; }

    #main .pl-add-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; margin-bottom: 14px; }
    #main .pl-add-grid .full { grid-column: 1 / -1; }
    #main .pl-add-grid label { display: block; font-weight: 600; font-size: 12px; color: #52525b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .02em; }
    #main .pl-add-grid input { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d4d4d8; border-radius: 6px; font-size: 14px; }
    #main .pl-btn { display: inline-block; background: #18181b; color: #fff; border: 0; border-radius: 6px; padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
    #main .pl-btn:hover { background: #333; color: #fff; }

    #main .pl-search { margin: 0 0 14px; }
    #main .pl-search input { width: 320px; max-width: 100%; padding: 8px 12px; border: 1px solid #d4d4d8; border-radius: 6px; font-size: 14px; }

    #main table.pl-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    #main table.pl-table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid #e4e4e7; color: #52525b; font-size: 12px; text-transform: uppercase; letter-spacing: .02em; }
    #main table.pl-table td { padding: 10px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
    #main table.pl-table tr:hover td { background: #fafafa; }
    #main .pl-name { font-weight: 600; color: #18181b; }
    #main .pl-muted { color: #a1a1aa; }
    #main .pl-count { display: inline-block; min-width: 22px; text-align: center; background: #f4f4f5; border-radius: 999px; padding: 2px 8px; font-weight: 600; }
    #main .pl-actions a { text-decoration: none; margin-right: 10px; }
    #main .pl-actions a.pl-del { color: #b32020; }
    #main .pl-empty { padding: 28px; text-align: center; color: #a1a1aa; }
</style>

<div id="main">
    <?php Partyline_View::load( 'admin/global/header' ); ?>

    <div class="left_column">
        <div id="controls" class="pl-mng-wrap">

            <?php if ( $notice ): ?>
                <div class="pl-notice is-<?php echo esc_attr( $notice[0] ); ?>">
                    <?php echo esc_html( $notice[1] ); ?>
                </div>
            <?php endif; ?>

            <!-- ============ ADD A PARTYLINER ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-plus-alt"></span> Add a Partyliner</div>
                <div class="content">
                    <form method="post" action="<?php echo esc_url( $page_url ); ?>">
                        <?php wp_nonce_field( 'partyline_add_partyliner' ); ?>
                        <div class="pl-add-grid">
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
                                <label for="pl_address">Address <span class="pl-muted">(optional)</span></label>
                                <input type="text" id="pl_address" name="pl_address" placeholder="123 Broad St, Red Bank" />
                            </div>
                        </div>
                        <button type="submit" name="partyline_add_partyliner" value="1" class="pl-btn">Add Partyliner</button>
                        <span class="pl-muted" style="margin-left:12px;">The phone is normalized so incoming texts match this person.</span>
                    </form>
                </div>
            </div>

            <!-- ============ ALL PARTYLINERS ============ -->
            <div class="box">
                <div class="title">
                    <span class="dashicons dashicons-groups"></span>
                    Partyliners <span class="pl-muted">(<?php echo count( $users ); ?>)</span>
                </div>
                <div class="content">

                    <div class="pl-search">
                        <input type="text" id="pl-filter" placeholder="Search name, email, phone, address&hellip;" autocomplete="off" />
                    </div>

                    <?php if ( empty( $users ) ): ?>
                        <div class="pl-empty">No Partyliners yet. Add one above, or share your public signup link.</div>
                    <?php else: ?>
                        <table class="pl-table" id="pl-table">
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
                                    $count   = partyline_count_user_partylines( $u->ID, $category );
                                    $joined  = $u->user_registered ? mysql2date( 'M j, Y', $u->user_registered ) : '';
                                    $del_url = wp_nonce_url(
                                        add_query_arg( array( 'action' => 'delete', 'user' => $u->ID ), $page_url ),
                                        'partyline_delete_' . $u->ID
                                    );
                                    $haystack = strtolower( $u->display_name . ' ' . $u->user_email . ' ' . $phone . ' ' . $address );
                                ?>
                                    <tr data-search="<?php echo esc_attr( $haystack ); ?>">
                                        <td class="pl-name"><?php echo esc_html( $u->display_name ? $u->display_name : $u->user_login ); ?></td>
                                        <td><?php echo esc_html( $u->user_email ); ?></td>
                                        <td><?php echo $phone ? esc_html( $phone ) : '<span class="pl-muted">&mdash;</span>'; ?></td>
                                        <td><?php echo $address ? esc_html( $address ) : '<span class="pl-muted">&mdash;</span>'; ?></td>
                                        <td><span class="pl-count"><?php echo (int) $count; ?></span></td>
                                        <td class="pl-muted"><?php echo esc_html( $joined ); ?></td>
                                        <td class="pl-actions">
                                            <a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>">Edit</a>
                                            <a href="<?php echo esc_url( $del_url ); ?>" class="pl-del"
                                               onclick="return confirm('Remove <?php echo esc_js( $u->display_name ); ?>? Their submissions will be reassigned to you.');">Remove</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div>
            </div>

        </div>
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
</script>
