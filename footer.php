</div> <!-- Закрытие .content-wrapper -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <p class="text-muted">&copy; <?php echo date("Y"); ?> Energy Diary. <?php echo __('all_rights_reserved'); ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded and parsed');

    // Функция обновления сессии
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

    // Обновление сессии каждые 2 минуты
    setInterval(refreshSession, 2 * 60 * 1000);

    // Обновление сессии при активности пользователя
    ['click', 'keypress', 'scroll', 'mousemove'].forEach(function(event) {
        document.addEventListener(event, function() {
            clearTimeout(window.refreshTimer);
            window.refreshTimer = setTimeout(refreshSession, 1000);
        });
    });
});

// Отладочная информация о времени выполнения страницы
window.addEventListener('load', function() {
    var pageEndTime = performance.now();
    var pageLoadTime = pageEndTime - <?php echo $page_start_time * 1000; ?>;
    console.log('Total page load time:', pageLoadTime.toFixed(2), 'ms');
});
</script>

</body>
</html>
<?php
$page_end_time = microtime(true);
$page_execution_time = $page_end_time - $GLOBALS['page_start_time'];
error_log("Page execution time: " . $page_execution_time . " seconds");
?>