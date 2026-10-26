/**
 * ====================================================================
 * JAVASCRIPT TRACKING SDK (sdk/tracker.js)
 * Tự động ghi nhận page_view, button_click, download_start, download_complete
 * Chống trùng ID khi retry, tạo session ẩn danh, bảo vệ quyền riêng tư
 * ====================================================================
 */

(function (window, document) {
    'use strict';

    // Cấu hình mặc định
    var config = {
        trackingKey: null,
        apiEndpoint: '/api/track.php',
        maxRetries: 3,
        retryDelayMs: 600,
        debug: false
    };

    var currentSessionId = null;

    /**
     * Tạo chuỗi UUID v4 chuẩn theo RFC 4122 cho event_id duy nhất
     */
    function generateUUID() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    /**
     * Quản lý Session ID ẩn danh trong sessionStorage (mỗi tab/phiên người dùng)
     * Không sử dụng Cookie vĩnh viễn, không dùng fingerprinting thiết bị.
     */
    function getOrCreateSessionId() {
        if (currentSessionId) {
            return currentSessionId;
        }

        var storageKey = 'wa_session_' + (config.trackingKey || 'default');
        try {
            var existing = window.sessionStorage.getItem(storageKey);
            if (existing && existing.length >= 16) {
                currentSessionId = existing;
                return existing;
            }
        } catch (e) {
            // Trường hợp sessionStorage bị vô hiệu hoá trên trình duyệt
        }

        currentSessionId = 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 9);
        try {
            window.sessionStorage.setItem(storageKey, currentSessionId);
        } catch (e) {}

        return currentSessionId;
    }

    /**
     * Lấy thời gian ISO hiện tại tại múi giờ Client
     */
    function getOccurredAt() {
        return new Date().toISOString();
    }

    /**
     * Lọc bỏ hoàn toàn các trường nhạy cảm phía client
     */
    function cleanProperties(props) {
        if (!props || typeof props !== 'object') {
            return {};
        }
        var clean = {};
        var forbiddenWords = ['pass', 'pwd', 'secret', 'token', 'card', 'cvv', 'phone', 'email', 'input'];

        for (var k in props) {
            if (Object.prototype.hasOwnProperty.call(props, k)) {
                var lk = k.toLowerCase();
                var forbidden = false;
                for (var i = 0; i < forbiddenWords.length; i++) {
                    if (lk.indexOf(forbiddenWords[i]) !== -1) {
                        forbidden = true;
                        break;
                    }
                }
                if (!forbidden && typeof props[k] !== 'function') {
                    // Cắt ngắn chuỗi an toàn
                    clean[k] = typeof props[k] === 'string' ? props[k].substring(0, 255) : props[k];
                }
            }
        }
        return clean;
    }

    /**
     * Gửi payload JSON tới PHP API có hỗ trợ retry và giữ nguyên event_id
     */
    function sendPayload(payload, retryCount) {
        retryCount = retryCount || 0;

        if (!config.trackingKey) {
            if (config.debug) console.warn('[WebTracker] Chưa cấu hình trackingKey.');
            return Promise.reject(new Error('Missing trackingKey'));
        }

        var jsonBody = JSON.stringify(payload);

        return fetch(config.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'X-Tracking-Key': config.trackingKey
            },
            body: jsonBody,
            keepalive: true
        })
        .then(function (response) {
            return response.json().then(function (data) {
                if (config.debug) {
                    console.log('[WebTracker Response]', response.status, data);
                }
                if (!response.ok && response.status !== 409 && response.status !== 200) {
                    // Nếu lỗi mạng/máy chủ 5xx và còn số lần retry
                    if (response.status >= 500 && retryCount < config.maxRetries) {
                        return retrySend(payload, retryCount + 1);
                    }
                }
                return data;
            });
        })
        .catch(function (err) {
            if (config.debug) {
                console.warn('[WebTracker Network Error]', err);
            }
            // Thử lại nếu lỗi mất mạng và còn lượt retry
            if (retryCount < config.maxRetries) {
                return retrySend(payload, retryCount + 1);
            }
            return Promise.reject(err);
        });
    }

    /**
     * Cơ chế Thử lại (Retry) với độ trễ tăng dần (Exponential backoff)
     * QUAN TRỌNG: Giữ nguyên event_id để API nhận diện đúng Idempotent Retry!
     */
    function retrySend(payload, nextRetryCount) {
        var delay = config.retryDelayMs * Math.pow(2, nextRetryCount - 1);
        if (config.debug) {
            console.log('[WebTracker] Đang thử lại lần ' + nextRetryCount + ' sau ' + delay + 'ms...');
        }

        return new Promise(function (resolve, reject) {
            setTimeout(function () {
                sendPayload(payload, nextRetryCount).then(resolve).catch(reject);
            }, delay);
        });
    }

    /**
     * Core Tracking function: Ghi nhận 1 sự kiện bất kỳ
     */
    function track(eventName, properties, customEventId) {
        var eventId = customEventId || generateUUID();
        var sessionId = getOrCreateSessionId();
        var pagePath = window.location.pathname || '/';

        var payload = {
            tracking_key: config.trackingKey,
            event_id: eventId,
            session_id: sessionId,
            event_name: eventName,
            page_path: pagePath,
            occurred_at: getOccurredAt(),
            properties: cleanProperties(properties)
        };

        if (config.debug) {
            console.log('[WebTracker Event]', eventName, payload);
        }

        return sendPayload(payload, 0);
    }

    /**
     * Ghi nhận lượt xem trang (page_view)
     */
    function trackPageView(additionalProps) {
        var props = {
            page_title: document.title || '',
            referrer: document.referrer ? document.referrer.substring(0, 150) : '',
            screen_width: window.screen ? window.screen.width : null,
            screen_height: window.screen ? window.screen.height : null
        };
        if (additionalProps) {
            for (var k in additionalProps) {
                props[k] = additionalProps[k];
            }
        }
        return track('page_view', props);
    }

    /**
     * Ghi nhận thao tác bấm nút (button_click)
     */
    function trackButtonClick(buttonElement, customProps) {
        var btn = buttonElement || {};
        var props = {
            element_id: btn.id || btn.getAttribute('data-track-id') || '',
            element_class: btn.className || '',
            button_text: (btn.innerText || btn.textContent || '').trim().substring(0, 50)
        };
        if (customProps) {
            for (var k in customProps) {
                props[k] = customProps[k];
            }
        }
        return track('button_click', props);
    }

    /**
     * Quản lý chuỗi tải tài liệu: download_start -> download_complete
     * Đảm bảo điều kiện hoàn tất thực sự thỏa mãn mới kích hoạt download_complete.
     */
    function trackDownload(fileUrl, fileName, onCompleteCallback) {
        var safeFileName = fileName || fileUrl.split('/').pop() || 'document.pdf';

        // 1. Kích hoạt sự kiện download_start
        var startPromise = track('download_start', {
            file_name: safeFileName,
            download_url: fileUrl.substring(0, 200)
        });

        // 2. Thực hiện tải thực tế qua Blob hoặc mô phỏng tiến trình tải tài liệu
        return fetch(fileUrl)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Download HTTP failed with status: ' + response.status);
                }
                return response.blob();
            })
            .then(function (blob) {
                // Tạo liên kết blob ảo để lưu file thực tế về máy người dùng
                var blobUrl = window.URL.createObjectURL(blob);
                var tempAnchor = document.createElement('a');
                tempAnchor.style.display = 'none';
                tempAnchor.href = blobUrl;
                tempAnchor.download = safeFileName;
                document.body.appendChild(tempAnchor);
                tempAnchor.click();

                setTimeout(function () {
                    document.body.removeChild(tempAnchor);
                    window.URL.revokeObjectURL(blobUrl);
                }, 1000);

                // 3. Kích hoạt sự kiện download_complete CHỈ KHI file đã được tải thành công 100%!
                return track('download_complete', {
                    file_name: safeFileName,
                    file_size_kb: Math.round(blob.size / 1024)
                }).then(function (completeRes) {
                    if (typeof onCompleteCallback === 'function') {
                        onCompleteCallback(null, completeRes);
                    }
                    return completeRes;
                });
            })
            .catch(function (error) {
                if (config.debug) {
                    console.error('[WebTracker Download Error]', error);
                }
                // Nếu tải file thất bại thì KHÔNG bắn download_complete!
                if (typeof onCompleteCallback === 'function') {
                    onCompleteCallback(error);
                }
                throw error;
            });
    }

    /**
     * Tự động quét và lắng nghe các phần tử có thuộc tính data-track-*
     */
    function attachAutoListeners() {
        document.addEventListener('click', function (e) {
            var target = e.target;
            // Tìm phần tử có data-track-click hoặc thẻ button/a
            var clickable = target.closest('[data-track-click], button, .btn-track');
            if (clickable) {
                var eventName = clickable.getAttribute('data-track-event') || 'button_click';
                var text = (clickable.innerText || clickable.textContent || '').trim().substring(0, 50);
                var elemId = clickable.id || clickable.getAttribute('data-track-id') || '';

                // Nếu là nút tải tài liệu có data-track-download
                var downloadUrl = clickable.getAttribute('data-track-download');
                if (downloadUrl) {
                    e.preventDefault();
                    trackDownload(downloadUrl, clickable.getAttribute('data-file-name'));
                    return;
                }

                track(eventName, {
                    element_id: elemId,
                    button_text: text,
                    element_class: clickable.className ? clickable.className.substring(0, 100) : ''
                });
            }
        }, false);
    }

    /**
     * Khởi tạo Tracker từ thẻ script hoặc gọi thủ công
     */
    function init(userConfig) {
        if (userConfig) {
            for (var k in userConfig) {
                if (Object.prototype.hasOwnProperty.call(userConfig, k)) {
                    config[k] = userConfig[k];
                }
            }
        }

        // Tự động tìm tracking key từ data-tracking-key trên thẻ <script>
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var s = scripts[i];
            if (s.src && s.src.indexOf('tracker.js') !== -1) {
                var keyAttr = s.getAttribute('data-tracking-key');
                if (keyAttr && !config.trackingKey) {
                    config.trackingKey = keyAttr;
                }
                var endpointAttr = s.getAttribute('data-api-endpoint');
                if (endpointAttr) {
                    config.apiEndpoint = endpointAttr;
                }
                break;
            }
        }

        getOrCreateSessionId();
        attachAutoListeners();

        // Tự động ghi nhận page_view khi trang đã sẵn sàng
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            trackPageView();
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                trackPageView();
            });
        }
    }

    // Xuất đối tượng WebTracker ra window toàn cục
    window.WebTracker = {
        init: init,
        track: track,
        trackPageView: trackPageView,
        trackButtonClick: trackButtonClick,
        trackDownload: trackDownload,
        getSessionId: getOrCreateSessionId,
        config: config
    };

    // Tự động khởi tạo nếu có thẻ script cấu hình sẵn
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            init();
        });
    }

})(window, document);
