<?php
namespace Tests\Unit;

use AppBundle\Extension\AutoLoginProcessor;
use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\AutoLoginRequest;
use AwardWallet\Engine\testprovider\Checker\AutoLogin;
use Doctrine\DBAL\Connection;
use Monolog\Logger;

/**
 * @backupGlobals disabled
 */
class AutoLoginTest extends BaseControllerTestClass
{

    private function getRequest(bool $valid = true): AutoLoginRequest
    {
        return (new AutoLoginRequest())
            ->setProvider("testprovider")
            ->setLogin("Checker.AutoLogin")
            ->setPassword($valid ? 'valid-autologin' : 'invalid-autologin')
            ->setUserId('userId' . rand(1000, 9999) . time())
            ->setUserData('userData' . rand(1000, 9999) . time())
            ->setSupportedProtocols(['http']);
    }

    private function getProcessor() : AutoLoginProcessor
    {
        $factory = $this->container->get('aw.checker_factory');
        $logger = $this->getCustomMock(Logger::class);
        $s3Client = $this->getCustomMock(S3Custom::class);
        $connection = $this->getCustomMock(Connection::class);
        $connection->method('executeQuery')->willReturnCallback(function($query, $params = [], $types = [], $qcp = null)
        {
            $query =   "SELECT 
                            'testprovider' as ProviderCode, 
                            1 as ProviderState, 
                            " . PROVIDER_ENGINE_CURL . " as ProviderEngine,
                            1 as AutoLogin, 
                            'http://LoginURL' as LoginURL, 
                            'http://ClickURL' as ClickURL, 
                            'http://ImageURL' as ImageURL 
                        FROM Provider";
            return $this->connection->executeQuery($query);
        });

        return new AutoLoginProcessor($logger, $connection, $factory, $s3Client);
    }

    public function testValidAutologin()
    {
        $request = $this->getRequest();
        $id = 'someId_'.rand(1000, 9999).time();
        $processor = $this->getProcessor();
        $result = $processor->processAutoLoginRequest($request, $id, $this->partner);

        $this->assertEquals($request->getUserData(), $result->getUserData());
        $valid = true;
        $valid = $valid && (strpos($result->getResponse(), sha1($request->getLogin().$request->getPassword())) !== false);
        $valid = $valid && (strpos($result->getResponse(), $request->getLogin()) !== false);
        $valid = $valid && (strpos($result->getResponse(), $request->getPassword()) !== false);
        $this->assertEquals(true, $valid);
    }

    public function testInvalidAutologin()
    {
        $request = $this->getRequest(false);
        $id = 'someId_'.rand(1000, 9999).time();
        $processor = $this->getProcessor();
        $result = $processor->processAutoLoginRequest($request, $id, $this->partner);

        $this->assertEquals($request->getUserData(), $result->getUserData());
        $valid = false;
        $valid = $valid || (strpos($result->getResponse(), sha1($request->getLogin().$request->getPassword())) !== false);
        $valid = $valid || (strpos($result->getResponse(), $request->getLogin()) !== false);
        $valid = $valid || (strpos($result->getResponse(), $request->getPassword()) !== false);
        $this->assertEquals(false, $valid);
        $this->assertTrue(strpos($result->getResponse(), AutoLogin::RedirectURL) !== false);
    }

    public function testSupportedProtocols()
    {
        $request = $this->getRequest()->setSupportedProtocols(['http', 'https']);
        $id = 'someId_'.rand(1000, 9999).time();
        $processor = $this->getProcessor();
        $result = $processor->processAutoLoginRequest($request, $id, $this->partner);
        $this->assertEquals(true, strpos($result->getResponse(), 'supportedProtocols = '.json_encode($request->getSupportedProtocols())));
    }
}