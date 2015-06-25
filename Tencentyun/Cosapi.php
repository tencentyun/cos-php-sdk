<?php

namespace Tencentyun;

class Cosapi
{
    // 30 days
    const EXPIRED_SECONDS = 2592000;

    //3M
    const DEFAULT_SLICE_SIZE = 3145728;

    //10M
    const MIN_SLICE_FILE_SIZE = 10485760;

    const COSAPI_FILE_NOT_EXISTS = -1;
    const COSAPI_NETWORK_ERROR = -2;
    const COSAPI_PARAMS_ERROR = -3;
    const COSAPI_ILLEGAL_SLICE_SIZE_ERROR = -3;
    
    public static function generateResUrl($bucketName, $dstPath) {
        return Conf::API_COSAPI_END_POINT . Conf::APPID . '/' . $bucketName . $dstPath;
    }
        
    public static function sendRequest($req) {
        //var_dump($req);

        $rsp = Http::send($req);
        $info = Http::info();
        $ret = json_decode($rsp, true);

        if ($ret) {
            if (0 === $ret['code']) {
                $ret['httpcode'] = $info['http_code'];
                return $ret;
            } else {
                return array(
                    'httpcode' => $info['http_code'], 
                    'code' => $ret['code'], 
                    'message' => $ret['message'], 
                    'data' => array()
                );
            }
        } else {
            return array(
                    'httpcode' => $info['http_code'], 
                    'code' => self::COSAPI_NETWORK_ERROR, 
                    'message' => 'network error', 'data' => array()
                );
        }
    }

    /**
     * 上传文件
     * @param  string  $srcPath     本地文件路径
     * @param  string  $bucketName  上传的bcuket名称
     * @param  string  $dstPath     上传的文件路径
     * @return [type]                [description]
     */
    public static function upload($srcPath, $bucketName, $dstPath, $bizAttr = null) {

        $srcPath = realpath($srcPath);

        if (!file_exists($srcPath)) {
            return array(
                    'httpcode' => 0, 
                    'code' => self::COSAPI_FILE_NOT_EXISTS, 
                    'message' => 'file '.$srcPath.' not exists', 
                    'data' => array());
        }

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $dstPath);
        $sign = Auth::appSign_more($expired, $bucketName);
        $sha1 = hash_file('sha1', $srcPath);

