<?php
// ====================================================================
// INCLUDES: FOOTER CHUNG & SCRIPTS (includes/footer.php)
// Hỗ trợ điều khiển Mobile Drawer và Toast Thông báo
// ====================================================================
?>
        </main>
    </div>
</div>

<div id="toast" class="toast-msg">
    <?= render_icon('check-circle', '', 16, '#10b981') ?>
    <span id="toast-text"></span>
</div>

<script>
    // Xử lý Drawer trên Mobile
    (function() {
        const toggleBtn = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (toggleBtn && sidebar && overlay) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    })();

    // Hàm hiển thị Toast thông báo hiện đại
    function showToast(msg, duration) {
        const t = document.getElementById('toast');
        const text = document.getElementById('toast-text');
        if (!t || !text) return;
        text.innerText = msg;
        t.style.display = 'inline-flex';
        setTimeout(function() {
            t.style.display = 'none';
        }, duration || 3000);
    }

    // Tự động đồng bộ số liệu mới nhất khi người dùng chuyển từ tab Demo Site về Dashboard
    (function() {
        const syncPages = ['dashboard.php', 'reports.php', 'funnels.php', 'event-logs.php'];
        const path = window.location.pathname;
        const isTargetPage = syncPages.some(function(p) { return path.indexOf(p) !== -1; });

        if (!isTargetPage) return;

        let blurredAt = 0;
        window.addEventListener('blur', function() {
            blurredAt = Date.now();
        });

        window.addEventListener('focus', function() {
            // Khi người dùng sang tab Demo Site tương tác trên 2 giây rồi quay lại Dashboard
            if (blurredAt > 0 && (Date.now() - blurredAt > 2000)) {
                blurredAt = 0;
                window.location.reload();
            }
        });

        // Tự động làm mới định kỳ mỗi 30 giây nếu tab đang mở xem và không tương tác
        setInterval(function() {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            const isEditing = activeTag === 'input' || activeTag === 'select' || activeTag === 'textarea';
            const popoverOpen = document.querySelector('.date-popover-menu.show');
            if (document.visibilityState === 'visible' && !isEditing && !popoverOpen) {
                window.location.reload();
            }
        }, 30000);
    })();

    // ====================================================================
    // BỘ ĐIỀU KHIỂN BỘ LỌC THỜI GIAN & WEBSITE (FILTER CONTROLLER)
    // ====================================================================
    (function() {
        const filterForm      = document.getElementById('global-filter-form');
        const siteSelect      = document.getElementById('filter-website-select');
        const presetSelect    = document.getElementById('filter-date-preset');
        const fromInput       = document.getElementById('filter-from-date');
        const toInput         = document.getElementById('filter-to-date');

        const popoverBtn      = document.getElementById('pill-date-range-btn');
        const popoverMenu     = document.getElementById('date-popover-menu');
        const popoverFrom     = document.getElementById('popover-from-date');
        const popoverTo       = document.getElementById('popover-to-date');
        const popoverApplyBtn = document.getElementById('btn-popover-apply');
        const presetButtons   = document.querySelectorAll('.popover-preset-btn');

        // Hàm tính chuỗi ngày YYYY-MM-DD
        function formatDate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function getDatesForPreset(preset) {
            const now = new Date();
            const todayStr = formatDate(now);

            if (preset === 'today') {
                return { from: todayStr, to: todayStr };
            }
            if (preset === 'yesterday') {
                const yest = new Date(now.getTime() - 86400000);
                const yestStr = formatDate(yest);
                return { from: yestStr, to: yestStr };
            }
            if (preset === '7_days') {
                const past = new Date(now.getTime() - 6 * 86400000);
                return { from: formatDate(past), to: todayStr };
            }
            if (preset === '14_days') {
                const past = new Date(now.getTime() - 13 * 86400000);
                return { from: formatDate(past), to: todayStr };
            }
            if (preset === '30_days') {
                const past = new Date(now.getTime() - 29 * 86400000);
                return { from: formatDate(past), to: todayStr };
            }
            if (preset === 'all') {
                return { from: '2026-01-01', to: todayStr };
            }
            return null;
        }

        // 1. Khi thay đổi Website -> Tự động submit
        if (siteSelect && filterForm) {
            siteSelect.addEventListener('change', function() {
                filterForm.submit();
            });
        }

        // 2. Khi thay đổi Khoảng ngày (Preset Select) trên thanh filter
        if (presetSelect && fromInput && toInput && filterForm) {
            presetSelect.addEventListener('change', function() {
                const val = this.value;
                if (val !== 'custom') {
                    const range = getDatesForPreset(val);
                    if (range) {
                        fromInput.value = range.from;
                        toInput.value = range.to;
                        filterForm.submit();
                    }
                }
            });

            fromInput.addEventListener('change', function() {
                presetSelect.value = 'custom';
            });
            toInput.addEventListener('change', function() {
                presetSelect.value = 'custom';
            });
        }

        // 3. Mở/Đóng Popover Menu ở góc trên bên phải
        if (popoverBtn && popoverMenu) {
            popoverBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                popoverMenu.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!popoverMenu.contains(e.target) && e.target !== popoverBtn && !popoverBtn.contains(e.target)) {
                    popoverMenu.classList.remove('show');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    popoverMenu.classList.remove('show');
                }
            });
        }

        // 4. Bấm các nút chọn nhanh trong Popover
        if (presetButtons.length > 0 && filterForm && fromInput && toInput) {
            presetButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const p = this.getAttribute('data-preset');
                    if (presetSelect) presetSelect.value = p;
                    const range = getDatesForPreset(p);
                    if (range) {
                        fromInput.value = range.from;
                        toInput.value = range.to;
                    }
                    if (popoverMenu) popoverMenu.classList.remove('show');
                    filterForm.submit();
                });
            });
        }

        // 5. Bấm nút "Áp Dụng Khoảng Ngày" trong Popover
        if (popoverApplyBtn && popoverFrom && popoverTo && filterForm && fromInput && toInput) {
            popoverApplyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (popoverFrom.value) fromInput.value = popoverFrom.value;
                if (popoverTo.value) toInput.value = popoverTo.value;
                if (presetSelect) presetSelect.value = 'custom';
                if (popoverMenu) popoverMenu.classList.remove('show');
                filterForm.submit();
            });
        }
    })();
</script>
</body>
</html>
