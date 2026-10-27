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
</script>
</body>
</html>
