<?php

require('./include.php');

use Tencentyun\Auth;
use Tencentyun\Cosapi;

$bucketName = 'test_mikenwang_20150623';

//$srcPath = 'test.mp4';
//$dstPath = '/test.mp4';
//$srcPath = '/data/home/mikenwang/cos/sdk/6MB.at.test';
//$dstPath = '/6MB.at.test';
//$srcPath = '/data/home/mikenwang/cos/sdk/13MB.zip';
//$dstPath = '/13MB.zip';
$srcPath = '/data/home/mikenwang/cos/sdk/63MB_test.exe';
$dstPath = '/63MB_test.exe';
//$srcPath = '/data/home/mikenwang/cos/sdk/1GB_test.mkv';
//$dstPath = '/1GB_test.mkv';


// 上传文件
//$uploadRet = Cosapi::upload('test.mp4', $bucketName, '/test.mp4');
//$uploadRet = Cosapi::upload('/data/home/mikenwang/cos/sdk/63MB_test.exe', $bucketName, '/63MB_test.exe');
//var_dump($uploadRet);

//分片上传
//$sliceUploadRet = Cosapi::upload_slice(
//        $srcPath, $bucketName, $dstPath, null, 2000000, '48d44422-3188-4c6c-b122-6f780742f125+CpzDLtEHAA==');
//$sliceUploadRet = Cosapi::upload_slice(
//        $srcPath, $bucketName, $dstPath, null, 2000000);
$sliceUploadRet = Cosapi::upload_slice(
        $srcPath, $bucketName, $dstPath);
//var_dump($sliceUploadRet);

//创建目录
//$createFolderRet = Cosapi::createFolder($bucketName, '/test_create_folder/');
//var_dump($createFolderRet);

//list
//$listRet = Cosapi::listFiles($bucketName, '/test.mp4');
//var_dump($listRet);

//stat
//$statRet = Cosapi::stat($bucketName, '/test.mp4');
//var_dump($statRet);

//update
//$updateRet = Cosapi::update($bucketName, '/test.mp4', 'test_biz_attr');
//var_dump($updateRet);

//stat
//$statRet = Cosapi::stat($bucketName, '/test.mp4');
//var_dump($statRet);

//del
//$delRet = Cosapi::del($bucketName, '/test.mp4');
//var_dump($delRet);

//end of script
