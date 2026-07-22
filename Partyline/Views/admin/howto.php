<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$logo    = esc_url( set_url_scheme( Partyline_Utility::getImageBaseURL() . 'partyline-black.png', 'https' ) );
$app_url = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' );
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

    <nav class="plg-toc">
      <a href="#plg-what">What it is</a>
      <a href="#plg-history">History</a>
      <a href="#plg-uses">Top 10 uses</a>
      <a href="#plg-start">Getting started</a>
      <a href="#plg-success">Making it successful</a>
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
            <button type="button" class="plg-copy" onclick="plgCopy(this)">Copy link</button>
          </div>
          Optionally turn on <em>anonymous submissions</em> to let anyone contribute without an account (they provide a name, email, and phone, and pass a Cloudflare Turnstile check).</div></li>
        <li><span class="s">3</span><div><strong>Text Messages (optional).</strong> To also accept Partylines by text, enable Twilio, get a Twilio phone number, and point its messaging webhook at the URL shown in that section.</div></li>
        <li><span class="s">4</span><div><strong>AI Formatting (optional).</strong> Add an OpenAI API key to auto-format submissions and enable voice dictation. You can fine-tune the editorial voice with the <em>AI writing prompt</em>.</div></li>
      </ol>
      <p style="margin-top:16px;">Click <strong>Save</strong>, then open your app link on your phone and try it: take a photo, add a story, and
        submit. It&rsquo;ll show up in your drafts. To credit texters by name, register them as
        &ldquo;Partyliners&rdquo; on the <a href="<?php echo esc_url( admin_url( 'users.php?partyline_has_phone=1' ) ); ?>">Users page</a>.</p>
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

    <div class="plg-tag">LONG LIVE LOCAL NEWS</div>
    <div class="plg-footer">Questions or a bug to report? Email <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>. Thanks for using Partyline.</div>

  </div>
</div>

<script>
function plgCopy(btn){
  var el = document.getElementById('plg-applink');
  if (!el) return;
  var text = el.getAttribute('data-link') || el.textContent;
  var done = function(){ var o = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = o; }, 1500); };
  if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text.trim()).then(done); }
  else { var t=document.createElement('input'); document.body.appendChild(t); t.value=text.trim(); t.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(t); done(); }
}
</script>
