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

        // Tự động làm mới định kỳ mỗi 15 giây nếu tab đang mở xem
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 15000);
    })();
</script>
</body>
</html>
