<?php

namespace Tests\Unit;

use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Engine\Settings;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\Aw;
use Monolog\Logger;
use WebDriverBy;

/**
 * @backupGlobals disabled
 */
class SeleniumIntegrationTest extends BaseWorkerTestClass
{

    /**
     * @var Aw
     */
    private $aw;

    public function _before()
    {
        parent::_before();

        /** @var Aw $aw */
        $this->aw = $this->getModule('\Helper\Aw');
    }

    public function testState()
    {
        $providerCode = "test" . bin2hex(random_bytes(4));
        $this->aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function() {
                parent::InitBrowser();
                $this->useSelenium();
                $this->useChromium();
                $this->usePacFile(false);
                $this->KeepState = true;
            },
            'LoadLoginForm' => function() {
                $this->http->GetURL('http:/web-http.docker/cookie-test.html');
                return $this->http->FindPreg("#Cookies test page#ims");
            },
            'Parse' => function() {
                if ($this->waitForElement(WebDriverBy::id('test-result'), 1)->getText() === 'saved cookie found') {
                    $this->SetBalance(2);
                } else {
                    $this->SetBalance(1);
                }
            }
        ], [], ['SeleniumCheckerHelper']);

        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->atLeastOnce())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) {
                $log = file_get_contents($logDir . "/log.html");
                $this->assertStringContainsString("creating new selenium session {", $log);
            })
        ;

        // first request, no cookie
        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
                ->setUserid('blah')
                ->setLogin('blah')
                ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, $s3client)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());

        // second request, with saved state, there should be cookie
        $request->setBrowserstate($response->getBrowserstate());
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, $s3client)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(2, $response->getBalance());
    }

    public function testScreenshotOnError()
    {
        $providerCode = "test" . bin2hex(random_bytes(4));
        $this->aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function() {
                parent::InitBrowser();
                $this->useSelenium();
                $this->useChromium();
                $this->usePacFile(false);
            },
            'LoadLoginForm' => function() {
                $this->http->GetURL('http:/web-http.docker/cookie-test.html');
                return $this->http->FindPreg("#Cookies test page#ims");
            },
            'Parse' => function() {
                // expect failure on second attempt
                if ($this->AccountFields["Pass"] === 'invalid-operation') {
                    $this->driver->findElement(WebDriverBy::id('no-this-element'));
                }
                $this->SetBalance(1);
            }
        ], [], ['SeleniumCheckerHelper']);

        // first request, no proxy
        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->atLeastOnce())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) {
                $log = file_get_contents($logDir . "/log.html");
                $this->assertFileExists($logDir . '/step00.html');
                $this->assertFileNotExists($logDir . 'last.png');
                $this->assertStringContainsString("creating new selenium session {", $log);
            })
        ;

        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
                ->setUserid('blah')
                ->setLogin('blah')
                ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, $s3client)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());

        // second request, there should be error and screenshot
        $s3client = $this->createMock(S3Custom::class);
        $s3client
            ->expects($this->atLeastOnce())
            ->method('uploadCheckerLogToBucket')
            ->willReturnCallback(function(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo) {
                $log = file_get_contents($logDir . "/log.html");
                $this->assertFileExists($logDir . '/error.png');
                $this->assertStringContainsString("creating new selenium session {", $log);
            })
        ;

        $request->setBrowserstate($response->getBrowserstate());
        $request->setPassword("invalid-operation");
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker($this->container->get('console_exception_logger'), null, null, $s3client)->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
    }

}