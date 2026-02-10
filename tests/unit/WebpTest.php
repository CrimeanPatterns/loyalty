<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\Property;
use AppBundle\Model\Resources\SubAccount;
use AppBundle\Model\Resources\UserData;

/**
 * @backupGlobals disabled
 */
class WebpTest extends \Codeception\TestCase\Test
{

    public function testJpegSupport(){
        $image = imagecreate(100, 100);
        imagepalettetotruecolor($image);
        $tempFile = tempnam(sys_get_temp_dir(), "webp");
        // test that we have jpeg compiled into gd
        \imagewebp($image);
        unlink($tempFile);
    }

}