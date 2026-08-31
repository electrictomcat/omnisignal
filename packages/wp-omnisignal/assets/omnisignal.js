/**
 * OmniSignal Universal Client-Side Tracking Script (v2.1)
 * https://omnisignal.dev - Pure Signal. Zero Noise.
 *
 * Captures click IDs (gclid, gbraid, wbraid, fbclid, ttclid, msclkid,
 * li_fat_id), persists them first-party, and injects hidden inputs into forms.
 *
 * Safe to load from <head> with or without defer.
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'omnisignal_params';
    var VISITOR_KEY = 'omnisignal_visitor_id';
    var CONSENT_KEY = 'omnisignal_consent';
    var COOKIE_DAYS = 30;
    var MAX_VALUE_LENGTH = 255;

    var PARAM_KEYS = [
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid',
        'msclkid', 'li_fat_id', 'utm_source', 'utm_medium',
        'utm_campaign', 'utm_content', 'utm_term', 'gad_source', 'gad_campaignid'
    ];

    // Read config off the script tag, e.g.
    //   <script src="/omnisignal.js" data-consent="required"></script>
    var script = document.currentScript;
    var config = {
        // 'always'   – store immediately (default; matches non-EEA setups)
        // 'required' – store nothing until omnisignal.grantConsent() is called
        consent: (script && script.getAttribute('data-consent')) || 'always',
        cookieDomain: script && script.getAttribute('data-cookie-domain')
    };

    function isSecure() {
        return window.location.protocol === 'https:';
    }

    function safeLocal(action, key, value) {
        try {
            if (action === 'get') return localStorage.getItem(key);
            if (action === 'set') return localStorage.setItem(key, value);
            if (action === 'remove') return localStorage.removeItem(key);
        } catch (e) {
            // Private mode, blocked storage, quota — never fatal.
        }
        return null;
    }

    function getCookie(name) {
        var escaped = String(name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var match = document.cookie.match(new RegExp('(^|;\\s*)(' + escaped + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    function setCookie(name, value, days) {
        var parts = [name + '=' + encodeURIComponent(value), 'path=/', 'SameSite=Lax'];

        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            parts.push('expires=' + date.toUTCString());
        }

        // An explicit domain is opt-in. Deriving one from the hostname scoped
        // the cookie to the current subdomain, which is the opposite of the
        // cross-subdomain persistence it was meant to provide.
        if (config.cookieDomain) {
            parts.push('domain=' + config.cookieDomain);
        }

        if (isSecure()) {
            parts.push('Secure');
        }

        document.cookie = parts.join('; ');
    }

    function hasConsent() {
        if (config.consent !== 'required') return true;
        return safeLocal('get', CONSENT_KEY) === 'granted' || getCookie(CONSENT_KEY) === 'granted';
    }

    function sanitize(value) {
        if (typeof value !== 'string') return null;
        var trimmed = value.trim();
        if (!trimmed || trimmed.length > MAX_VALUE_LENGTH) return null;
        return trimmed;
    }

    function randomId() {
        var bytes = new Uint8Array(16);

        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
        }

        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        var hex = [];
        for (var j = 0; j < 16; j++) hex.push((bytes[j] + 0x100).toString(16).slice(1));

        return 'omn_' + hex.slice(0, 4).join('') + '-' + hex.slice(4, 6).join('') + '-' +
            hex.slice(6, 8).join('') + '-' + hex.slice(8, 10).join('') + '-' + hex.slice(10).join('');
    }

    var visitorId = null;

    function getVisitorId() {
        if (visitorId) return visitorId;

        visitorId = safeLocal('get', VISITOR_KEY) || getCookie(VISITOR_KEY);

        if (!visitorId) {
            if (!hasConsent()) return null;

            visitorId = randomId();
            safeLocal('set', VISITOR_KEY, visitorId);
            setCookie(VISITOR_KEY, visitorId, COOKIE_DAYS);
        }

        return visitorId;
    }

    function readStored() {
        try {
            var raw = safeLocal('get', STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : {};
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function captureParams() {
        var stored = readStored();

        if (!hasConsent()) return stored;

        var urlParams = new URLSearchParams(window.location.search);
        var updated = false;

        PARAM_KEYS.forEach(function (key) {
            if (!urlParams.has(key)) return;

            var value = sanitize(urlParams.get(key));
            if (!value) return;

            stored[key] = value;
            setCookie('omni_' + key, value, COOKIE_DAYS);
            updated = true;
        });

        if (updated) {
            safeLocal('set', STORAGE_KEY, JSON.stringify(stored));
        }

        return stored;
    }

    function injectInto(form) {
        if (form.getAttribute('data-omnisignal-injected')) return;
        form.setAttribute('data-omnisignal-injected', 'true');

        var stored = readStored();
        var id = getVisitorId();
        var fragment = document.createDocumentFragment();

        function hidden(name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            fragment.appendChild(input);
        }

        if (id) hidden('visitor_id', id);

        Object.keys(stored).forEach(function (key) {
            if (stored[key]) hidden(key, stored[key]);
        });

        form.appendChild(fragment);
    }

    function injectAll() {
        var forms = document.querySelectorAll('form:not([data-omnisignal-injected])');
        for (var i = 0; i < forms.length; i++) injectInto(forms[i]);
    }

    var scheduled = false;

    function scheduleInject() {
        if (scheduled) return;
        scheduled = true;

        var run = function () {
            scheduled = false;
            injectAll();
        };

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(run);
        } else {
            window.setTimeout(run, 16);
        }
    }

    function observeForms() {
        if (!window.MutationObserver || !document.body) return;

        var observer = new MutationObserver(function (mutations) {
            // Only rescan when an element was actually added. Previously every
            // mutation — including this script's own attribute writes — kicked
            // off a full document scan.
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    scheduleInject();
                    return;
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function start() {
        captureParams();
        getVisitorId();
        injectAll();
        observeForms();
    }

    // document.body is null while the parser is still in <head>, which threw
    // for anyone loading this the way tracking scripts are normally loaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    window.omnisignal = {
        getVisitorId: getVisitorId,
        getParams: readStored,

        /**
         * Record consent and capture anything already in the URL.
         * Only meaningful when the script is loaded with data-consent="required".
         */
        grantConsent: function () {
            safeLocal('set', CONSENT_KEY, 'granted');
            setCookie(CONSENT_KEY, 'granted', COOKIE_DAYS);
            captureParams();
            getVisitorId();
            scheduleInject();
        },

        /**
         * Forget this visitor entirely (GDPR erasure on the client side).
         */
        revokeConsent: function () {
            safeLocal('remove', CONSENT_KEY);
            safeLocal('remove', STORAGE_KEY);
            safeLocal('remove', VISITOR_KEY);
            visitorId = null;

            [CONSENT_KEY, VISITOR_KEY].concat(PARAM_KEYS.map(function (k) { return 'omni_' + k; }))
                .forEach(function (name) { setCookie(name, '', -1); });
        },

        track: function (eventName, value, currency, customData) {
            var payload = {
                event_name: eventName,
                value: value || 0.0,
                currency: currency || 'USD',
                visitor_id: getVisitorId(),
                params: readStored(),
                custom_data: customData || {},
                url: window.location.href,
                timestamp: Math.floor(Date.now() / 1000)
            };

            // Dispatches for your own listener to forward. This does not send
            // anything to a server by itself.
            window.dispatchEvent(new CustomEvent('omnisignal:event', { detail: payload }));

            return payload;
        }
    };
})(window, document);
