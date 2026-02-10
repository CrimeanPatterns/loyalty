<?php

namespace Tests\Unit;

use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\AWS\Util;
use AwardWallet\WebdriverClient\NodeFinder;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\Aw;
use Monolog\Logger;

/**
 * @backupGlobals disabled
 */
class LoggingTest extends BaseWorkerTestClass
{

    public function testServerName(){
        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->once())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) {
                $log = file_get_contents($logDir . "/log.html");
                $this->assertStringContainsString("Server info > hostname: loyalty, local ip: 1.2.3.4", $log);
            })
        ;

        $awsUtil = $this->createMock(Util::class);
        $awsUtil
            ->expects($this->once())
            ->method('getLocalIP')
            ->willReturn('1.2.3.4')
        ;

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('balance.random')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker(null, null, null, $s3client, null, null, null, null, null, $awsUtil)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
    }

    public function testSelenium(){
        $seleniumAddress = random_int(1, 255) . "." . random_int(1, 255) . "." . random_int(1, 255) . "." . random_int(1, 255);

        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->once())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) use ($seleniumAddress){
                $log = file_get_contents($logDir . "/log.html");
                $this->assertStringContainsString("Cancelled firefox session", $log);
                $this->assertStringContainsString("creating new selenium session {", $log);
            })
        ;

        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $seleniumFinder = $this->createMock(\SeleniumFinderInterface::class);
        $seleniumFinder
            ->expects($this->atMost(1))
            ->method('getServers')
            ->willReturn([new \SeleniumServer($seleniumAddress, 4567)])
        ;
        $aw->mockService("aw.selenium_finder", $seleniumFinder);

        $nodeFinder = $this->createMock(NodeFinder::class);
        $nodeFinder
            ->expects($this->atMost(1))
            ->method('getNode')
            ->willReturn($seleniumAddress)
        ;
        $aw->mockService(NodeFinder::class, $nodeFinder);

        $firefoxStarter = $this->createMock(\FirefoxStarter::class);
        $firefoxStarter
            ->expects($this->once())
            ->method('prepareSession')
            ->willThrowException(new \Exception("Cancelled firefox session"))
        ;
        $aw->mockService("aw.selenium_firefox_starter", $firefoxStarter);

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.Selenium')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, $s3client)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
    }

}