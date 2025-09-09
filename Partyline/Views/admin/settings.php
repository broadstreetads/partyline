<script>window.bs_bootstrap = <?php echo json_encode($data) ?>;</script>
<div id="main" ng-app="bs_zones">
      <?php Partyline_View::load('admin/global/header') ?>
      <div class="left_column" ng-controller="ZoneCtrl">
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
                            <input ng-model="data.settings.partyline_key" type="text" placeholder="" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="break"></div>                    
                    <div class="webhook-notice">
                        <strong>Your Twilio Webhook URL is:</strong>
                        <span id="webhook-url"><?php echo esc_url( home_url( '/' ) ); ?>?partyline_twilio_webhook={{data.settings.partyline_key}}</span>
                        <span class="copy-icon" onclick="copyToClipboard('#webhook-url')">
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
                            <input ng-model="data.settings.twilio_account_sid" type="text" placeholder="" />
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
                            <input ng-model="data.settings.twilio_auth_token" type="password" placeholder="" />
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
                            <select ng-model="data.settings.partyline_category">
                                <option ng-repeat="category in data.categories" value="{{category.id}}">{{category.name}}</option>
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
                            <input ng-model="data.settings.chatgpt_api_key" type="password" placeholder="sk-..." />
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
                        <div class="full-control-container" style="clear: both; display: block;">
                            <textarea placeholder="Enter your custom ChatGPT prompt here..." ng-model="data.settings.chatgpt_prompt" style="width: 100%; height: 120px;"></textarea>
                        </div>
                        <div style="clear:both;"></div>
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
                        <div class="full-control-container" style="clear: both; display: block;">
                            <textarea placeholder="admin@example.com&#10;editor@example.com&#10;notifications@example.com" ng-model="data.settings.email_notifications" style="width: 100%; height: 100px;"></textarea>
                        </div>
                        <div style="clear:both;"></div>
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
                            <input type="button" value="Save" name="" ng-click="save()" />
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="selfie-loading-box" ng-show="loadingMessage !== null">
            <img src="<?php echo esc_url( Partyline_Utility::getImageBaseURL() . 'ajax-loader-bar.gif' ); ?>" alt="Loading Image"/>
            <span>{{loadingMessage}}</span>
        </div>
      </div>
      <div class="right_column">
          <?php Partyline_View::load('admin/global/sidebar') ?>
      </div>
    </div>
      <div class="clearfix"></div>
<script>
    (function() {
        var app = angular.module('bs_zones', []);

        app.controller('ZoneCtrl', function($scope, $http) {
            var bootstrap = window.bs_bootstrap;
            $scope.loadingMessage = null;

            $scope.data = { settings: bootstrap.settings || {} };

            var catList = [], found = false;
            for(var i = 0; i < bootstrap.categories.length; i++) {
                catList.push({name: bootstrap.categories[i].cat_name, id: bootstrap.categories[i].cat_ID, selected: false, ticked: false});
            }

            $scope.data.categories = catList;

            $scope.save = function() {
                console.log($scope.data.settings);
                $scope.loadingMessage = 'Saving ...';
                var params = $scope.data.settings;
                $http.post(window.ajaxurl + '?action=partyline_save_settings', params)
                    .success(function(response) {
                        $scope.loadingMessage = null;
                   }).error(function(response) {
                        $scope.loadingMessage = null;
                        alert('There was an error saving the zone information! Try again.');
                   });
            }

            if (!$scope.data.settings.partyline_key) {
                $scope.data.settings.partyline_key = Math.random().toString(36).substring(2, 15);
                $scope.save();
            }
        });

        window.copyToClipboard = function(element) {
            var $temp = jQuery("<input>");
            jQuery("body").append($temp);
            $temp.val(jQuery(element).text()).select();
            document.execCommand("copy");
            $temp.remove();
            alert("Copied to clipboard!");
        }
    })()

</script>
<style>
    .webhook-notice {
        background-color: #f0f6fc;
        border: 1px solid #c8d7e5;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .webhook-notice strong {
        margin-right: 10px;
    }
    #webhook-url {
        font-family: monospace;
        background-color: #e1eaf2;
        padding: 5px 10px;
        border-radius: 4px;
    }
    .copy-icon {
        cursor: pointer;
        color: #0073aa;
    }
    .copy-icon:hover {
        color: #00a0d2;
    }
</style>