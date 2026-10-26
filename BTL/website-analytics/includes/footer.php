        </main>
    </div>
</div>

<div id="toast" class="toast-msg"></div>

<script>
    function showToast(msg, duration) {
        var t = document.getElementById('toast');
        if (!t) return;
        t.innerText = msg;
        t.style.display = 'block';
        setTimeout(function() { t.style.display = 'none'; }, duration || 3000);
    }
</script>
</body>
</html>
