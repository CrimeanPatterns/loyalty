<?php


namespace Tests\Unit;

use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use Doctrine\Common\Persistence\ObjectRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class WrappedProxyServiceTest extends BaseWorkerTestClass
{
    /** @var \CurlDriver */
    private $curlDriver;
    /** @var WrappedProxyClient */
    private $client;
    private $externalHostParts;

    protected function setUp(): void
    {
        parent::setUp();
        $internalHost = $this->container->getParameter('env(WRAPPED_PROXY_INTERNAL_HOST)');
        $externalHost = $this->container->getParameter('env(WRAPPED_PROXY_EXTERNAL_HOST)');
        $this->externalHostParts = explode(':', $externalHost);

        $this->curlDriver = $this->createMock(\CurlDriver::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->client = new WrappedProxyClient($this->curlDriver, $logger, $internalHost, $externalHost);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->curlDriver = null;
        $this->client = null;
    }

    public function testWrappedProxyServiceExisting()
    {
        /** @var ContainerInterface $serviceLocator */
        $serviceLocator = $this->container->get("aw.parsing.web.service_locator");
        /** @var WrappedProxyClient */
        $wrappedProxy = $serviceLocator->get(WrappedProxyClient::class);
        $this->assertNotNull($wrappedProxy);
        $this->assertTrue($wrappedProxy instanceof WrappedProxyClient);
    }

    public function testCreateWrappedProxyPort()
    {
        $username = "5d77fa40-4a6e-49c4-8518-f09bd6ff4a0e";
        $password = "331aa810-5771-11ee-bf80-13f55aa49823";
        $port = "35462";

        $this->curlDriver->method('request')
            ->will($this->onConsecutiveCalls(
                new \HttpDriverResponse(
                    "{}",
                    200
                ),
                new \HttpDriverResponse(
                    "{\"username\": \"{$username}\", \"password\": \"{$password}\", \"port\": \"$port\"}",
                    201
                )
            ));

        $wrappedProxy = $this->client->createPort([
            'proxyAddress' => '0.0.0.0',
            'proxyHost' => 'test',
            'proxyPort' => '3128',
            'proxyType' => 0,
            'proxyLogin' => 'login',
            'proxyPassword' => 'pass'
        ]);

        $this->assertEquals([
            'proxyAddress' => gethostbyname($this->externalHostParts[0]),
            'proxyHost' => $this->externalHostParts[0],
            'proxyPort' => $port,
            'proxyType' => 'http',
            'proxyLogin' => $username,
            'proxyPassword' => $password
        ], $wrappedProxy);
    }

    public function testIntegrationWrappedProxyInTAccountChecker()
    {
        $this->aw = $this->getModule('\Helper\Aw');
        $providerCode = "test" . bin2hex(random_bytes(4));
        $this->aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function() {
                parent::InitBrowser();
                $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                if (!is_null($wrappedProxy) && $wrappedProxy instanceof WrappedProxyClient) {
                    $this->SetBalance(1);
                } else {
                    $this->SetBalance(0);
                }
            },
            'Parse' => function() {
                return [];
            }
        ], [], []);

        $s3client = $this->createMock(S3Custom::class);
        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
            ->setUserid('blah')
            ->setLogin('blah')
            ->setPassword('blah');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        $this->getCheckAccountWorker(
            $this->container->get('console_exception_logger'),
            null,
            null,
            $s3client
        )->processRequest(
            $request,
            $response,
            $this->row
        );

        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, $response->getBalance());
    }
}