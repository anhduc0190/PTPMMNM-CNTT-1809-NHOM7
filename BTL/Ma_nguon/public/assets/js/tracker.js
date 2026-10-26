/**
 * tracker.js — Chức năng 2: Đoạn JavaScript gắn vào website thử.
 *
 * Cách dùng (gắn vào <head> của website thử):
 *   <script src="http://127.0.0.1:8200/assets/js/tracker.js"></script>
 *   <script>
 *     WA.init({
 *       apiKey:       'YOUR_64_CHAR_API_KEY',
 *       collectUrl:   'http://127.0.0.1:8200/collect.php',
 *       sessionToken: localStorage.getItem('wa_session') || WA.newSession(),
 *     });
 *   </script>
 *
 * Bảo đảm an toàn:
 * - Không gửi nội dung trường nhập (value của <input>, <textarea>).
 * - Không thu thập dấu vân tay thiết bị.
 * - Không theo dõi chéo website.
 * - idempotency_key: SHA-256(apiKey + sessionToken + eventType + pagePath + target + clientTs)
 */
'use strict';

(function (window) {
  var WA = {};
  var _cfg = null;

  /** Tạo session token mới (128-bit random hex). */
  WA.newSession = function () {
    var arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    var token = Array.from(arr).map(function (b) {
      return b.toString(16).padStart(2, '0');
    }).join('');
    localStorage.setItem('wa_session', token);
    return token;
  };

  /**
   * Khởi tạo tracker.
   * @param {Object} config
   *   - apiKey {string}        64-char hex API key của website
   *   - collectUrl {string}    URL của collect.php
   *   - sessionToken {string}  Token phiên (lấy từ localStorage hoặc WA.newSession())
   */
  WA.init = function (config) {
    if (!config || !config.apiKey || !config.collectUrl) {
      console.warn('[WA] Thiếu apiKey hoặc collectUrl.');
      return;
    }
    _cfg = {
      apiKey:       config.apiKey,
      collectUrl:   config.collectUrl,
      sessionToken: config.sessionToken || WA.newSession(),
    };

    // Tự động ghi nhận pageview
    WA.trackPageview();

    // Lắng nghe các nút có data-wa-event
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-wa-event]');
      if (!el) return;
      var eventType = el.getAttribute('data-wa-event') || 'click';
      var target    = el.id ? '#' + el.id : (el.getAttribute('data-wa-target') || '');
      WA.track(eventType, { target: target });
    });

    // Lắng nghe form submit (không ghi nội dung trường nhập)
    document.addEventListener('submit', function (e) {
      var form   = e.target;
      var formId = form.id ? '#' + form.id : '';
      WA.track('form_submit', { target: formId });
    });
  };

  /** Ghi nhận lượt xem trang. */
  WA.trackPageview = function () {
    WA.track('pageview', {});
  };

  /**
   * Ghi nhận một sự kiện bất kỳ.
   * @param {string} eventType  pageview|click|form_submit|download
   * @param {Object} extra      { target: string }  — KHÔNG chứa nội dung nhập liệu
   */
  WA.track = function (eventType, extra) {
    if (!_cfg) {
      console.warn('[WA] Gọi WA.init() trước.');
      return;
    }
    extra = extra || {};
    var pagePath  = window.location.pathname || '/';
    var target    = (extra.target || '').substring(0, 255);
    var clientTs  = Math.floor(Date.now() / 1000);

    // Tạo idempotency key
    _sha256Hex(
      _cfg.apiKey + '|' + _cfg.sessionToken + '|' + eventType + '|' + pagePath + '|' + target + '|' + clientTs
    ).then(function (ikey) {
      var payload = {
        api_key:          _cfg.apiKey,
        session_token:    _cfg.sessionToken,
        event_type:       eventType,
        page_path:        pagePath,
        target:           target,
        idempotency_key:  ikey,
        client_ts:        clientTs,
        // KHÔNG gửi: form_data, input_values, field_content
      };

      fetch(_cfg.collectUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
        keepalive: true,
      }).catch(function (err) {
        console.warn('[WA] Gửi sự kiện thất bại:', err);
      });
    });
  };

  /** Tính SHA-256 của chuỗi; trả về hex string. */
  function _sha256Hex(str) {
    var buf = new TextEncoder().encode(str);
    return crypto.subtle.digest('SHA-256', buf).then(function (hash) {
      return Array.from(new Uint8Array(hash))
        .map(function (b) { return b.toString(16).padStart(2, '0'); })
        .join('');
    });
  }

  window.WA = WA;
}(window));
