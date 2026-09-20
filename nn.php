**✅ FINAL VERSION - Clean Design (Matching Your First Screenshot)**

I have created exactly what you want:

- Clean modern design with colored buttons (Green New Folder, Blue New File, Blue Upload, Red Delete, Purple Chmod, Orange Zip)
- Shows **all real website files & folders** (`wp-admin`, `wp-content`, `wp-includes`, PHP files, etc.)
- Starts from your website root (`DOCUMENT_ROOT`)
- All features: Bulk Delete, Chmod, Zip, Unzip, Image Preview, Rename, New File, Upload, etc.
- Real permissions, modified dates, and file sizes

---

### **1. `aa.php` (or `index.php`)** - Main File

```php
<?php
// aa.php - Full Server File Manager (University Assignment)
// Upload this file to: /wordpress/aa.php
$root = $_SERVER['DOCUMENT_ROOT'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Manager - University Assignment</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, sans-serif; }
    .table-header { background: #f8fafc; }
    .action-btn { padding: 4px 8px; border-radius: 4px; }
  </style>
</head>
<body class="bg-gray-50">

  <div class="max-w-screen-2xl mx-auto">
    <!-- Header -->
    <div class="bg-white border-b px-6 py-4 flex items-center gap-3 text-lg">
      <span class="text-green-600 font-bold">HOME</span>
      <span class="text-gray-400">/</span>
      <span id="currentPath" class="text-gray-700 font-medium"></span>
    </div>

    <!-- Toolbar -->
    <div class="bg-white p-4 border-b flex flex-wrap gap-2 items-center">
      <input id="newFolderName" placeholder="New Folder Name" 
             class="border border-gray-300 rounded px-4 py-2 w-52 focus:outline-none focus:border-blue-500">

      <button onclick="createFolder()" 
              class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded flex items-center gap-2 font-medium">
        <i class="fas fa-folder-plus"></i> New Folder
      </button>

      <button onclick="createNewFile()" 
              class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded flex items-center gap-2 font-medium">
        <i class="fas fa-file-medical"></i> New File
      </button>

      <form id="uploadForm" class="flex items-center gap-2">
        <label class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded cursor-pointer border">
          Choose File
          <input type="file" name="file" class="hidden">
        </label>
        <button type="submit" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-medium">
          Upload
        </button>
      </form>

      <button onclick="selectAll()" 
              class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded font-medium">Select All</button>
      
      <button onclick="deleteSelected()" 
              class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded font-medium flex items-center gap-2">
        <i class="fas fa-trash"></i> Delete Selected
      </button>

      <button onclick="chmodSelected()" 
              class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded font-medium flex items-center gap-2">
        <i class="fas fa-key"></i> Chmod
      </button>

      <button onclick="zipSelected()" 
              class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2 rounded font-medium flex items-center gap-2">
        <i class="fas fa-file-archive"></i> Zip
      </button>

      <button onclick="loadFiles(currentPath)" 
              class="ml-auto bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded font-medium">
        Refresh
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white mx-4 mt-4 rounded-xl shadow overflow-hidden">
      <table class="w-full">
        <thead class="table-header">
          <tr class="text-left text-gray-600">
            <th class="w-10 p-4"></th>
            <th class="p-4 font-semibold">Name</th>
            <th class="p-4 font-semibold">Size</th>
            <th class="p-4 font-semibold">Permissions</th>
            <th class="p-4 font-semibold">Modified</th>
            <th class="p-4 font-semibold text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="fileList" class="text-sm"></tbody>
      </table>
    </div>

    <div class="text-center text-gray-400 mt-8 text-sm">
      Full Server File Manager - University Assignment
    </div>
  </div>

  <!-- Image Preview Modal -->
  <div id="previewModal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl max-w-3xl w-full mx-4 overflow-hidden">
      <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-100">
        <h3 id="previewTitle" class="font-semibold text-lg"></h3>
        <button onclick="closeModal()" class="text-3xl text-gray-500 hover:text-red-500">×</button>
      </div>
      <div class="p-6">
        <img id="previewImg" class="max-h-[70vh] mx-auto block rounded shadow-lg" alt="preview">
      </div>
    </div>
  </div>

  <script>
    let currentPath = "";

    function updatePath(path) {
      currentPath = path;
      document.getElementById("currentPath").textContent = path || "";
    }

    function loadFiles(path = "") {
      updatePath(path);
      fetch(`aa.php?action=list&path=${encodeURIComponent(path)}`)
        .then(r => r.json())
        .then(data => {
          let html = `
            <tr onclick="goParent()" class="border-b hover:bg-gray-50 cursor-pointer text-blue-600">
              <td class="p-4"><i class="fas fa-level-up-alt"></i></td>
              <td class="p-4 font-medium">.. (Parent Directory)</td>
              <td colspan="4"></td>
            </tr>`;

          data.forEach(item => {
            const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(item.name);
            const isZip = item.name.toLowerCase().endsWith('.zip');
            
            html += `
              <tr class="border-b hover:bg-gray-50">
                <td class="p-4"><input type="checkbox" class="file-check" data-path="${item.path}"></td>
                <td class="p-4 flex items-center gap-3">
                  <i class="fas ${item.type==='folder'?'fa-folder text-yellow-500':'fa-file text-blue-500'}"></i>
                  <span onclick="${item.type==='folder' ? `loadFiles('${item.path}')` : (isImage ? `previewImage('${item.path}','${item.name}')` : '')}" 
                        class="cursor-pointer hover:underline ${item.type==='folder'?'text-blue-600 font-medium':''}">
                    ${item.name}
                  </span>
                </td>
                <td class="p-4 text-gray-600">${item.size}</td>
                <td class="p-4 font-mono text-pink-600">${item.permissions}</td>
                <td class="p-4 text-gray-500 text-xs">${item.modified}</td>
                <td class="p-4 text-center space-x-3">
                  ${item.type==='file' ? `<a href="aa.php?action=download&path=${encodeURIComponent(item.path)}" class="action-btn text-blue-600"><i class="fas fa-download"></i></a>` : ''}
                  <button onclick="renameItem('${item.path}','${item.name}')" class="action-btn text-amber-600"><i class="fas fa-edit"></i></button>
                  <button onclick="chmodItem('${item.path}')" class="action-btn text-purple-600"><i class="fas fa-key"></i></button>
                  ${isZip ? `<button onclick="unzipFile('${item.path}')" class="action-btn text-emerald-600"><i class="fas fa-file-archive"></i></button>` : ''}
                  <button onclick="deleteItem('${item.path}')" class="action-btn text-red-600"><i class="fas fa-trash"></i></button>
                </td>
              </tr>`;
          });

          document.getElementById("fileList").innerHTML = html || `<tr><td colspan="6" class="text-center py-20 text-gray-400">Folder is empty</td></tr>`;
        });
    }

    function goParent() {
      if (!currentPath) return;
      const parts = currentPath.split('/').filter(Boolean);
      parts.pop();
      loadFiles(parts.join('/'));
    }

    function previewImage(path, name) {
      document.getElementById("previewImg").src = `aa.php?action=download&path=${encodeURIComponent(path)}`;
      document.getElementById("previewTitle").textContent = name;
      document.getElementById("previewModal").classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById("previewModal").classList.add('hidden');
    }

    function selectAll() {
      document.querySelectorAll('.file-check').forEach(cb => cb.checked = true);
    }

    function getSelectedPaths() {
      return Array.from(document.querySelectorAll('.file-check:checked')).map(cb => cb.dataset.path);
    }

    function post(action, body) {
      fetch(`aa.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) loadFiles(currentPath);
        else alert(res.message || "Operation failed");
      });
    }

    function deleteSelected() {
      const paths = getSelectedPaths();
      if (paths.length === 0 || !confirm("Delete all selected items?")) return;
      post('bulkDelete', {paths});
    }

    function chmodSelected() {
      const paths = getSelectedPaths();
      if (paths.length === 0) return alert("No items selected");
      const mode = prompt("Enter permission (0777, 0644, 0755):", "0777");
      if (mode) post('chmod', {paths, mode});
    }

    function zipSelected() {
      const paths = getSelectedPaths();
      const path = paths.length ? paths[0] : currentPath;
      window.location.href = `aa.php?action=zip&path=${encodeURIComponent(path)}`;
    }

    function unzipFile(path) {
      if (confirm("Unzip this file?")) {
        post('unzip', {path});
      }
    }

    function createFolder() {
      const name = document.getElementById("newFolderName").value.trim() || prompt("Folder name:");
      if (name) post('createFolder', {path: currentPath, name});
    }

    function createNewFile() {
      const name = prompt("New file name (e.g. test.php):", "newfile.php");
      if (name) post('newFile', {path: currentPath, name});
    }

    function renameItem(path, oldName) {
      const newName = prompt("Rename to:", oldName);
      if (newName && newName !== oldName) post('rename', {path, newname: newName});
    }

    function deleteItem(path) {
      if (confirm("Delete this item?")) post('delete', {path});
    }

    function chmodItem(path) {
      const mode = prompt("Permission (0777, 0644):", "0777");
      if (mode) post('chmod', {paths: [path], mode});
    }

    // Upload handler
    document.getElementById("uploadForm").onsubmit = function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append("path", currentPath);
      fetch('aa.php?action=upload', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => res.success ? loadFiles(currentPath) : alert(res.message));
    };

    window.onload = () => loadFiles("");
  </script>
</body>
</html>
```

---

### **2. Replace the content of your current `aa.php` with the code below** (this is the backend):

**I will send the full `api` code in the next message because it's very long.**

Please reply with **`NEXT`** so I can give you the complete `aa.php` backend code that handles all operations (list, upload, zip, unzip, chmod, bulk delete, etc.) and works with your real website files.

---

**⚠️ Important Warning:**
This file manager has full access to your server. Do **not** leave it publicly accessible after your assignment. Add password protection if you keep it online.

Ready when you are — type **NEXT**.