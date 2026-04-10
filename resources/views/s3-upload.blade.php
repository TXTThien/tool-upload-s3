<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Internal Tool - Octokit</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #log::-webkit-scrollbar { width: 8px; }
        #log::-webkit-scrollbar-track { background: #1a1a1a; }
        #log::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
        #log::-webkit-scrollbar-thumb:hover { background: #555; }
        .tab-active { border-bottom: 2px solid #2563eb; color: #2563eb; }
    </style>
</head>
<body class="min-h-screen p-8 text-gray-800 bg-gray-50">

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">S3 Manager Professional</h2>
            <p class="text-sm text-gray-500">Internal tool for {{ auth()->user()->name ?? 'Developer' }}</p>
        </div>
        <div class="px-3 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full flex items-center gap-1">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> System Online
        </div>
    </div>

    <div class="flex border-b border-gray-200 mb-6">
        <button onclick="switchTab('upload')" id="tab-upload" class="px-6 py-2 font-medium text-gray-500 tab-active">Upload Assets</button>
        <button onclick="switchTab('download')" id="tab-download" class="px-6 py-2 font-medium text-gray-500">Download by Keys</button>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">

            <div id="section-upload" class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">1. Individual Files</label>
                        <input type="file" id="fileInput" multiple class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">2. Entire Folder</label>
                        <input type="file" id="folderInput" webkitdirectory directory multiple class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-purple-50 file:text-purple-700">
                    </div>
                </div>
                <div class="mb-6">
                    <label class="block mb-2 text-sm font-medium text-gray-700">S3 Prefix Path</label>
                    <input type="text" id="uploadPath" placeholder="e.g. assets/images/2026" class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button onclick="handleBulkUpload()" id="uploadBtn" class="w-full py-3 font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-all flex justify-center items-center gap-2">
                    START UPLOAD TO S3
                </button>
            </div>

            <div id="section-download" class="hidden p-6 bg-white border border-gray-200 shadow-sm rounded-xl">
                <label class="block mb-2 text-sm font-medium text-gray-700">Enter S3 Keys (One per line)</label>
                <textarea id="downloadKeys" rows="8" placeholder="assets/img1.png&#10;products/item2.jpg" class="w-full p-4 font-mono text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none transition-all"></textarea>

                <div id="checkResult" class="hidden my-4 p-4 rounded-lg bg-gray-50 border border-gray-200">
                    <div class="flex justify-between text-sm">
                        <span>Total Keys: <b id="totalInput">0</b></span>
                        <span>Found on S3: <b id="foundCount" class="text-green-600">0</b></span>
                    </div>
                </div>

                <div class="flex gap-4 mt-6">
                    <button onclick="handleCheckKeys()" id="checkBtn" class="flex-1 px-4 py-3 font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-all">
                        CHECK KEYS EXISTENCE
                    </button>
                    <button onclick="handleDownloadZip()" id="downloadBtn" disabled class="flex-1 px-4 py-3 font-bold text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all">
                        DOWNLOAD AS ZIP
                    </button>
                </div>
            </div>

        </div>

        <div class="lg:col-span-1 flex flex-col overflow-hidden bg-gray-900 border border-gray-800 shadow-xl rounded-xl h-[600px]">
            <div class="flex items-center px-4 py-2 bg-gray-800 border-b border-gray-700 gap-2">
                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <span class="ml-2 text-xs font-medium text-gray-400 font-mono">Process Terminal</span>
            </div>
            <div id="log" class="p-4 overflow-y-auto text-xs font-mono text-green-400 flex-1">
                <div class="text-gray-500">> System ready. Waiting for task...</div>
            </div>
        </div>
    </div>
</div>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function switchTab(type) {
        document.getElementById('section-upload').classList.toggle('hidden', type !== 'upload');
        document.getElementById('section-download').classList.toggle('hidden', type !== 'download');
        document.getElementById('tab-upload').classList.toggle('tab-active', type === 'upload');
        document.getElementById('tab-download').classList.toggle('tab-active', type === 'download');
        addLog(`> Switched to ${type.toUpperCase()} mode.`);
    }

    function addLog(message, color = 'green') {
        const logBox = document.getElementById('log');
        const colorClass = color === 'red' ? 'text-red-400' : (color === 'yellow' ? 'text-yellow-400' : 'text-green-400');
        logBox.innerHTML += `<div class="${colorClass}">[${new Date().toLocaleTimeString()}] ${message}</div>`;
        logBox.scrollTop = logBox.scrollHeight;
    }
    async function handleCheckKeys() {
        const keys = document.getElementById('downloadKeys').value;
        const btn = document.getElementById('checkBtn');

        if (!keys.trim()) return alert("Nhập key đi bồ!");

        btn.disabled = true;
        addLog("> Validating keys on S3...", 'yellow');

        try {
            const res = await fetch('/api/s3/check-keys', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ keys })
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.error);

            document.getElementById('checkResult').classList.remove('hidden');
            document.getElementById('totalInput').innerText = data.total_input;
            document.getElementById('foundCount').innerText = data.found_count;

            addLog(`> Check xong: Tìm thấy ${data.found_count}/${data.total_input} file.`);

            if (data.found_count > 0) {
                document.getElementById('downloadBtn').disabled = false;
            }
        } catch (e) {
            addLog(`> Lỗi: ${e.message}`, 'red');
        } finally {
            btn.disabled = false;
        }
    }

    async function handleDownloadZip() {
        const keys = document.getElementById('downloadKeys').value;
        addLog("> Preparing ZIP archive. Please wait...", 'yellow');

        try {
            const response = await fetch('/api/s3/download-zip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ keys })
            });

            if (!response.ok) throw new Error("Download failed");

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `S3_Backup_${Date.now()}.zip`;
            document.body.appendChild(a);
            a.click();
            addLog("> Download started successfully!", 'green');
        } catch (e) {
            addLog(`> ZIP Error: ${e.message}`, 'red');
        }
    }
    async function handleBulkUpload() {
        const fileInput = document.getElementById('fileInput');
        const folderInput = document.getElementById('folderInput');
        const pathInput = document.getElementById('uploadPath').value;
        const btn = document.getElementById('uploadBtn');

        const individualFiles = Array.from(fileInput.files);
        const folderFiles = Array.from(folderInput.files);
        const allFiles = [...individualFiles, ...folderFiles];

        if (allFiles.length === 0) {
            alert("Vui lòng chọn ít nhất một file hoặc folder!");
            return;
        }

        btn.disabled = true;
        btn.innerText = "Đang Upload...";
        document.getElementById('log').innerHTML = "";

        const uploadedS3Paths = [];

        const invalidationSet = new Set();

        const cleanBase = pathInput.trim().replace(/^\/+|\/+$/g, '');

        addLog(`Bắt đầu xử lý tổng cộng ${allFiles.length} file(s)...`);

        for (let i = 0; i < allFiles.length; i++) {
            const file = allFiles[i];
            const relativePath = file.webkitRelativePath || file.name;

            addLog(`Đang up (${i+1}/${allFiles.length}): ${relativePath}...`);

            const s3Path = await uploadSingleFile(file, relativePath, pathInput);

            if (s3Path) {
                uploadedS3Paths.push(s3Path);

                if (relativePath.includes('/')) {
                    const topFolder = relativePath.split('/')[0];

                    const folderInvalidation = cleanBase ? `/${cleanBase}/${topFolder}/*` : `/${topFolder}/*`;
                    invalidationSet.add(folderInvalidation);
                } else {
                    invalidationSet.add(s3Path);
                }
            }
        }

        if (uploadedS3Paths.length > 0) {
            const pathsToInvalidate = Array.from(invalidationSet);

            addLog(`Đã upload thành công ${uploadedS3Paths.length} file lên S3.`);
            addLog(`Đang gửi yêu cầu xoá cache cho: <span style="color:cyan">${pathsToInvalidate.join(', ')}</span>`);

            await invalidateCloudFront(pathsToInvalidate);
        } else {
            addLog(`Upload thất bại toàn bộ!`);
            alert("Đã xảy ra lỗi trong quá trình upload.");
        }

        btn.disabled = false;
        btn.innerText = "Upload Lên S3";
        fileInput.value = "";
        folderInput.value = "";
    }

    async function uploadSingleFile(file, relativeFileName, baseUploadPath) {
        const fileName = encodeURIComponent(relativeFileName);
        const contentType = encodeURIComponent(file.type || 'application/octet-stream');
        const pathParam = encodeURIComponent(baseUploadPath);

        try {
            const urlRes = await fetch(`/s3/presigned-url?file_name=${fileName}&content_type=${contentType}&upload_path=${pathParam}`);
            if (!urlRes.ok) throw new Error("Không lấy được Presigned URL");

            const { url, path } = await urlRes.json();

            const uploadRes = await fetch(url, {
                method: 'PUT',
                body: file,
                headers: { 'Content-Type': file.type || 'application/octet-stream' }
            });

            if (!uploadRes.ok) throw new Error("Upload lên AWS S3 thất bại");

            return path;
        } catch (error) {
            console.error(error);
            addLog(`<span style="color:red">Lỗi ở file ${relativeFileName}: ${error.message}</span>`);
            return null;
        }
    }

    async function invalidateCloudFront(pathsArray) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch(`/cloudfront/invalidate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ paths: pathsArray })
            });

            if (res.ok) {
                addLog(`<span style="color:yellow">=> Xoá cache CloudFront thành công!</span>`);
            } else {
                const errorData = await res.json();
                throw new Error(errorData.details || "API Invalidate trả về lỗi");
            }
        } catch (error) {
            console.error("Lỗi Invalidate:", error);
            addLog(`<span style="color:red">Lỗi khi gọi Invalidate: ${error.message}</span>`);
        }
    }
</script>
</body>
</html>
