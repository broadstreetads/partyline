(function () {
    'use strict';

    window.zoneCtrl = function () {
        var bootstrap = (typeof window.PartylineSettings !== 'undefined') ? window.PartylineSettings : {};
        var categories = (bootstrap.categories || []).map(function (c) {
            return { id: c.cat_ID, name: c.cat_name };
        });

        return {
            loadingMessage: null,
            settings: bootstrap.settings || {},
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
