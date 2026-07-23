<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Partyline settings — reskinned to the shared "plg" design system while keeping
 *  all of the Alpine (zoneCtrl) bindings intact.
 *
 * @var stdClass $settings
 * @var array    $categories
 * @var array    $errors
 */
$main_url        = admin_url( 'admin.php?page=Partyline' );
$partyliners_url = admin_url( 'admin.php?page=Partyline-Partyliners' );
$howto_url       = admin_url( 'admin.php?page=Partyline-HowTo' );
$loader          = esc_url( Partyline_Utility::getImageBaseURL() . 'ajax-loader-bar.gif' );
?>
<?php Partyline_View::load( 'admin/global/plg-styles' ); ?>

<div class="plg" x-data="zoneCtrl()">
  <div class="plg-wrap">

    <?php Partyline_View::load( 'admin/global/plg-hero', array(
        'hero_lead' => 'Set up how your community sends in Partylines: the app, text messages, and optional AI formatting. Work top to bottom, then Save.',
        'hero_toc'  => array(
            array( 'href' => $main_url,        'label' => 'Newsroom' ),
            array( 'href' => $partyliners_url, 'label' => 'Partyliners' ),
            array( 'href' => $howto_url,       'label' => 'How-To' ),
        ),
    ) ); ?>

    <?php if ( ! empty( $errors ) ): ?>
      <section class="plg-section">
        <div class="plg-callout warn">
          <strong>Nice to have you! A few things you may want to take care of:</strong>
          <ul style="margin:8px 0 0; padding-left:18px;">
            <?php foreach ( $errors as $error ): ?>
              <li><?php echo wp_kses_post( $error ); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    <?php endif; ?>

    <!-- ============ GENERAL ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Setup</div>
      <h2>General Settings</h2>

      <div class="plg-field">
        <div class="plg-field-label">Partyline Category</div>
        <div class="plg-field-desc">Submitted Partylines are filed under this category, so you can build dedicated Partyline archives and widgets.</div>
        <select x-model="settings.partyline_category">
          <option value="">Select a category&hellip;</option>
          <?php foreach ( (array) $categories as $cat ): ?>
            <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="plg-field">
        <div class="plg-field-label">Email Notifications</div>
        <div class="plg-field-desc">Email addresses (one per line) to notify when a Partyline comes in.</div>
        <textarea placeholder="admin@example.com&#10;editor@example.com" x-model="settings.email_notifications"></textarea>
      </div>
    </section>

    <!-- ============ CONTRIBUTOR APP ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Channels</div>
      <h2>Contributor App</h2>
      <p class="plg-intro">The built-in web app where people submit a Partyline with a photo and a story right from their phone. This is the primary way to collect Partylines.</p>

      <div class="plg-field">
        <div class="plg-field-label">Enable the app</div>
        <div class="plg-field-desc">On by default. Contributors open your link, snap a photo, add a story, and submit.</div>
        <label class="plg-check"><input type="checkbox" x-model="settings.pwa_enabled"> Enable the contributor app</label>
        <div class="plg-linkbox" x-show="settings.pwa_enabled">
          <span class="lbl">Share link</span>
          <span class="url" id="pwa-url"><?php echo esc_html( Partyline_Pwa::appUrl() ); ?></span>
          <button type="button" class="plg-copy" @click="window.partylineCopyToClipboard('#pwa-url')">Copy</button>
        </div>
      </div>

      <div class="plg-field">
        <div class="plg-field-label">App home screen</div>
        <div class="plg-field-desc">The headline and subtext shown on the app&rsquo;s home screen. Leave blank to use the defaults.</div>
        <input x-model="settings.app_home_title" type="text" placeholder="Send in a Partyline" />
        <textarea x-model="settings.app_home_subtitle" placeholder="Snap a photo and tell us what&#39;s happening around town. We&#39;ll take it from there." style="margin-top:10px;"></textarea>
      </div>

      <div class="plg-field">
        <div class="plg-field-label">Allow anonymous submissions</div>
        <div class="plg-field-desc">By default only logged-in users can submit. Turn this on to let anyone submit without an account. Anonymous submitters type their story (no voice dictation) and provide a name, email &amp; phone.</div>
        <label class="plg-check"><input type="checkbox" x-model="settings.pwa_allow_anonymous"> Allow anonymous submissions</label>
        <div class="plg-callout warn" x-show="settings.pwa_allow_anonymous">
          <strong>&#9888;&#65039; Heads up:</strong> this opens submission to the public internet. Anonymous posts always come in as drafts for review. We use a quick <strong>math challenge</strong> to filter out spam bots automatically. For the most reliable protection, add a <strong>Cloudflare Turnstile</strong> key below.
        </div>
      </div>

      <div class="plg-field" x-show="settings.pwa_allow_anonymous">
        <div class="plg-field-label">Cloudflare Turnstile Site Key <span class="plg-muted" style="font-weight:800;">(optional)</span></div>
        <div class="plg-field-desc">Optional but recommended. From your Cloudflare dashboard &rarr; Turnstile. When set, it&rsquo;s shown in the app on top of the math check for stronger spam protection. Leave blank to rely on the math check alone.</div>
        <input x-model="settings.turnstile_site_key" type="text" placeholder="0x4AAA..." />
      </div>

      <div class="plg-field" x-show="settings.pwa_allow_anonymous">
        <div class="plg-field-label">Cloudflare Turnstile Secret Key</div>
        <div class="plg-field-desc">Used on the server to verify the Turnstile token. Keep this private.</div>
        <input x-model="settings.turnstile_secret_key" type="password" placeholder="0x4AAA..." />
      </div>

      <div class="plg-field">
        <div class="plg-field-label">Public Partyliner signup</div>
        <div class="plg-field-desc">Let people sign up to become Partyliners on a public page (name, phone, email, and an optional address). They confirm by email, and their phone is matched to future text-message submissions. Manage everyone under <a href="<?php echo esc_url( $partyliners_url ); ?>">Partyline &rarr; Partyliners</a>.</div>
        <label class="plg-check"><input type="checkbox" x-model="settings.partyliner_signup_enabled"> Enable public signup</label>
        <div class="plg-linkbox" x-show="settings.partyliner_signup_enabled">
          <span class="lbl">Signup link</span>
          <span class="url" id="signup-url"><?php echo esc_html( Partyline_Pwa::signupUrl() ); ?></span>
          <button type="button" class="plg-copy" @click="window.partylineCopyToClipboard('#signup-url')">Copy</button>
        </div>
      </div>
    </section>

    <!-- ============ TEXT MESSAGES ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Channels</div>
      <h2>Text Messages <span class="plg-muted" style="font-weight:800;">(optional)</span></h2>
      <p class="plg-intro">A secondary channel. To accept Partylines by SMS you need a <a href="https://twilio.com/console" target="_blank" rel="noopener">Twilio</a> phone number, pointed at the webhook below.</p>

      <div class="plg-field">
        <div class="plg-field-label">Accept Partylines by text message</div>
        <div class="plg-field-desc">Enable this, then point your Twilio number's messaging webhook at the URL shown here.</div>
        <label class="plg-check"><input type="checkbox" x-model="settings.twilio_enabled"> Enable Twilio (SMS)</label>
        <div class="plg-linkbox" x-show="settings.twilio_enabled">
          <span class="lbl">Webhook URL</span>
          <span class="url" id="webhook-url" x-text="webhookBase + (settings.partyline_key || '')"></span>
          <button type="button" class="plg-copy" @click="window.partylineCopyToClipboard('#webhook-url')">Copy</button>
        </div>
      </div>

      <div class="plg-field" x-show="settings.twilio_enabled">
        <div class="plg-field-label">Partyline Key</div>
        <div class="plg-field-desc">A shared secret that authenticates the Twilio webhook (part of the URL above).</div>
        <input x-model="settings.partyline_key" type="text" placeholder="" />
      </div>

      <div class="plg-field" x-show="settings.twilio_enabled">
        <div class="plg-field-label">Twilio Account SID</div>
        <div class="plg-field-desc">Found on your <a href="https://twilio.com/console" target="_blank" rel="noopener">Twilio Console</a>.</div>
        <input x-model="settings.twilio_account_sid" type="text" placeholder="" />
      </div>

      <div class="plg-field" x-show="settings.twilio_enabled">
        <div class="plg-field-label">Twilio Auth Token</div>
        <div class="plg-field-desc">Required to download images from Twilio. Also on your Twilio Console.</div>
        <input x-model="settings.twilio_auth_token" type="password" placeholder="" />
      </div>
    </section>

    <!-- ============ AI FORMATTING ============ -->
    <section class="plg-section">
      <div class="plg-eyebrow">Optional</div>
      <h2>AI Formatting <span class="plg-muted" style="font-weight:800;">(optional)</span></h2>
      <p class="plg-intro">Add an OpenAI key to auto-format submissions into a clean blurb and enable voice dictation. Without a key, contributors just type their story and it's saved as-is.</p>

      <div class="plg-field">
        <div class="plg-field-label">OpenAI / ChatGPT API Key</div>
        <div class="plg-field-desc">
          Sign in at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>,
          click <em>Create new secret key</em>, and paste it here. You'll also need
          <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener">billing / credits</a> on the account for it to work.
        </div>
        <input x-model="settings.chatgpt_api_key" type="password" placeholder="sk-..." />
      </div>

      <div class="plg-field">
        <div class="plg-field-label">AI writing prompt</div>
        <div class="plg-field-desc">The editorial voice used when formatting Partylines. Leave blank for the built-in default.</div>
        <textarea class="tall" placeholder="Leave blank to use the built-in default." x-model="settings.ai_prompt"></textarea>
      </div>

      <div class="plg-field">
        <div class="plg-field-label">Transcription dictionary</div>
        <div class="plg-field-desc">Optional. Local names and terms (streets, people, places) to improve voice transcription accuracy, comma or line separated.</div>
        <textarea placeholder="Broad Street, Monmouth Street, Count Basie Center, Navesink" x-model="settings.transcription_dictionary"></textarea>
      </div>
    </section>

    <!-- ============ SAVE ============ -->
    <div class="plg-savebar">
      <span class="hint">
        <template x-if="loadingMessage === null">
          <a target="_blank" href="https://broadstreetads.com/ad-platform/ad-formats/">Not sure what this is? Broadstreet is also an adserver.</a>
        </template>
        <template x-if="loadingMessage !== null">
          <span class="plg-saving"><img src="<?php echo $loader; ?>" alt="" /><span x-text="loadingMessage"></span></span>
        </template>
      </span>
      <button type="button" class="plg-btn" @click="save()">Save changes</button>
    </div>

    <div class="plg-footer">Questions or a bug to report? Email <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>.</div>

  </div>
</div>
