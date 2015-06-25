<?php
namespace Tencentyun;

class Conf
{
    const PKG_VERSION = '1.0.0'; 

    const API_IMAGE_END_POINT = 'http://web.image.myqcloud.com/photos/v1/';

	const API_VIDEO_END_POINT = 'http://web.video.myqcloud.com/videos/v1/';

	const API_COSAPI_END_POINT = 'http://web.file.myqcloud.com/files/v1/';
		
    const APPID = '1000029';

    const SECRET_ID = 'AKID4EAND9RuE6psJYOuFKlh0Jeg9Q9BmmS2';

    const SECRET_KEY = 'jvAAGz07ElrJF1oKWpbKhAIzWF5W6BZN';

    public static function getUA() {
        return 'QcloudPHP/'.self::PKG_VERSION.' ('.php_uname().')';
    }
}


//end of script
