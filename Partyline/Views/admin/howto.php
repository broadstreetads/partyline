<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$logo    = esc_url( set_url_scheme( Partyline_Utility::getImageBaseURL() . 'partyline-black.png', 'https' ) );
$app_url = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' );
$apply_enabled = class_exists( 'Partyline_Pwa' ) && Partyline_Pwa::applyEnabled();
$apply_url     = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::applyUrl() : '';
$partyliners_admin = admin_url( 'admin.php?page=Partyline-Partyliners' );
$settings_admin    = admin_url( 'admin.php?page=Partyline-Settings' );

$pl_settings = Partyline_Utility::getSettings();
$pl_cat_id   = isset( $pl_settings->partyline_category ) ? (int) $pl_settings->partyline_category : 0;
$pl_cat_link = $pl_cat_id ? get_category_link( $pl_cat_id ) : '';
$pl_cat_name = $pl_cat_id ? get_cat_name( $pl_cat_id ) : '';
?>
<style>
.plg { --ink:#18181b; --muted:#6b7280; --line:#e7e7ea; --surface:#f6f6f8; --accent:#7c3aed; }
.plg { max-width: 880px; margin: 18px auto 64px; color: var(--ink);
       font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
       -webkit-font-smoothing: antialiased; }
.plg *, .plg *::before, .plg *::after { box-sizing: border-box; }
.plg-wrap { background:#fff; border:1px solid var(--line); border-radius:22px; overflow:hidden;
            box-shadow:0 4px 24px rgba(24,24,27,.06); }

.plg-hero { text-align:center; padding:48px 28px 44px; color:#fff;
            background:linear-gradient(135deg,#4f46e5 0%,#9333ea 52%,#ec4899 100%); }
.plg-hero-logo { display:block; width:250px; max-width:72%; height:auto; margin:0 auto 20px;
                 filter:brightness(0) invert(1); }
.plg-hero .plg-lead { color:#fff; text-align:center; margin:0 auto; max-width:48ch; font-size:16px; line-height:1.55; }

.plg-toc { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; padding:18px 24px;
           background:var(--surface); border-bottom:1px solid #f1f1f3; }
.plg-toc a { font-size:13px; font-weight:700; color:var(--ink); background:#fff; border:1px solid var(--line);
             border-radius:999px; padding:7px 14px; text-decoration:none; }
.plg-toc a:hover { border-color:var(--accent); color:var(--accent); }

.plg-section { padding:36px 44px; border-top:1px solid #f1f1f3; scroll-margin-top:40px; }
.plg-toc + .plg-section { border-top:none; }
.plg-eyebrow { font-size:12px; font-weight:800; letter-spacing:.11em; text-transform:uppercase; color:var(--accent); margin-bottom:7px; }
.plg-section h2 { margin:0 0 14px; font-size:25px; font-weight:800; letter-spacing:-.01em; }
.plg-section h3 { margin:26px 0 8px; font-size:17px; font-weight:800; }
.plg p { font-size:15.5px; line-height:1.72; color:#3f3f46; margin:0 0 14px; }
.plg p:last-child { margin-bottom:0; }
.plg a { color:var(--accent); text-decoration:none; font-weight:600; }
.plg a:hover { text-decoration:underline; }
.plg .plg-sub { font-style:italic; color:var(--muted); margin-top:-4px; }
.plg .plg-note { background:var(--surface); border-radius:12px; padding:14px 16px; font-size:14.5px; margin-top:4px; }

.plg-features { display:flex; flex-direction:column; gap:14px; margin:8px 0 18px; }
.plg-feature { display:flex; gap:14px; align-items:flex-start; }
.plg-feature .ico { flex:0 0 auto; width:42px; height:42px; border-radius:11px; background:var(--surface);
                    display:flex; align-items:center; justify-content:center; font-size:21px; }
.plg-feature strong { font-size:15px; display:block; }
.plg-feature span { font-size:14px; color:var(--muted); line-height:1.55; }

.plg-tens { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:13px; }
.plg-tens li { display:flex; gap:14px; align-items:flex-start; }
.plg-tens .n { flex:0 0 auto; width:28px; height:28px; border-radius:50%; background:var(--ink); color:#fff;
               font-size:13px; font-weight:800; display:flex; align-items:center; justify-content:center; margin-top:1px; }
.plg-tens li > div { font-size:15px; line-height:1.55; color:#3f3f46; }

.plg-steps { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:20px; }
.plg-steps li { display:flex; gap:15px; align-items:flex-start; }
.plg-steps .s { flex:0 0 auto; width:31px; height:31px; border-radius:10px; color:#fff; font-size:14px; font-weight:800;
                display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#6366f1,#a855f7); }
.plg-steps li > div { font-size:15px; line-height:1.62; color:#3f3f46; }

.plg-linkbox { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:11px 0 2px; }
.plg-linkbox code { font-family:Menlo,Consolas,monospace; font-size:14px; background:var(--surface); border:1px solid var(--line);
                    border-radius:10px; padding:9px 13px; color:var(--ink); }
.plg-copy { border:none; background:var(--ink); color:#fff; font-size:13px; font-weight:700; padding:9px 15px; border-radius:10px; cursor:pointer; }
.plg-copy:hover { background:#000; }

.plg-callout { background:linear-gradient(135deg,#fff1e6,#ffe0ec); border:1px solid #fbd6c8; border-radius:14px;
               padding:16px 18px; font-size:15px; line-height:1.6; color:#7c2d3a; margin-top:4px; }

.plg-faqgroup { font-size:13px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); margin:24px 0 10px; }
.plg-faqgroup:first-of-type { margin-top:6px; }
.plg-faq { display:flex; flex-direction:column; gap:9px; }
.plg-faq details { border:1px solid var(--line); border-radius:12px; background:#fff; }
.plg-faq details[open] { border-color:#ddd6f3; background:#fbfaff; }
.plg-faq summary { list-style:none; cursor:pointer; padding:14px 44px 14px 17px; font-size:15px; font-weight:700; color:var(--ink); position:relative; }
.plg-faq summary::-webkit-details-marker { display:none; }
.plg-faq summary::after { content:"+"; position:absolute; right:17px; top:50%; transform:translateY(-50%);
                          font-size:20px; font-weight:700; color:var(--accent); line-height:1; }
.plg-faq details[open] summary::after { content:"\2212"; }
.plg-faq .a { padding:0 17px 15px; }
.plg-faq .a p { font-size:14.5px; line-height:1.65; color:#3f3f46; margin:0 0 10px; }
.plg-faq .a p:last-child { margin-bottom:0; }

.plg-video { padding:0; background:var(--ink); }
.plg-video .frame { position:relative; width:100%; padding-top:56.25%; background:#000; }
.plg-video .frame iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
.plg-video .cap { padding:15px 22px; text-align:center; font-size:13.5px; line-height:1.55; color:#cbd5e1; }

.plg-tag { text-align:center; font-weight:800; letter-spacing:.22em; font-size:15px; color:#fff; padding:24px; background:var(--ink); }
.plg-footer { text-align:center; padding:20px; font-size:13px; color:var(--muted); }

@media (max-width:600px){ .plg-section{padding:28px 22px;} .plg-hero{padding:40px 20px;} }
</style>

<div class="plg">
  <div class="plg-wrap">

    <header class="plg-hero">
      <img class="plg-hero-logo" src="<?php echo $logo; ?>" alt="Partyline">
      <p class="plg-lead">The community-powered newsroom. Here&rsquo;s what it is, where it came from, and how to make it thrive in your town.</p>
    </header>

    <section id="plg-watch" class="plg-video">
      <div class="frame">
        <iframe src="https://www.youtube-nocookie.com/embed/f4vUM_DGjPM" title="Partyline: an explainer and how-to from Kenny Katzgrau" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
      </div>
      <div class="cap">A quick explainer and how-to from Kenny Katzgrau, publisher of redbankgreen and creator of Partyline.</div>
    </section>

    <nav class="plg-toc">
      <a href="#plg-watch">Watch first</a>
      <a href="#plg-what">What it is</a>
      <a href="#plg-history">History</a>
      <a href="#plg-uses">Top 10 uses</a>
      <a href="#plg-start">Getting started</a>
      <a href="#plg-success">Making it successful</a>
      <a href="#plg-faq">FAQ</a>
    </nav>

    <!-- What is -->
    <section id="plg-what" class="plg-section">
      <div class="plg-eyebrow">Overview</div>
      <h2>What is Partyline?</h2>
      <p>Partyline turns your readers into contributors. Anyone in your community can send in a photo and a few
        words, from the built-in app on their phone or by text message, and it lands in your newsroom as a
        <strong>draft</strong>, ready for you to review and publish.</p>
      <div class="plg-features">
        <div class="plg-feature"><span class="ico">📱</span><div><strong>The app (primary)</strong><span>An installable web app at your Partyline link. Snap a photo, add the story (type it or dictate it), and submit.</span></div></div>
        <div class="plg-feature"><span class="ico">💬</span><div><strong>Text messages (optional)</strong><span>Accept Partylines by SMS/MMS through Twilio.</span></div></div>
        <div class="plg-feature"><span class="ico">✨</span><div><strong>AI formatting (optional)</strong><span>Tidies up spelling and grammar and powers voice dictation, while staying true to the contributor&rsquo;s own words.</span></div></div>
      </div>
      <p class="plg-note">Nothing goes live on its own. Every submission arrives as a draft, so an editor always has the final say.</p>
    </section>

    <!-- History -->
    <section id="plg-history" class="plg-section">
      <div class="plg-eyebrow">Our story</div>
      <h2>A brief history</h2>
      <p>Partyline was invented at <strong>Red Bank Green</strong> in Red Bank, New Jersey. We loved the old Red Bank
        newspaper, the <em>Red Bank Register</em>, which covered every tiny little thing that happened in town. In
        today&rsquo;s news environment, that level of coverage just isn&rsquo;t economically feasible, but we also
        realized that <strong>local news is the original social media</strong>, and that it doesn&rsquo;t have to be
        a one-way street.</p>
      <p>If a community captures the things it thinks are important, the moments that matter to the people who live
        there, those things can be news too. Together, we believe a community can paint a better picture of a place
        and tell its story better than ever, maybe even better than the <em>Register</em> did. It&rsquo;s a
        deceptively simple idea, with a number of benefits that only reveal themselves in the unfolding of its use.</p>
      <p>Want the fuller thinking? Read this
        <a href="https://localmedia.org/2023/11/qa-with-redbankgreen-publisher-kenny-katzgrau/" target="_blank" rel="noopener">Q&amp;A with the Local Media Association</a>,
        or watch <a href="https://www.youtube.com/watch?v=6940dkt2weI" target="_blank" rel="noopener">this talk from the Colorado Press Association</a> (2024).</p>
    </section>

    <!-- Top 10 -->
    <section id="plg-uses" class="plg-section">
      <div class="plg-eyebrow">Ideas</div>
      <h2>Top 10 uses for Partyline</h2>
      <p class="plg-sub">A lovingly curated list from experience, originally shared with LINA Publishers in Australia.</p>
      <ol class="plg-tens">
        <li><span class="n">1</span><div><strong>Cover stories you&rsquo;d never get to otherwise</strong>, like proms, graduations, and scout ceremonies.</div></li>
        <li><span class="n">2</span><div><strong>Post ridiculous stuff that wouldn&rsquo;t fly as a &ldquo;real&rdquo; article</strong>, and watch the traffic surprise you.</div></li>
        <li><span class="n">3</span><div><strong>File an initial post when a breaking story hits</strong>, straight from the street.</div></li>
        <li><span class="n">4</span><div><strong>Capture the in-the-moment energy</strong> of a parade, protest, or town meeting.</div></li>
        <li><span class="n">5</span><div><strong>Share sunsets and puppy pics</strong>, without feeling like a complete sellout.</div></li>
        <li><span class="n">6</span><div><strong>Keep your advertisers happy</strong> by actually posting their community event (and looking cool doing it).</div></li>
        <li><span class="n">7</span><div><strong>Turn your readers into local mini-celebrities</strong>, and spark conversations offline.</div></li>
        <li><span class="n">8</span><div><strong>Give voice to people who never get quoted.</strong> Partyline lowers the barrier to entry.</div></li>
        <li><span class="n">9</span><div><strong>Redirect PR people</strong> to a more productive outlet than your inbox.</div></li>
        <li><span class="n">10</span><div><strong>Reclaim your newsroom&rsquo;s social media power</strong>, because you were doing it before Facebook anyway.</div></li>
      </ol>
      <h3>What to expect</h3>
      <p>Every Partyline arrives as a draft, so you always decide what runs. Expect a steady, manageable stream rather
        than a firehose, and treat what comes in as raw material. A quick edit (or the optional AI cleanup) turns a
        rough text into publishable copy. Quality will vary, and that&rsquo;s fine: the goal is coverage you&rsquo;d
        never get any other way.</p>
    </section>

    <!-- Getting started -->
    <section id="plg-start" class="plg-section">
      <div class="plg-eyebrow">Setup</div>
      <h2>Getting started</h2>
      <p>Head to <strong>Partyline &rarr; Settings</strong> and work top to bottom:</p>
      <ol class="plg-steps">
        <li><span class="s">1</span><div><strong>General Settings.</strong> Choose the <em>Partyline Category</em> that submissions are filed under, and add the <em>Email Notifications</em> addresses that should be pinged when one comes in.</div></li>
        <li><span class="s">2</span><div><strong>Contributor App.</strong> It&rsquo;s on by default. Share your app link with the community; people submit there, and can install it to their home screen like an app:
          <div class="plg-linkbox">
            <code id="plg-applink" data-link="<?php echo esc_attr( $app_url ); ?>"><?php echo esc_html( $app_url ); ?></code>
            <button type="button" class="plg-copy" onclick="plgCopy(this,'plg-applink')">Copy link</button>
          </div>
          Optionally turn on <em>anonymous submissions</em> to let anyone contribute without an account (they provide a name, email, and phone, and pass a Cloudflare Turnstile check).</div></li>
        <li><span class="s">3</span><div><strong>Text Messages (optional).</strong> To also accept Partylines by text, enable Twilio, get a Twilio phone number, and point its messaging webhook at the URL shown in that section.</div></li>
        <li><span class="s">4</span><div><strong>AI Formatting (optional).</strong> Add an OpenAI API key to auto-format submissions and enable voice dictation. You can fine-tune the editorial voice with the <em>AI writing prompt</em>.</div></li>
      </ol>
      <p style="margin-top:16px;">Click <strong>Save</strong>, then open your app link on your phone and try it: take a photo, add a story, and
        submit. It&rsquo;ll show up in your drafts.</p>

      <h3>Where your Partylines appear</h3>
      <p>Every submission is filed under the <em>Partyline Category</em> you chose in Settings. Once you review and
        publish one, it lands on that category&rsquo;s archive page &mdash; the public home for all your Partylines.
        <?php if ( $pl_cat_link ): ?>
          Here&rsquo;s yours:</p>
        <div class="plg-linkbox">
          <code id="plg-catlink" data-link="<?php echo esc_attr( $pl_cat_link ); ?>"><?php echo esc_html( $pl_cat_link ); ?></code>
          <button type="button" class="plg-copy" onclick="plgCopy(this,'plg-catlink')">Copy link</button>
          <a class="plg-copy" style="background:var(--accent);text-decoration:none;" href="<?php echo esc_url( $pl_cat_link ); ?>" target="_blank" rel="noopener">Visit&nbsp;&rarr;</a>
        </div>
        <?php else: ?>
          Pick a <em>Partyline Category</em> in <a href="<?php echo esc_url( $settings_admin ); ?>">Settings</a> first, and this becomes the page to send readers to.</p>
        <?php endif; ?>
      <p class="plg-note">💡 A popular setup: add a <strong>Posts widget</strong> (or a Query Loop block) to your site&rsquo;s
        home page, restricted to the Partyline category<?php echo $pl_cat_name ? ' (&ldquo;' . esc_html( $pl_cat_name ) . '&rdquo;)' : ''; ?>. That surfaces the
        latest Partylines right on your front page, where readers will actually see them &mdash; and it&rsquo;s a big part of
        making the whole thing feel alive.</p>

      <h3>Recruiting Partyliners</h3>
      <p>To credit texters by name, add them on the <a href="<?php echo esc_url( $partyliners_admin ); ?>">Partyliners</a> page.
        Even better, turn on <strong>Public Partyliner applications</strong> in
        <a href="<?php echo esc_url( $settings_admin ); ?>">Settings</a> and share this link so readers can register
        themselves (name, phone, email, and an optional address). Their phone number is then matched to any Partyline
        they text in.</p>
      <?php if ( $apply_enabled && $apply_url ): ?>
        <div class="plg-linkbox">
          <code id="plg-applylink" data-link="<?php echo esc_attr( $apply_url ); ?>"><?php echo esc_html( $apply_url ); ?></code>
          <button type="button" class="plg-copy" onclick="plgCopy(this,'plg-applylink')">Copy link</button>
        </div>
      <?php else: ?>
        <p class="plg-note">Public applications are currently off. Flip on <em>Public Partyliner applications</em> in Settings to activate your application link.</p>
      <?php endif; ?>
    </section>

    <!-- Success -->
    <section id="plg-success" class="plg-section">
      <div class="plg-eyebrow">Growth</div>
      <h2>Making it successful in your community</h2>
      <p>The single most important thing is a <strong>persistent call to action</strong>. You can&rsquo;t tell people
        once. You have to build the habit, until the moment someone sees something great in town, they instinctively
        think, <em>&ldquo;This should be a Partyline.&rdquo;</em></p>
      <ol class="plg-steps">
        <li><span class="s">1</span><div>Post about it and tell everybody.</div></li>
        <li><span class="s">2</span><div>Create a clear Partyline call to action: put the link (and a QR code) everywhere.</div></li>
        <li><span class="s">3</span><div>Continually beat the drum.</div></li>
      </ol>
      <h3>A few things that work well</h3>
      <div class="plg-features">
        <div class="plg-feature"><span class="ico">📣</span><div><strong>Send a dedicated newsletter</strong><span>Introduce Partyline to your whole list.</span></div></div>
        <div class="plg-feature"><span class="ico">🏆</span><div><strong>Run a monthly contest</strong><span>Reward the best Partyline, and give out swag if you can. (At Red Bank Green, our best Partyliners get &ldquo;Long Live Local News&rdquo; swag.)</span></div></div>
      </div>
      <div class="plg-callout">And don&rsquo;t sweat quality control. You don&rsquo;t have to publish everything, and the fear of a garbage
        flood rarely comes true. In practice you get only a little garbage and a whole lot of sunsets and rainbows.
        You know what? <strong>Just post the sunsets and rainbows.</strong></div>
    </section>

    <!-- FAQ -->
    <section id="plg-faq" class="plg-section">
      <div class="plg-eyebrow">Questions</div>
      <h2>Frequently asked questions</h2>
      <p class="plg-sub">The edge cases and the &ldquo;wait, what happens if&hellip;&rdquo; questions, especially for editors new to letting the community in.</p>

      <div class="plg-faqgroup">Logged-in vs. logged-out contributors</div>
      <div class="plg-faq">
        <details>
          <summary>Do people need an account to submit?</summary>
          <div class="a">
            <p>It depends on your setup. The contributor app works for logged-in users out of the box. If you turn on <strong>Allow anonymous submissions</strong> in Settings, anyone can submit without an account. If anonymous submissions are off, a logged-out visitor who opens the app is simply sent to the login screen.</p>
            <p><strong>To set someone up with a Partyliner account</strong>, you have three options: open the <a href="<?php echo esc_url( $partyliners_admin ); ?>">Partyliners</a> page and use <em>Add a Partyliner</em> (just their name, phone, and email); turn on <strong>Public Partyliner applications</strong> in <a href="<?php echo esc_url( $settings_admin ); ?>">Settings</a> so readers can apply; or add them the usual WordPress way under <strong>Users &rarr; Add New</strong>. However they&rsquo;re created, their phone number is what links a text message back to their account.</p>
          </div>
        </details>
        <details>
          <summary>What&rsquo;s the difference between a logged-in and an anonymous submission?</summary>
          <div class="a"><p>Logged-in contributors get the full app: snap a photo and <em>dictate</em> the story, which is transcribed and lightly cleaned up by AI. Anonymous contributors <em>type</em> their story (no voice dictation or AI), provide a name, email, and phone, and pass a quick spam check. Either way, it lands in your newsroom as a draft.</p></div>
        </details>
        <details>
          <summary>How do texters (SMS) fit in?</summary>
          <div class="a">
            <p>If you enable <strong>Text Messages</strong>, people can send a photo and a note by text. Partyline matches the sender&rsquo;s phone number to a registered Partyliner (see the <a href="<?php echo esc_url( $partyliners_admin ); ?>">Partyliners</a> page). If it&rsquo;s a number you don&rsquo;t recognize, the post simply comes in credited as &ldquo;Anonymous Partyliner.&rdquo;</p>
            <p><strong>Texting runs on Twilio</strong>, so it does take a little setup: you&rsquo;ll need a <a href="https://twilio.com" target="_blank" rel="noopener">Twilio</a> account and a Twilio phone number for people to text. Then, in <a href="<?php echo esc_url( $settings_admin ); ?>">Settings &rarr; Text Messages</a>, turn on Twilio and paste in your <strong>Account SID</strong> and <strong>Auth Token</strong> (both from your Twilio Console). Finally, copy the <strong>webhook URL</strong> Partyline shows you and set it as the messaging webhook on your Twilio number, so incoming texts are handed off to Partyline. The app and public applications need none of this; Twilio is only for the text-message channel.</p>
          </div>
        </details>
        <details>
          <summary>Someone submitted anonymously but they&rsquo;re actually a registered Partyliner. What happens?</summary>
          <div class="a"><p>When you publish it, Partyline matches them by email or phone, attributes the published post to their account, and emails them that it&rsquo;s live. Until you publish, it stays a draft credited to the name they typed, so an unverified &ldquo;I&rsquo;m really so-and-so&rdquo; never touches a real account without your say-so.</p></div>
        </details>
      </div>

      <div class="plg-faqgroup">About submitted Partylines</div>
      <div class="plg-faq">
        <details>
          <summary>Does anything get published automatically?</summary>
          <div class="a"><p><strong>No.</strong> Every submission (app, text, or anonymous) arrives as a <strong>draft</strong> for you to review. Nothing goes live until you publish it. The only exception: an editor or administrator can tick &ldquo;Post immediately&rdquo; on their <em>own</em> submission.</p></div>
        </details>
        <details>
          <summary>Who can &ldquo;Post immediately&rdquo;?</summary>
          <div class="a"><p>Only editors and administrators even see that checkbox. Regular contributors and anonymous submitters can&rsquo;t publish anything; their Partylines are always drafts.</p></div>
        </details>
        <details>
          <summary>Where do submissions show up, and how will I know?</summary>
          <div class="a"><p>They appear on the <strong>Partyline</strong> screen (your newsroom inbox), newest first. They also show up right in your normal WordPress <strong>Posts</strong> list as drafts, so you can review, edit, and publish them wherever you already work. And you&rsquo;ll get an email notification at whatever addresses you set under <strong>General Settings &rarr; Email Notifications</strong>.</p></div>
        </details>
        <details>
          <summary>What happens to the author when I publish?</summary>
          <div class="a"><p>If the submitter matches a registered Partyliner (by email or phone), the published post is attributed to <em>them</em> and they get a &ldquo;your Partyline is live&rdquo; email. If they&rsquo;re not a known user, it stays attributed to the site admin and the &ldquo;Submitted by&rdquo; credit still shows the name they gave.</p></div>
        </details>
        <details>
          <summary>Is the submitter&rsquo;s contact info public?</summary>
          <div class="a"><p>No. Their <em>name</em> appears in a small &ldquo;Submitted by&rdquo; credit line on the post, but their email and phone are stored privately on the post for your reference, and they&rsquo;re never shown on the site.</p></div>
        </details>
      </div>

      <div class="plg-faqgroup">For nervous editors</div>
      <div class="plg-faq">
        <details>
          <summary>Will spam or junk end up on my site?</summary>
          <div class="a"><p>It can&rsquo;t publish itself; everything is a draft you approve. On top of that, the public forms are guarded by a quick <strong>math challenge</strong> and a hidden honeypot to stop bots, with optional <strong>Cloudflare Turnstile</strong> for extra strength.</p></div>
        </details>
        <details>
          <summary>What if I get flooded with submissions?</summary>
          <div class="a"><p>Honestly? You probably won&rsquo;t, and if anything, the opposite is the real work. A healthy flow of Partylines is something you have to actively <em>grow</em> (that&rsquo;s what <a href="#plg-success">Making it successful</a> is all about), so a day with &ldquo;too many submissions&rdquo; would be a genuinely good problem to have. And because everything arrives as a draft, even a busy day just means more good stuff to pick from.</p></div>
        </details>
        <details>
          <summary>Can I edit a submission before it runs?</summary>
          <div class="a"><p>Absolutely. It&rsquo;s an ordinary WordPress draft, so you can fix the title, rewrite the text, swap or crop the photo, whatever you need, before you publish.</p></div>
        </details>
        <details>
          <summary>If I <em>don&rsquo;t</em> publish something, does the submitter find out?</summary>
          <div class="a"><p>No. Contributors are only emailed <em>if and when</em> you publish their Partyline. Leaving it as a draft or trashing it sends nothing, so there&rsquo;s no awkward &ldquo;your post was rejected&rdquo; message.</p></div>
        </details>
        <details>
          <summary>I don&rsquo;t want one of these channels. Can I turn it off?</summary>
          <div class="a"><p>Yes. The contributor app, anonymous submissions, text messages, and public applications are each independent toggles in <a href="<?php echo esc_url( $settings_admin ); ?>">Settings</a>. Turn on only what you want and leave the rest off.</p></div>
        </details>
        <details>
          <summary>Do I need the AI or a Cloudflare account to use Partyline?</summary>
          <div class="a"><p>No, both are optional. Without an OpenAI key, contributors just type their story and it&rsquo;s saved as-is. Without Cloudflare Turnstile, the built-in math check still guards your public forms.</p></div>
        </details>
      </div>
    </section>

    <div class="plg-tag">LONG LIVE LOCAL NEWS</div>
    <div class="plg-footer">Questions or a bug to report? Email <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>. Thanks for using Partyline.</div>

  </div>
</div>

<script>
function plgCopy(btn, id){
  var el = document.getElementById(id || 'plg-applink');
  if (!el) return;
  var text = el.getAttribute('data-link') || el.textContent;
  var done = function(){ var o = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = o; }, 1500); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text.trim()).then(done); }
  else { var t=document.createElement('input'); document.body.appendChild(t); t.value=text.trim(); t.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(t); done(); }
}
</script>
