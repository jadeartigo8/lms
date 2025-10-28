<?php
session_start();
include('../connection/db.php');
include('includes/logger.php');
include('includes/exceptions.php');
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

// Session validation
try {
    if (empty($_SESSION['alogin'])) {
        throw new SessionException("User not logged in.");
    }
} catch (SessionException $e) {
    $_SESSION['error'] = $e->getMessage();
    $logger->write("Session error: " . $e->getMessage());
    header('Location: ../index.php');
    exit;
}

// Notepad-specific variables
$noteError = "";
$noteStatus = "";
$noteTitle = "Library Notepad";
$lastEdit = date('h:i A T, F d, Y'); // e.g., 01:37 PM PHT, October 27, 2025
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="Library Management System Notepad" />
    <meta name="author" content="" />
    <title><?php echo htmlspecialchars($noteTitle); ?> | Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Caveat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notepad.css">
    <link rel="stylesheet" href="../css/styles.css">

</head>
<body>
<?php include('includes/header.php'); ?>

<div class="notepad-container">
    <h1><?php echo htmlspecialchars($noteTitle); ?></h1>
    

    <div class="form-group full-width">
        <?php if (!empty($noteError)): ?>
            <div class="error"><?php echo htmlspecialchars($noteError); ?></div>
        <?php endif; ?>
        <?php if (!empty($noteStatus)): ?>
            <div class="success"><?php echo htmlspecialchars($noteStatus); ?></div>
        <?php endif; ?>
        
        <label for="noteArea">Note Content <span class="required">*</span></label>
        <div class="textarea-wrapper">
             <div class="notepad-actions">
                <button type="button" id="newBtn" class="">[New]</button>
                <button type="button" id="openBtn" class="">[Open]</button>
                <button type="button" id="saveBtn" class="">[Save]</button>
                <div id="watchFolder" class="watch-folder">File name: Not saved yet</div>
            </div>
            <textarea id="noteArea" name="noteArea" placeholder="Jot down your notes here... "></textarea>
           
        </div>
        <div class="timestamp">Edited @ <?php echo $lastEdit; ?></div>

        <div id="noteStatus"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const noteArea = document.getElementById('noteArea');
    const openBtn = document.getElementById('openBtn');
    const saveBtn = document.getElementById('saveBtn');
    const newBtn = document.getElementById('newBtn');
    const status = document.getElementById('noteStatus');
    const timestamp = document.querySelector('.timestamp');
    const watchFolder = document.getElementById('watchFolder');
    let fileHandle = null;
    let lastContent = noteArea.value;
    let typingTimer = null;

    // Update status display with persistent background
    function updateStatus(message, type = 'info') {
        status.textContent = message;
        status.className = `status ${type}`; // Apply the type class for background color
        if (!status.dataset.timeout) {
            status.dataset.timeout = setTimeout(() => {
                status.textContent = '';
                status.className = 'status'; // Reset to base class without removing background
                delete status.dataset.timeout;
            }, 5000);
        } else {
            clearTimeout(status.dataset.timeout);
            status.dataset.timeout = setTimeout(() => {
                status.textContent = '';
                status.className = 'status';
                delete status.dataset.timeout;
            }, 5000);
        }
    }

    // Debounce function to limit status updates
    function debounce(func, wait) {
        return function(...args) {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Update watch folder path with mock file system path
    function updateWatchFolder(fileName) {
        
        watchFolder.textContent = `File name: ${fileName ? `${fileName}` : 'Not saved yet'}`;
    }

    // Open file
    openBtn.addEventListener('click', async () => {
        try {
            [fileHandle] = await window.showOpenFilePicker({
                types: [{ description: 'Text Files', accept: { 'text/plain': ['.txt'] } }]
            });
            const file = await fileHandle.getFile();
            const content = await file.text();
            noteArea.value = content;
            lastContent = content;
            updateStatus(`Opened: ${file.name}`, 'success');
            updateWatchFolder(file.name);
            timestamp.textContent = `Edited @ ${new Date().toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZoneName: 'short' })}`;
        } catch (error) {
            if (error.name !== 'AbortError') {
                updateStatus(`Error opening file: ${error.message}`, 'error');
            }
        }
    });

    // Save file
    saveBtn.addEventListener('click', async () => {
        try {
            if (!fileHandle) {
                fileHandle = await window.showSaveFilePicker({
                    suggestedName: 'library-note.txt',
                    types: [{ description: 'Text Files', accept: { 'text/plain': ['.txt'] } }]
                });
            }
            const writable = await fileHandle.createWritable();
            await writable.write(noteArea.value);
            await writable.close();
            lastContent = noteArea.value;
            updateStatus('Saved successfully!', 'success');
            updateWatchFolder(fileHandle.name);
            timestamp.textContent = `Edited @ ${new Date().toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZoneName: 'short' })}`;
        } catch (error) {
            updateStatus(`Error saving: ${error.message}`, 'error');
        }
    });

    // New note
    newBtn.addEventListener('click', () => {
        if (noteArea.value && !confirm('Create a new note? Unsaved changes will be lost.')) {
            return;
        }
        noteArea.value = '';
        fileHandle = null;
        lastContent = '';
        updateStatus('New note created', 'info');
        updateWatchFolder(null);
        timestamp.textContent = `Edited @ ${new Date().toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZoneName: 'short' })}`;
        noteArea.style.height = 'auto';
    });

    // File monitoring and auto-resize with debounced status update
    noteArea.addEventListener('input', () => {
        noteArea.style.height = 'auto';
        noteArea.style.height = Math.max(250, noteArea.scrollHeight) + 'px';
        if (noteArea.value !== lastContent && lastContent !== '') {
            debounce(() => {
                updateStatus('Unsaved changes detected. Click Save to persist.', 'info');
            }, 2000)();
        }
        timestamp.textContent = `Edited @ ${new Date().toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZoneName: 'short' })}`;
        lastContent = noteArea.value;
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key.toLowerCase()) {
                case 'o':
                    e.preventDefault();
                    openBtn.click();
                    break;
                case 's':
                    e.preventDefault();
                    saveBtn.click();
                    break;
                case 'n':
                    e.preventDefault();
                    newBtn.click();
                    break;
            }
        }
    });
});
</script>

</body>
</html>