</main>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <h5 class="text-muted">FriendFinder</h5>
                <p class="text-muted small"><?php echo __('connect_through_interests'); ?></p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <ul class="list-inline">
                    <li class="list-inline-item"><a href="contacts.php" class="text-muted"><?php echo __('contact'); ?></a></li>
                    <li class="list-inline-item">|</li>
                    <li class="list-inline-item"><a href="policy.php" class="text-muted"><?php echo __('privacy_policy'); ?></a></li>
                </ul>
                <p class="text-muted small mb-0">&copy; <?php echo date("Y"); ?> FriendFinder. <?php echo __('all_rights_reserved'); ?></p>
            </div>
        </div>
    </div>
</footer>

<style>
html, body {
    height: 100%;
}
body {
    display: flex;
    flex-direction: column;
}
main {
    flex: 1 0 auto;
}
.footer {
    flex-shrink: 0;
    background-color: #f8f9fa;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded and parsed');

    function refreshSession() {
        fetch('refresh_session.php', { 
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Session refreshed');
            } else {
                console.error('Failed to refresh session');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    setInterval(refreshSession, 2 * 60 * 1000);

    ['click', 'keypress', 'scroll', 'mousemove'].forEach(function(event) {
        document.addEventListener(event, function() {
            clearTimeout(window.refreshTimer);
            window.refreshTimer = setTimeout(refreshSession, 1000);
        });
    });
});

window.addEventListener('load', function() {
    var pageEndTime = performance.now();
    var pageLoadTime = pageEndTime - <?php echo $page_start_time * 1000; ?>;
    console.log('Total page load time:', pageLoadTime.toFixed(2), 'ms');
});
</script>
</body>
</html>