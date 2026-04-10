<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Aws\CloudFront\CloudFrontClient;
use ZipArchive;

class S3ToolController extends Controller {
    public function getPresignUrl(Request $request)
    {
        $request->validate(['file_name'=>'required|string', 'content_type'=>'required|string', 'upload_path'=>'nullable|string']);

        $folderPath =  trim($request->upload_path,'/ ');
        if(!empty($folderPath)){
            $path= $folderPath . '/' . $request->file_name;
        }
        else{
            $path = $request->file_name;
        }

        $s3 = Storage::disk('s3');
        $client = $s3->getClient();

        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType'=>$request->content_type,
        ]);

        $requestUrl = $client->createPresignedRequest($command, '+20 minutes');
        return response()->json([
            'url' =>(string) $requestUrl->getUri(),
            'path'=>'/' . $path
        ]);
    }

    public function invalidateCloudFront(Request $request)
    {
        $request->validate(['paths' => 'required|array']);

        $cloudFront = new \Aws\CloudFront\CloudFrontClient([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret')
            ]
        ]);

        $pathsArray = $request->input('paths');

        $encodedPaths = array_map(function($path) {
            return str_replace('%2F', '/', rawurlencode($path));
        }, $pathsArray);

        try {
            $result = $cloudFront->createInvalidation([
                'DistributionId' => env('CLOUDFRONT_DISTRIBUTION_ID'),
                'InvalidationBatch' => [
                    'CallerReference' => (string) time(),
                    'Paths' => [
                        'Items' => $encodedPaths,
                        'Quantity' => count($encodedPaths)
                    ],
                ]
            ]);

            return response()->json(['message' => 'CloudFront invalidated triggered', 'result' => $result]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi từ AWS CloudFront',
                'details' => $e->getMessage()
            ], 400);
        }
    }

    public function checkKeys(Request $request){
        $keys = $request->input('keys');
        $keyList = array_filter(explode("\n", str_replace("\r", '', $keys)));

        if (empty($keyList)) {
            return response()->json(['error' => 'Vui lòng nhập ít nhất 1 key'], 400);
        }

        $maxKeys = config('app.max_s3_keys');
        if (count($keyList) > $maxKeys) {
            return response()->json(['error' => "Số lượng key quá nhiều (" . count($keyList) . "). Tối đa cho phép: $maxKeys"], 400);
        }

        $foundFiles= [];
        foreach ($keyList as $key) {
            if (Storage::disk('s3')->exists(trim($key))) {
                $foundFiles[] = $key;
            }
        }
        return response()->json([
            'total_input' => count($keyList),
            'found_count' => count($foundFiles),
            'files' => $foundFiles
        ]);
    }

    public function downloadKeys(Request $request) {
        $keys = $request->input('keys');
        $keyList = array_filter(explode("\n", str_replace("\r", '', $keys)));

        if (empty($keyList)) {
            return response()->json(['error' => 'Danh sách key trống'], 400);
        }

        $zipFileName = 's3_download_' . time() . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($keyList as $key) {
                $trimmedKey = trim($key, " /");

                $parentPath = dirname($trimmedKey);
                $parentPath = ($parentPath === '.') ? '' : $parentPath . '/';

                $filesToDownload = Storage::disk('s3')->allFiles($trimmedKey);
                if (empty($filesToDownload)) {
                    if (Storage::disk('s3')->exists($trimmedKey)) {
                        $filesToDownload = [$trimmedKey];
                    }
                }

                foreach ($filesToDownload as $file) {
                    $content = Storage::disk('s3')->get($file);

                    if (!empty($parentPath) && strpos($file, $parentPath) === 0) {
                        $nameInZip = substr($file, strlen($parentPath));
                    } else {
                        $nameInZip = $file;
                    }

                    $zip->addFromString($nameInZip, $content);
                }
            }
            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
