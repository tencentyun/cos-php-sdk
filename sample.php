<?php

require('./include.php');

use Tencentyun\Auth;
use Tencentyun\Cosapi;

$bucketName = 'test_mikenwang_20150623';

$srcPath = '/data/home/mikenwang/cos/sdk/63MB_test.exe';
$dstPath = '/63MB_test.exe';


// 上传文件
$uploadRet = Cosapi::upload('test.mp4', $bucketName, '/test.mp4');
var_dump($uploadRet);

//分片上传
$sliceUploadRet = Cosapi::upload_slice(
        $srcPath, $bucketName, $dstPath);
//用户指定分片大小来分片上传
//$sliceUploadRet = Cosapi::upload_slice(
//        $srcPath, $bucketName, $dstPath, null, 2000000);
//指定了session，可以实现断点续传
//$sliceUploadRet = Cosapi::upload_slice(
//        $srcPath, $bucketName, $dstPath, null, 2000000, '48d44422-3188-4c6c-b122-6f780742f125+CpzDLtEHAA==');
//var_dump($sliceUploadRet);

//创建目录
$createFolderRet = Cosapi::createFolder($bucketName, '/test_create_folder/');
var_dump($createFolderRet);

//list
$listRet = Cosapi::listFiles($bucketName, '/test.mp4');
var_dump($listRet);

//update
$updateRet = Cosapi::update($bucketName, '/test.mp4', 'test_biz_attr');
var_dump($updateRet);

//stat
$statRet = Cosapi::stat($bucketName, '/test.mp4');
var_dump($statRet);

//del
$delRet = Cosapi::del($bucketName, '/test.mp4');
var_dump($delRet);

//end of script
