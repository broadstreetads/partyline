(function () {
    'use strict';

    window.zoneCtrl = function () {
        var bootstrap = (typeof window.PartylineSettings !== 'undefined') ? window.PartylineSettings : {};
        var categories = (bootstrap.categories || []).map(function (c) {
            return { id: String(c.cat_ID), name: c.cat_name };
        });

        var settings = bootstrap.settings || {};
        // wp_localize_script leaves nested numbers as numbers, but the <select>
        // option values are strings. Without this coercion Alpine can't match the
        // saved category to an option and falls back to the first one — so the
        // saved value looks like it "didn't stick" on reload.
        if (settings.partyline_category !== undefined && settings.partyline_category !== null) {
            settings.partyline_category = String(settings.partyline_category);
        }

        // The contributor app is on by default.
        if (settings.pwa_enabled === undefined) { settings.pwa_enabled = true; }
        // Twilio (SMS) defaults on when credentials already exist (back-compat).
        if (settings.twilio_enabled === undefined) {
            settings.twilio_enabled = !!(settings.twilio_account_sid && String(settings.twilio_account_sid).length);
        }

        return {
            loadingMessage: null,
            settings: settings,
            categories: categories,
            webhookBase: bootstrap.webhookBase || '',

            init: function () {
                if (!this.settings.partyline_key) {
                    this.settings.partyline_key = Math.random().toString(36).substring(2, 15);
                    this.save();
                }
            },

            save: function () {
                var self = this;
                self.loadingMessage = 'Saving ...';
                var url = bootstrap.ajaxUrl
                    + '?action=partyline_save_settings'
                    + '&_wpnonce=' + encodeURIComponent(bootstrap.nonce || '');
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(self.settings),
                    credentials: 'same-origin'
                }).then(function (response) {
                    self.loadingMessage = null;
                    if (!response.ok) {
                        throw new Error('save failed');
                    }
                }).catch(function () {
                    self.loadingMessage = null;
                    alert('There was an error saving the settings! Try again.');
                });
            }
        };
    };

    window.partylineCopyToClipboard = function (selector) {
        var el = document.querySelector(selector);
        if (!el) return;
        var text = el.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                alert('Copied to clipboard!');
            });
            return;
        }
        var temp = document.createElement('input');
        document.body.appendChild(temp);
        temp.value = text;
        temp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(temp);
        alert('Copied to clipboard!');
    };
})();
