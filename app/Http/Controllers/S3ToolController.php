<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Aws\CloudFront\CloudFrontClient;
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
}
