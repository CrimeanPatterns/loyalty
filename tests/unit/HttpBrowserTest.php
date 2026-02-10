<?php

namespace Tests\Unit;

/**
 * @backupGlobals disabled
 */
class HttpBrowserTest extends \Codeception\TestCase\Test
{

    public function _before()
    {
        parent::_before();
        require_once __DIR__ . '/../../vendor/awardwallet/service/old/constants.php';
        require_once __DIR__ . '/../../src/AppBundle/Lib/functions.php';
    }

    public function testSwitchProxy()
    {
        $driver = $this->getMockBuilder(\HttpDriverInterface::class)->disableOriginalConstructor()->getMock();

        $errorResponse = new \HttpDriverResponse();
        $errorResponse->errorCode = 7;
        $errorResponse->errorMessage = "Failed to connect to 127.0.0.1 port 3128: Connection refused";
        $errorResponse->request = new \HttpDriverRequest('/blah');
        $driver->expects($this->exactly(2))->method('request')->willReturn($errorResponse);

        $http = new \HttpBrowser("console", $driver);
        $http->RetryCount = 1;

        $http->SetProxy("localhost:80");
        $http->setProxyList(["localhost:82", "localhost:83"]);
        ob_start();
        $http->GetURL("http://localhost/test");
        $logs = ob_get_contents();
        ob_end_clean();
        $this->assertStringContainsString("trying next proxy in list", $logs);
    }

    public function testNoSwitchProxy()
    {
        $driver = $this->getMockBuilder(\HttpDriverInterface::class)->disableOriginalConstructor()->getMock();

        $errorResponse = new \HttpDriverResponse();
        $errorResponse->request = new \HttpDriverRequest('/blah');
        $driver->expects($this->exactly(2))->method('request')->willReturn($errorResponse);

        $http = new \HttpBrowser("console", $driver);
        $http->RetryCount = 1;

        ob_start();
        $http->GetURL("http://localhost/test");
        $logs = ob_get_contents();
        ob_end_clean();
        $this->assertStringNotContainsString("trying next proxy in list", $logs);
    }

}