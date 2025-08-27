<div id="main">
    <?php Partyline_View::load('admin/global/header') ?>
    <div class="left_column">
        <?php if($errors): ?>
            <div class="box">
                <div class="shadow_column">
                    <div class="title" style="">
                        <span class="dashicons dashicons-warning"></span> Alerts
                    </div>
                    <div class="content">
                        <p>
                            Nice to have you! We've noticed some things you may want to take
                            care of:
                        </p>
                        <ol>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                <div class="shadow_bottom"></div>
            </div>
        <?php endif; ?>
        
        <div class="box">
            <div class="title">
                <span class="dashicons dashicons-admin-post"></span> Pending Partyline Posts
            </div>
            <div class="content">
                <?php if ($posts): ?>
                    <div class="partyline-posts-list">
                        <?php foreach ($posts as $post): ?>
                            <div class="partyline-post-item">
                                <a href="<?php echo esc_url( get_edit_post_link($post->ID) ); ?>" class="partyline-post-link">
                                    <div class="post-title"><?php echo esc_html($post->post_title); ?></div>
                                    <div class="post-meta">
                                        <span class="post-date"><?php echo esc_html( get_the_date('M j, Y g:i A', $post->ID) ); ?></span>
                                        <span class="post-status">Draft</span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-posts">
                        <p>No pending Partyline posts found.</p>
                        <p><small>Posts will appear here when they are submitted via SMS and need review.</small></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="right_column">
        <?php Partyline_View::load('admin/global/sidebar') ?>
    </div>
</div>
<div class="clearfix"></div> 