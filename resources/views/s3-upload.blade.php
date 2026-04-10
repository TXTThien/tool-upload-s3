<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Internal Tool</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom scrollbar for terminal */
        #log::-webkit-scrollbar { width: 8px; }
        #log::-webkit-scrollbar-track { background: #1a1a1a; }
        #log::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
        #log::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
</head>
<body class="min-h-screen p-8 text-gray-800 bg-gray-50">

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">S3 Upload Manager</h2>
            <p class="text-sm text-gray-500">Internal tool for managing static assets.</p>
        </div>
        <div class="px-3 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
            System Online
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">

        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl h-fit">

            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-700">S3 Prefix Path (Optional)</label>
                <input type="text" id="uploadPath" placeholder="e.g. assets/images/2026"
                       class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow">
                <p class="mt-1 text-xs text-gray-400">Leave empty to upload to root bucket.</p>
            </div>

            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-700">1. Select Individual Files</label>
                <input type="file" id="fileInput" multiple
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>

            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-700">2. OR Select Entire Folder</label>
                <input type="file" id="folderInput" webkitdirectory directory multiple
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer">
                <p class="mt-2 text-xs italic text-gray-500">System will merge both selections before uploading.</p>
            </div>

            <div id="progressContainer" class="hidden mb-6">
                <div class="flex justify-between mb-1 text-xs font-medium text-gray-700">
                    <span>Uploading...</span>
                    <span id="progressText">0%</span>
                </div>
                <div class="w-full h-2 bg-gray-200 rounded-full">
                    <div id="progressBar" class="h-2 bg-blue-600 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <button onclick="handleBulkUpload()" id="uploadBtn"
                    class="w-full px-4 py-2.5 font-medium text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex justify-center items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Start Upload to S3
            </button>
        </div>

        <div class="flex flex-col overflow-hidden bg-gray-900 border border-gray-800 shadow-xl rounded-xl">
            <div class="flex items-center px-4 py-2 bg-gray-800 border-b border-gray-700 gap-2">
                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <span class="ml-2 text-xs font-medium text-gray-400 font-mono">Process Terminal</span>
            </div>
            <div id="log" class="p-4 overflow-y-auto text-sm font-mono text-green-400 h-[400px]">
                <div class="text-gray-500">System ready. Waiting for input...</div>
            </div>
        </div>

    </div>
</div>
<script>
    function addLog(message) {
        const logBox = document.getElementById('log');
        logBox.innerHTML += `<div>${message}</div>`;
        logBox.scrollTop = logBox.scrollHeight;
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
