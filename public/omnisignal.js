/**
 * OmniSignal Universal Client-Side Tracking Script (v2.0)
 * https://omnisignal.dev - Pure Signal. Zero Noise.
 *
 * Automatically captures Click IDs (gclid, gbraid, wbraid, fbclid, ttclid, msclkid, li_fat_id),
 * persists first-party tracking across subdomains, and injects hidden inputs into HTML forms.
 */
(function(window, document) {
    'use strict';

    var STORAGE_KEY = 'omnisignal_params';
    var VISITOR_KEY = 'omnisignal_visitor_id';
    var COOKIE_DAYS = 30;

    var PARAM_KEYS = [
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 
        'msclkid', 'li_fat_id', 'utm_source', 'utm_medium', 
        'utm_campaign', 'utm_content', 'utm_term', 'gad_source', 'gad_campaignid'
    ];

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        var domain = window.location.hostname.includes('.') ? '; domain=.' + window.location.hostname.replace(/^www\./, '') : '';
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/' + domain + '; SameSite=Lax';
    }

    function getUUID() {
        var visitorId = localStorage.getItem(VISITOR_KEY) || getCookie(VISITOR_KEY);
        if (!visitorId) {
            visitorId = 'omn_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
            try { localStorage.setItem(VISITOR_KEY, visitorId); } catch(e) {}
            setCookie(VISITOR_KEY, visitorId, COOKIE_DAYS);
        }
        return visitorId;
    }

    function captureParams() {
        var urlParams = new URLSearchParams(window.location.search);
        var stored = {};
        try {
            stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch(e) {}

        var updated = false;
        PARAM_KEYS.forEach(function(key) {
            if (urlParams.has(key)) {
                stored[key] = urlParams.get(key);
                setCookie('omni_' + key, stored[key], COOKIE_DAYS);
                updated = true;
            }
        });

        if (updated) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(stored)); } catch(e) {}
        }
        return stored;
    }

    function injectFormInputs() {
        var stored = captureParams();
        var visitorId = getUUID();

        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            // Avoid double injection
            if (form.getAttribute('data-omnisignal-injected')) return;
            form.setAttribute('data-omnisignal-injected', 'true');

            // Inject visitor_id
            var vInput = document.createElement('input');
            vInput.type = 'hidden';
            vInput.name = 'visitor_id';
            vInput.value = visitorId;
            form.appendChild(vInput);

            // Inject click and UTM params
            Object.keys(stored).forEach(function(key) {
                if (stored[key]) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = stored[key];
                    form.appendChild(input);
                }
            });
        });
    }

    // Initialize
    var visitorId = getUUID();
    var currentParams = captureParams();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectFormInputs);
    } else {
        injectFormInputs();
    }

    // Observe dynamically added forms (e.g. React/Vue/Livewire/modals)
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            injectFormInputs();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Public OmniSignal API
    window.omnisignal = {
        getVisitorId: function() { return visitorId; },
        getParams: function() { return captureParams(); },
        track: function(eventName, value, currency, customData) {
            var payload = {
                event_name: eventName,
                value: value || 0.0,
                currency: currency || 'USD',
                visitor_id: visitorId,
                params: captureParams(),
                custom_data: customData || {},
                url: window.location.href,
                timestamp: Math.floor(Date.now() / 1000)
            };

            // Dispatch CustomEvent for browser listeners
            window.dispatchEvent(new CustomEvent('omnisignal:event', { detail: payload }));
            return payload;
        }
    };

})(window, document);
