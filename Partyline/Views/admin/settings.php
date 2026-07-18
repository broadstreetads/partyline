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
            <div class="box">
                <div class="title"><span class="dashicons dashicons-admin-generic"></span> Partyline Settings</div>
                <div class="content">
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Partyline Key
                            </div>
                            <div class="desc nomargin">
                                This is a password that will be used to authenticate requests to the Partyline API.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.partyline_key" type="text" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="webhook-notice">
                        <strong>Your Twilio Webhook URL is:</strong>
                        <span id="webhook-url" x-text="webhookBase + (settings.partyline_key || '')"></span>
                        <span class="copy-icon" @click="window.partylineCopyToClipboard('#webhook-url')">
                            <span class="dashicons dashicons-admin-page"></span>
                        </span>
                    </div>
					<div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Twilio Account SID
                            </div>
                            <div class="desc nomargin">
                                You will find this on your <a href="https://twilio.com/console" target="_blank">Twilio Console &#x2197;</a>.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.twilio_account_sid" type="text" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="clearfix"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Twilio Auth Token
                            </div>
                            <div class="desc nomargin">
                                This is required to download images from Twilio. You will also find this on your <a href="https://twilio.com/console" target="_blank">Twilio Console &#x2197;</a>.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.twilio_auth_token" type="password" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
					<div class="break"></div>
                    <div class="clearfix"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Partyline Category
                            </div>
                            <div class="desc nomargin">
                                Partylines come in from text message, and their content is inserted into a post. That post has a category.
                                Would you like to set a default category for Partylines? This is a good idea, especially so that you can create
                                dedicated Partyline archive pages and widgets.
                            </div>
                        </div>
                        <div class="control-container">
                            <select x-model="settings.partyline_category">
                                <template x-for="category in categories" :key="category.id">
                                    <option :value="category.id" x-text="category.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                ChatGPT API Key
                            </div>
                            <div class="desc nomargin">
                                Enter your OpenAI API key for ChatGPT integration
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
                            <div class="name nomargin">
                                ChatGPT Prompt
                            </div>
                            <div class="desc nomargin">
                                Custom prompt to use when generating content with ChatGPT
                            </div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="Enter your custom ChatGPT prompt here..." x-model="settings.chatgpt_prompt" class="partyline-settings-textarea"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Email Notifications
                            </div>
                            <div class="desc nomargin">
                                Enter email addresses (one per line) to receive notifications
                            </div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="admin@example.com&#10;editor@example.com&#10;notifications@example.com" x-model="settings.email_notifications" class="partyline-settings-textarea-short"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Contributor App
                            </div>
                            <div class="desc nomargin">
                                Enable the installable web app that lets logged-in contributors submit a
                                Partyline with a photo and a voice note (or typed text). The camera + voice
                                flow runs entirely in the browser.
                            </div>
                        </div>
                        <div class="control-container">
                            <label>
                                <input type="checkbox" x-model="settings.pwa_enabled" />
                                Enable the app
                            </label>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="webhook-notice" x-show="settings.pwa_enabled">
                        <strong>Your Partyline app URL is:</strong>
                        <span id="pwa-url"><?php echo esc_html( Partyline_Pwa::appUrl() ); ?></span>
                        <span class="copy-icon" @click="window.partylineCopyToClipboard('#pwa-url')">
                            <span class="dashicons dashicons-admin-page"></span>
                        </span>
                    </div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Wispr Flow API Key
                            </div>
                            <div class="desc nomargin">
                                Transcribes voice recordings from the app. Get one at
                                <a href="https://wisprflow.ai/developers" target="_blank">Wispr Flow &#x2197;</a>.
                                Without it, contributors can still type their story.
                            </div>
                        </div>
                        <div class="control-container">
                            <input x-model="settings.wispr_api_key" type="password" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>
                    <div class="option">
                        <div class="control-label">
                            <div class="name nomargin">
                                Transcription Dictionary
                            </div>
                            <div class="desc nomargin">
                                Optional. Local names and terms (streets, people, places) to improve
                                transcription accuracy — comma or line separated.
                            </div>
                        </div>
                        <div class="full-control-container partyline-settings-full-control">
                            <textarea placeholder="Broad Street, Monmouth Street, Count Basie Center, Navesink" x-model="settings.wispr_dictionary" class="partyline-settings-textarea-short"></textarea>
                        </div>
                        <div class="partyline-clearboth"></div>
                    </div>
                    <div class="clearfix"></div>
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