        $data = array(
            'op' => 'upload',
            'sha' => $sha1,
            'biz_attr' => (isset($bizAttr) ? $bizAttr : ''),
            'filecontent' => '@'.$srcPath,
        );

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
            ),
        );

        return self::sendRequest($req);
    }

    /**
     * 上传文件
     * @param  string  $srcPath     本地文件路径
     * @param  string  $bucketName  上传的bcuket名称
     * @param  string  $dstPath     上传的文件路径
     * @return [type]                [description]
     */
    public static function upload_slice(
            $srcPath, $bucketName, $dstPath, 
            $bizAttr = null, 
            $sliceSize = 0, $session = null) {

        $srcPath = realpath($srcPath);

        if (!file_exists($srcPath)) {
            return array(
                    'httpcode' => 0, 
                    'code' => self::COSAPI_FILE_NOT_EXISTS, 
                    'message' => 'file '.$srcPath.' not exists', 
                    'data' => array());
        }

        $fileSize = filesize($srcPath);
        /*
        if ($fileSize < self::MIN_SLICE_FILE_SIZE) {
            return self::upload(
                    $srcPath, $bucketName, $dstPath,
                    $bizAttr);
        }
        */

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $dstPath);
        $sign = Auth::appSign_more($expired, $bucketName);
        $sha1 = hash_file('sha1', $srcPath);

        $ret = self::upload_prepare(
                $fileSize, $sha1, $sliceSize, 
                $sign, $url, $bizAttr, $session);
        if($ret['httpcode'] != 200
                || $ret['code'] != 0) {
            return $ret;
        }

        if(isset($ret['data']) 
                && isset($ret['data']['url'])) {
        //秒传命中，直接返回了url
            return $ret;
        }

        $sliceSize = $ret['data']['slice_size'];
        if ($sliceSize > self::DEFAULT_SLICE_SIZE ||
            $sliceSize <= 0) {
            $ret['code'] = self::COSAPI_ILLEGAL_SLICE_SIZE_ERROR;
            $ret['message'] = 'illegal slice size';
            return $ret;
        }

        $session = $ret['data']['session'];
        $offset = $ret['data']['offset'];

        $ret = self::upload_data(
                $fileSize, $sha1, $sliceSize,
                $sign, $url, $srcPath,
                $offset, $session);
        return $ret;
    }

    private static function upload_prepare(
            $fileSize, $sha1, $sliceSize,
            $sign, $url, $bizAttr, $session = null) {
        /*
        if (file_exists($sha1)) {
            $lastSession = file_get_contents($sha1);
            $lastSession = json_decode($lastSession);
            if (is_array($lastSession)
                 && isset($lastSession['session'])
                 && is_string($lastSession['session'])) {
                $session = $lastSession['session'];
            }
        }
        */

        $data = array(
            'op' => 'upload_slice',
            'filesize' => $fileSize,
            'sha' => $sha1,
        );
        isset($bizAttr) && 
            $data['biz_attr'] = $bizAttr;
        isset($session) &&
            $data['session'] = $session;

        if ($sliceSize > 0) {
            if ($sliceSize <= self::DEFAULT_SLICE_SIZE) {
                $data['slice_size'] = $sliceSize;
            } else {
                $data['slice_size'] = self::DEFAULT_SLICE_SIZE;
            }
        }

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
            ),
        );

        $ret = self::sendRequest($req);
        return $ret;
    
    }

    private static function upload_data(
            $fileSize, $sha1, $sliceSize,
            $sign, $url, $srcPath, 
            $offset, $session) {
    
        //$handle = fopen($srcPath, "rb");
        while ($fileSize > $offset) {
            $filecontent = file_get_contents(
                    $srcPath, false, null,
                    $offset, $sliceSize);
            //$filecontent = fread($handle, $sliceSize);

            if ($filecontent === false) {
                return $ret;
            }

            /*
            $data = array(
                'op' => 'upload_slice',
                'sha' => $sha1,
                'session' => $session,
                'offset' => $offset,
                'filecontent' => $filecontent,
            );
            */

            $boundary = '---------------------------' . substr(md5(mt_rand()), 0, 10); 
            $data = self::generateSliceBody(
                    $filecontent, $offset, $sha1,
                    $session, basename($srcPath), $boundary);

            $req = array(
                'url' => $url,
                'method' => 'post',
                'timeout' => 120,
                'data' => $data,
                'header' => array(
                    'Authorization:'.$sign,
                    'Content-Type: multipart/form-data; boundary=' . $boundary,
                ),
            );

            $ret = self::sendRequest($req);

            if($ret['httpcode'] != 200 
                    || $ret['code'] != 0) {
                return $ret;
            }

            $session = $ret['data']['session'];
            $offset += $sliceSize;
        
        }

        return $ret;
    }


    private static function generateSliceBody(
            $fileContent, $offset, $sha, 
            $session, $fileName, $boundary) {
        $formdata = '';

        $formdata .= '--' . $boundary . "\r\n";
        $formdata .= "content-disposition: form-data; name=\"op\"\r\n\r\nupload_slice\r\n";

        $formdata .= '--' . $boundary . "\r\n";
        $formdata .= "content-disposition: form-data; name=\"offset\"\r\n\r\n" . $offset. "\r\n";

        //$formdata .= '--' . $boundary . "\r\n";
        //$formdata .= "content-disposition: form-data; name=\"sha\"\r\n\r\n" . $sha . "\r\n";

        $formdata .= '--' . $boundary . "\r\n";
        $formdata .= "content-disposition: form-data; name=\"session\"\r\n\r\n" . $session . "\r\n";

        $formdata .= '--' . $boundary . "\r\n";
        $formdata .= "content-disposition: form-data; name=\"fileContent\"; filename=\"" . $fileName . "\"\r\n"; 
        $formdata .= "content-type: application/octet-stream\r\n\r\n";

        $data = $formdata . $fileContent . "\r\n--" . $boundary . "--\r\n";

        return $data;
    }

    /*
     * 创建目录
     * @param  string  $bucketName
     * @param  string  $path 目录路径，必须以‘/’结尾
     *
     */
    public static function createFolder($bucketName, $path, 
                  $toOverWrite = 0, $bizAttr = null) {
        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $path);
        $sign = Auth::appSign_more($expired, $bucketName);

        $data = array(
            'op' => 'create',
            'to_over_write' => $toOverWrite,
            'biz_attr' => (isset($bizAttr) ? $bizAttr : ''),
        );
        
        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
                'Content-Type: application/json',
            ),
        );

        return self::sendRequest($req);
    }
        
    /*
     * 目录列表,前缀搜索
     * @param  string  $bucketName
     * @param  string  $path     目录路径 web.file.myqcloud.com/files/v1/[appid]/[bucket_name]/[DirName]/
     *                           web.file.myqcloud.com/files/v1/appid/[bucket_name]/[DirName]/[prefix] <- 如果填写prefix, 则列出含此前缀的所有文件
     * @param  int     $num      拉取的总数
     * @param  string  $pattern  eListBoth,ListDirOnly,eListFileOnly  默认both
     * @param  int     $order    默认正序(=0), 填1为反序,
     * @param  string  $offset   透传字段,用于翻页,前端不需理解,需要往前/往后翻页则透传回来
     *  
     */
    public static function listFiles(
                    $bucketName, $path, $num = 20, 
                    $pattern = 'eListBoth', $order = 0, $offset = null) {

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $path);
        $sign = Auth::appSign_more($expired, $bucketName);

        $data = array(
            'op' => 'list',
            'num' => $num,
            'pattern' => $pattern,
            'order' => $order,
            'offset' => $offset,
        );
        
        //$data = json_encode($data);
        $url = $url . '?' . http_build_query($data);

        $req = array(
            'url' => $url,
            'method' => 'get',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
                'Content-Type: application/json',
            ),
        );

        return self::sendRequest($req);
    } 


    /*
     * 目录/文件信息 update
     * @param  string  $bucketName
     * @param  string  $path 路径，如果是目录则必须以‘/’结尾
     *
     */
    public static function update($bucketName, $path, 
                  $bizAttr = null) {

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $path);
        $sign = Auth::appSign_once(
                '/' . Conf::APPID . '/' . $bucketName . $path, 
                $bucketName);
        //$sign = Auth::appSign_more($expired, $bucketName);

        $data = array(
            'op' => 'update',
            'biz_attr' => $bizAttr,
        );
        
        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
                'Content-Type: application/json',
            ),
        );

        return self::sendRequest($req);
    }

    /*
     * 目录/文件信息 查询
     * @param  string  $bucketName
     * @param  string  $path 路径，如果是目录则必须以‘/’结尾
     *  
     */
    public static function stat(
                    $bucketName, $path) {

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $path);
        $sign = Auth::appSign_more($expired, $bucketName);

        $data = array(
            'op' => 'stat',
        );

        //$data = json_encode($data);
        $url = $url . '?' . http_build_query($data);

        $req = array(
            'url' => $url,
            'method' => 'get',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
                'Content-Type: application/json',
            ),
        );

        return self::sendRequest($req);
    } 

    /*
     * 删除文件及目录
     * @param  string  $bucketName
     * @param  string  $path 路径，如果是目录则必须以‘/’结尾
     *
     */
    public static function del($bucketName, $path) {

        $expired = time() + self::EXPIRED_SECONDS;
        $url = self::generateResUrl($bucketName, $path);
        $sign = Auth::appSign_once(
                '/' . Conf::APPID . '/' . $bucketName . $path, 
                $bucketName);
        //$sign = Auth::appSign_more($expired, $bucketName);

        $data = array(
            'op' => 'delete',
        );
        
        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => 120,
            'data' => $data,
            'header' => array(
                'Authorization:'.$sign,
                'Content-Type: application/json',
            ),
        );

        return self::sendRequest($req);
    }
    
//end of script
}

