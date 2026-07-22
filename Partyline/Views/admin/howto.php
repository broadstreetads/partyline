<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
.pl-howto .content { padding: 18px 24px 22px; }
.pl-howto p { font-size: 14px; line-height: 1.65; color: #3c434a; margin: 0 0 12px; }
.pl-howto ol, .pl-howto ul { margin: 0 0 14px 22px; padding: 0; }
.pl-howto li { font-size: 14px; line-height: 1.6; color: #3c434a; margin: 0 0 9px; }
.pl-howto h3 { font-size: 15px; margin: 20px 0 8px; color: #1d2327; }
.pl-howto .pl-sub { font-style: italic; color: #646970; margin: 0 0 14px; }
.pl-howto .pl-link { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 7px 11px; font-family: Menlo, Consolas, monospace; display: inline-block; margin: 4px 0 8px; }
.pl-howto .pl-tag { text-align: center; font-weight: 800; letter-spacing: .14em; color: #2271b1; margin: 16px 0 4px; }
</style>
<div id="main" class="pl-howto">
      <?php Partyline_View::load('admin/global/header') ?>
      <div class="left_column">
          <div id="controls">

            <!-- ============ WHAT IS PARTYLINE ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-megaphone"></span> What is Partyline?</div>
                <div class="content">
                    <p>Partyline turns your readers into contributors. Anyone in your community can send in a
                        photo and a few words &mdash; from the built-in app on their phone, or by text message
                        &mdash; and it lands in your newsroom as a <strong>draft</strong>, ready for you to
                        review and publish.</p>
                    <ul>
                        <li><strong>The app (primary):</strong> an installable web app at your Partyline link.
                            Snap a photo, add the story (type it or dictate it), and submit.</li>
                        <li><strong>Text messages (optional):</strong> accept Partylines by SMS/MMS through Twilio.</li>
                        <li><strong>AI formatting (optional):</strong> tidies up spelling and grammar, and powers
                            voice dictation &mdash; while staying true to the contributor&rsquo;s own words.</li>
                    </ul>
                    <p>Nothing goes live on its own. Every submission arrives as a draft, so an editor always
                        has the final say.</p>
                </div>
            </div>

            <!-- ============ HISTORY ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-book"></span> A brief history</div>
                <div class="content">
                    <p>Partyline was invented at <strong>Red Bank Green</strong> in Red Bank, New Jersey. We loved
                        the old Red Bank newspaper, the <em>Red Bank Register</em>, which covered every tiny little
                        thing that happened in town. In today&rsquo;s news environment, that level of coverage just
                        isn&rsquo;t economically feasible &mdash; but we also realized that <strong>local news is
                        the original social media</strong>, and that it doesn&rsquo;t have to be a one-way street.</p>
                    <p>If a community captures the things it thinks are important &mdash; the moments that matter to
                        the people who live there &mdash; those things can be news too. Together, we believe a
                        community can paint a better picture of a place and tell its story better than ever, maybe
                        even better than the <em>Register</em> did. It&rsquo;s a deceptively simple idea, with a
                        number of benefits that only reveal themselves in the unfolding of its use.</p>
                </div>
            </div>

            <!-- ============ TOP 10 USES + WHAT TO EXPECT ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-star-filled"></span> Top 10 uses (and what to expect)</div>
                <div class="content">
                    <p class="pl-sub">A lovingly curated list from experience &mdash; originally shared with LINA
                        Publishers in Australia.</p>
                    <ol>
                        <li><strong>Cover stories you&rsquo;d never get to otherwise</strong> &mdash; like proms, graduations, and scout ceremonies.</li>
                        <li><strong>Post ridiculous stuff that wouldn&rsquo;t fly as a &ldquo;real&rdquo; article</strong> &mdash; and watch the traffic surprise you.</li>
                        <li><strong>File an initial post when a breaking story hits</strong> &mdash; straight from the street.</li>
                        <li><strong>Capture the in-the-moment energy</strong> of a parade, protest, or town meeting.</li>
                        <li><strong>Share sunsets and puppy pics</strong> &mdash; without feeling like a complete sellout.</li>
                        <li><strong>Keep your advertisers happy</strong> by actually posting their community event (and looking cool doing it).</li>
                        <li><strong>Turn your readers into local mini-celebrities</strong> &mdash; and spark conversations offline.</li>
                        <li><strong>Give voice to people who never get quoted</strong> &mdash; Partyline lowers the barrier to entry.</li>
                        <li><strong>Redirect PR people</strong> to a more productive outlet than your inbox.</li>
                        <li><strong>Reclaim your newsroom&rsquo;s social media power</strong> &mdash; because you were doing it before Facebook anyway.</li>
                    </ol>
                    <h3>What to expect</h3>
                    <p>Every Partyline arrives as a draft, so you always decide what runs. Expect a steady,
                        manageable stream rather than a firehose &mdash; and treat what comes in as raw material.
                        A quick edit (or the optional AI cleanup) turns a rough text into publishable copy.
                        Quality will vary, and that&rsquo;s fine: the goal is coverage you&rsquo;d never get any
                        other way.</p>
                </div>
            </div>

            <!-- ============ GETTING STARTED ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-admin-settings"></span> Getting started</div>
                <div class="content">
                    <p>Head to <strong>Partyline &rarr; Settings</strong> and work top to bottom:</p>
                    <ol>
                        <li><strong>General Settings</strong> &mdash; choose the <em>Partyline Category</em> that
                            submissions are filed under, and add the <em>Email Notifications</em> addresses that
                            should be pinged when one comes in.</li>
                        <li><strong>Contributor App</strong> &mdash; it&rsquo;s on by default. Share your app link
                            with the community:
                            <br><span class="pl-link"><?php echo esc_html( class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' ) ); ?></span><br>
                            That&rsquo;s where people submit &mdash; they can install it to their home screen like
                            an app. Optionally turn on <em>anonymous submissions</em> to let anyone contribute
                            without an account (they provide a name, email, and phone, and pass a Cloudflare
                            Turnstile check).</li>
                        <li><strong>Text Messages (optional)</strong> &mdash; to also accept Partylines by text,
                            enable Twilio, get a Twilio phone number, and point its messaging webhook at the URL
                            shown in that section.</li>
                        <li><strong>AI Formatting (optional)</strong> &mdash; add an OpenAI API key to auto-format
                            submissions and enable voice dictation. You can fine-tune the editorial voice with the
                            <em>AI writing prompt</em>.</li>
                    </ol>
                    <p>Click <strong>Save</strong>, then open your app link on your phone and try it: take a photo,
                        add a story, and submit &mdash; it&rsquo;ll show up in your drafts.</p>
                    <p>To credit texters by name, register them as &ldquo;Partyliners&rdquo; on the
                        <a href="<?php echo esc_url( admin_url( 'users.php?partyline_has_phone=1' ) ); ?>">Users page</a>
                        (see <em>How to Manage Your Partyliners</em> in the sidebar).</p>
                </div>
            </div>

            <!-- ============ MAKING IT SUCCESSFUL ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-groups"></span> Making it successful in your community</div>
                <div class="content">
                    <p>The single most important thing is a <strong>persistent call to action</strong>. You
                        can&rsquo;t tell people once &mdash; you have to build the habit, until the moment someone
                        sees something great in town, they instinctively think, <em>&ldquo;This should be a
                        Partyline.&rdquo;</em></p>
                    <ol>
                        <li>Post about it and tell everybody.</li>
                        <li>Create a clear Partyline call to action &mdash; put the link (and a QR code) everywhere.</li>
                        <li>Continually beat the drum.</li>
                    </ol>
                    <p>A few things that work well:</p>
                    <ul>
                        <li>Send a <strong>dedicated newsletter</strong> introducing it.</li>
                        <li>Run a <strong>contest</strong> for the best Partyline of the month &mdash; and give out
                            swag if you can. (At Red Bank Green, our best Partyliners get &ldquo;Long Live Local
                            News&rdquo; swag.)</li>
                    </ul>
                    <p>And don&rsquo;t sweat quality control. You don&rsquo;t have to publish everything, and the
                        fear of a garbage flood rarely comes true &mdash; in practice you get only a little garbage
                        and a whole lot of sunsets and rainbows. You know what? Just post the sunsets and rainbows.</p>
                    <p class="pl-tag">LONG LIVE LOCAL NEWS</p>
                </div>
            </div>

          </div>
      </div>
      <div class="right_column">
          <?php Partyline_View::load('admin/global/sidebar') ?>
      </div>
    </div>
      <div class="clearfix"></div>
