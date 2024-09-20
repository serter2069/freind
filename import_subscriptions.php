<?php
session_start();
require_once 'db_connection.php';
require_once 'translations.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

function getImportStatus($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT import_status, import_progress, total_subscriptions FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        return [
            'status' => $row['import_status'],
            'imported' => $row['import_progress'],
            'total' => $row['total_subscriptions'],
            'progress' => $row['total_subscriptions'] > 0 ? round(($row['import_progress'] / $row['total_subscriptions']) * 100, 2) : 0
        ];
    }
    
    return [
        'status' => 'not_started',
        'imported' => 0,
        'total' => 0,
        'progress' => 0
    ];
}

$import_status = getImportStatus($user_id);

$page_title = __('import_subscriptions');
include 'header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg mt-5">
                <div class="card-body text-center">
                    <h1 class="card-title mb-4"><?php echo __('import_subscriptions'); ?></h1>
                    
                    <div id="importStatus">
                        <?php if ($import_status['status'] === 'not_started' || $import_status['status'] === 'error'): ?>
                            <p class="lead"><?php echo __('start_import_explanation'); ?></p>
                            <button id="startImport" class="btn btn-primary btn-lg"><?php echo __('start_import'); ?></button>
                        <?php elseif ($import_status['status'] === 'in_progress'): ?>
                            <h3 class="mb-4"><?php echo __('import_progress'); ?></h3>
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden"><?php echo __('loading'); ?></span>
                            </div>
                            <p id="importCount" class="lead mt-3">
                                <?php echo __('imported'); ?>: <span id="importedCount"><?php echo $import_status['imported']; ?></span>
                                / <span id="totalCount"><?php echo $import_status['total']; ?></span>
                            </p>
                            <?php if ($import_status['imported'] >= 10): ?>
                                <div id="enoughImportedAlert" class="alert alert-success mt-3">
                                    <p><?php echo __('enough_subscriptions_imported'); ?></p>
                                    <a href="my_subscriptions.php" class="btn btn-primary"><?php echo __('manage_subscriptions'); ?></a>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($import_status['status'] === 'completed'): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading"><?php echo __('import_completed'); ?></h4>
                                <p class="mb-0"><?php echo __('import_success_message'); ?></p>
                            </div>
                            <a href="my_subscriptions.php" class="btn btn-primary btn-lg mt-3"><?php echo __('view_subscriptions'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startImportBtn = document.getElementById('startImport');
    if (startImportBtn) {
        startImportBtn.addEventListener('click', startImport);
    }

    let importInProgress = <?php echo $import_status['status'] === 'in_progress' ? 'true' : 'false'; ?>;

    function startImport() {
        if (importInProgress) {
            console.log('Импорт уже выполняется');
            return;
        }

        startImportBtn.disabled = true;
        importInProgress = true;

        fetch('start_import.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            console.log('Ответ от start_import.php:', data);
            if (data.status === 'started') {
                updateUIForImportInProgress(data);
                checkProgress();
            } else {
                throw new Error('Unexpected response status: ' + data.status);
            }
        })
        .catch(error => {
            console.error('Ошибка при запуске импорта:', error);
            importInProgress = false;
            startImportBtn.disabled = false;
            displayErrorMessage('<?php echo __('import_start_error'); ?>: ' + error.message);
        });
    }

    function displayErrorMessage(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.textContent = message;
        document.getElementById('importStatus').appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    function updateUIForImportInProgress(data) {
        document.getElementById('importStatus').innerHTML = `
            <h3 class="mb-4"><?php echo __('import_progress'); ?></h3>
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden"><?php echo __('loading'); ?></span>
            </div>
            <p id="importCount" class="lead mt-3">
                <?php echo __('imported'); ?>: <span id="importedCount">0</span>
                / <span id="totalCount">${data.total}</span>
            </p>
        `;
    }

    function updateProgress(data) {
        document.getElementById('importedCount').textContent = data.imported;
        document.getElementById('totalCount').textContent = data.total;

        if (data.imported >= 10 && !document.getElementById('enoughImportedAlert')) {
            const alertDiv = document.createElement('div');
            alertDiv.id = 'enoughImportedAlert';
            alertDiv.className = 'alert alert-success mt-3';
            alertDiv.innerHTML = `
                <p><?php echo __('enough_subscriptions_imported'); ?></p>
                <a href="my_subscriptions.php" class="btn btn-primary"><?php echo __('manage_subscriptions'); ?></a>
            `;
            document.getElementById('importStatus').appendChild(alertDiv);
        }
    }

    function checkProgress() {
        fetch('check_import_progress.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            console.log('Данные о прогрессе импорта:', data);
            
            updateProgress(data);
            
            if (data.status === 'completed') {
                console.log('Импорт завершен');
                importInProgress = false;
                location.reload();
            } else if (data.status === 'in_progress') {
                console.log('Импорт все еще выполняется');
                setTimeout(checkProgress, 3000);
            } else if (data.status === 'error') {
                throw new Error('Import error: ' + (data.message || 'Unknown error'));
            } else {
                throw new Error('Unexpected import status: ' + data.status);
            }
        })
        .catch(error => {
            console.error('Ошибка при проверке прогресса:', error);
            importInProgress = false;
            displayErrorMessage('<?php echo __('import_error'); ?>: ' + error.message);
            setTimeout(checkProgress, 5000);
        });
    }

    if (importInProgress) {
        console.log('Импорт уже выполняется, проверяем прогресс');
        checkProgress();
    }
});
</script>

<?php include 'footer.php'; ?>