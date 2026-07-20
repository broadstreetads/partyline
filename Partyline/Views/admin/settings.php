<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="main" x-data="zoneCtrl()">
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
                                    <li><?php echo wp_kses_post( $error ); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </div>
                    <div class="shadow_bottom"></div>
             </div>
         <?php endif; ?>
          <div id="controls">

            <!-- ============ 1. CONTRIBUTOR APP (PWA) ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-smartphone"></span> Contributor App</div>
                <div class="content">
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Enable the app</div>
                            <div class="desc nomargin">
                                Partyline includes a built-in web app where people can submit a Partyline
                                with a photo and a story right from their phone. This is the primary way
                                to collect Partylines. It's on by default.
                            </div>
                        </div>
                        <div class="control-container">
                            <label><input type="checkbox" x-model="settings.pwa_enabled"> Enable the contributor app</label>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="webhook-notice" x-show="settings.pwa_enabled">
                        <strong>Share this link with contributors:</strong>
                        <span id="pwa-url"><?php echo esc_html( Partyline_Pwa::appUrl() ); ?></span>
                        <span class="copy-icon" @click="window.partylineCopyToClipboard('#pwa-url')">
                            <span class="dashicons dashicons-admin-page"></span>
                        </span>
                    </div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Allow anonymous submissions</div>
                            <div class="desc nomargin">
                                By default only logged-in users can submit. Turn this on to let anyone
                                submit without an account. Anonymous submitters type their story (no voice
                                dictation), provide a name &amp; email, and pass a Cloudflare Turnstile check.
                            </div>
                        </div>
                        <div class="control-container">
                            <label><input type="checkbox" x-model="settings.pwa_allow_anonymous"> Allow anonymous submissions</label>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="webhook-notice" x-show="settings.pwa_allow_anonymous" style="background:#fef3c7;border-color:#f59e0b;">
                        <strong>⚠️ Heads up:</strong> this opens submission to the public internet. Anonymous
                        posts always come in as drafts for review, and Cloudflare Turnstile (below) is
                        required to reduce spam.
                    </div>
                    <div class="option" x-show="settings.pwa_allow_anonymous">
                        <div class="control-label">
                            <div class="name nomargin">Cloudflare Turnstile Site Key</div>
                            <div class="desc nomargin">
                                From your Cloudflare dashboard &rarr; Turnstile. Shown in the app to verify
                                anonymous submitters.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.turnstile_site_key" type="text" placeholder="0x4AAA..." />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option" x-show="settings.pwa_allow_anonymous">
                        <div class="control-label">
                            <div class="name nomargin">Cloudflare Turnstile Secret Key</div>
                            <div class="desc nomargin">Used on the server to verify the Turnstile token. Keep this private.</div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.turnstile_secret_key" type="password" placeholder="0x4AAA..." />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <!-- ============ 2. AI FORMATTING (optional) ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-superhero"></span> AI Formatting <span style="font-weight:normal;opacity:.6;">(optional)</span></div>
                <div class="content">
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">OpenAI / ChatGPT API Key</div>
                            <div class="desc nomargin">
                                Optional. If you add an OpenAI API key, Partyline will <strong>auto-format</strong>
                                submissions into a clean blurb and enable <strong>voice dictation</strong>
                                (transcribed with Whisper) in the app. Without a key, contributors just type
                                their story and it's saved as-is.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.chatgpt_api_key" type="password" placeholder="sk-..." />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">AI writing prompt</div>
                            <div class="desc nomargin">
                                The editorial voice used when formatting Partylines. Leave blank for the
                                built-in redbankgreen default.
                            </div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="You are an editor for redbankgreen, a community news site covering Red Bank, New Jersey. Write in a clear, neutral, professional community-news style." x-model="settings.ai_prompt" class="partyline-settings-textarea"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Transcription dictionary</div>
                            <div class="desc nomargin">
                                Optional. Local names and terms (streets, people, places) to improve voice
                                transcription accuracy &mdash; comma or line separated.
                            </div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="Broad Street, Monmouth Street, Count Basie Center, Navesink" x-model="settings.transcription_dictionary" class="partyline-settings-textarea-short"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <!-- ============ 3. TEXT MESSAGES (Twilio, optional) ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-smartphone"></span> Text Messages <span style="font-weight:normal;opacity:.6;">(optional)</span></div>
                <div class="content">
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Accept Partylines by text message</div>
                            <div class="desc nomargin">
                                Optional, secondary channel. To accept Partylines via SMS you need a
                                <a href="https://twilio.com/console" target="_blank">Twilio &#x2197;</a> phone
                                number, and you point its messaging webhook at the URL below.
                            </div>
                        </div>
                        <div class="control-container">
                            <label><input type="checkbox" x-model="settings.twilio_enabled"> Enable Twilio (SMS)</label>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="webhook-notice" x-show="settings.twilio_enabled">
                        <strong>Your Twilio Webhook URL is:</strong>
                        <span id="webhook-url" x-text="webhookBase + (settings.partyline_key || '')"></span>
                        <span class="copy-icon" @click="window.partylineCopyToClipboard('#webhook-url')">
                            <span class="dashicons dashicons-admin-page"></span>
                        </span>
                    </div>
                    <div class="option" x-show="settings.twilio_enabled">
                        <div class="control-label">
                            <div class="name nomargin">Partyline Key</div>
                            <div class="desc nomargin">A shared secret that authenticates the Twilio webhook (part of the URL above).</div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.partyline_key" type="text" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option" x-show="settings.twilio_enabled">
                        <div class="control-label">
                            <div class="name nomargin">Twilio Account SID</div>
                            <div class="desc nomargin">Found on your <a href="https://twilio.com/console" target="_blank">Twilio Console &#x2197;</a>.</div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.twilio_account_sid" type="text" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option" x-show="settings.twilio_enabled">
                        <div class="control-label">
                            <div class="name nomargin">Twilio Auth Token</div>
                            <div class="desc nomargin">Required to download images from Twilio. Also on your Twilio Console.</div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.twilio_auth_token" type="password" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <!-- ============ 4. GENERAL ============ -->
            <div class="box">
                <div class="title"><span class="dashicons dashicons-admin-generic"></span> General</div>
                <div class="content">
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Partyline Category</div>
                            <div class="desc nomargin">
                                Submitted Partylines are filed under this category, so you can build
                                dedicated Partyline archives and widgets.
                            </div>
                        </div>
                        <div class="control-container">
                            <select x-model="settings.partyline_category">
                                <option value="">&mdash; Select a category &mdash;</option>
                                <?php foreach ( (array) $categories as $cat ): ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">Email Notifications</div>
                            <div class="desc nomargin">Email addresses (one per line) to notify when a Partyline comes in.</div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="admin@example.com&#10;editor@example.com" x-model="settings.email_notifications" class="partyline-settings-textarea-short"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                <a target="_blank" href="https://broadstreetads.com/ad-platform/ad-formats/">Not sure what this is? Broadstreet is also an adserver.</a>
                            </div>
                        </div>
                        <div class="save-container">
                            <span class="success" id="save-success">Saved!</span>
                            <input type="button" value="Save" name="" @click="save()" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

        </div>
        <div class="selfie-loading-box" x-show="loadingMessage !== null">
            <img src="<?php echo esc_url( Partyline_Utility::getImageBaseURL() . 'ajax-loader-bar.gif' ); ?>" alt="Loading Image"/>
            <span x-text="loadingMessage"></span>
        </div>
      </div>
      <div class="right_column">
          <?php Partyline_View::load('admin/global/sidebar') ?>
      </div>
    </div>
      <div class="clearfix"></div>
