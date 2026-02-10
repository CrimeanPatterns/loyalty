<?php
namespace Tests\Unit;


use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Parsing\LuminatiProxyManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class LuminatiProxyManagerServiceTest extends BaseWorkerTestClass
{
    /** @var \CurlDriver */
    private $curlDriver;
    /** @var LuminatiProxyManager\Api */
    private $api;
    /** @var LuminatiProxyManager\Client */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $host = $this->container->getParameter('env(LPM_HOST)');

        $this->curlDriver = $this->createMock(\CurlDriver::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->api = new LuminatiProxyManager\Api($this->curlDriver, $logger, $host);
        $this->client = new LuminatiProxyManager\Client($this->api, $logger);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->curlDriver = null;
        $this->api = null;
        $this->client = null;
    }

    public function testLuminatiProxyManagerServiceExisting()
    {
        /** @var ContainerInterface $serviceLocator */
        $serviceLocator = $this->container->get("aw.parsing.web.service_locator");
        /** @var LuminatiProxyManager\Client */
        $lpm = $serviceLocator->get(LuminatiProxyManager\Client::class);
        $this->assertNotNull($lpm);
        $this->assertTrue($lpm instanceof LuminatiProxyManager\Client);
    }

    public function testGetApiLogic()
    {
        $this->curlDriver->method('request')
            ->willReturn(
                new \HttpDriverResponse('{"status": "ok"}', 200)
            );
        $this->curlDriver->expects($this->once())
            ->method('request');

        $response = $this->api->getVersion();

        $this->assertTrue($response instanceof \stdClass);
        $this->assertEquals($response->status, "ok");
    }

    public function testPostApiLogic()
    {
        $this->curlDriver->method('request')
            ->willReturn(
                new \HttpDriverResponse(true, 204)
            );
        $this->curlDriver->expects($this->once())
            ->method('request');

        $response = $this->api->deleteProxyPort(24000);

        $this->assertTrue($response);
    }

    public function testCreatePort()
    {
        $this->curlDriver->method('request')
            ->willReturn(
                new \HttpDriverResponse('{"data": {"port": 24000}}', 200)
            );

        $port = $this->client->createProxyPort(
            new LuminatiProxyManager\Port()
        );

        $this->assertEquals($port, 24000);
    }

    public function testDeletePort()
    {
        $this->curlDriver->method('request')
            ->willReturn(
                new \HttpDriverResponse(true, 204)
            );

        $result = $this->client->deleteProxyPort('24000');

        $this->assertTrue($result);
    }

    public function testSetPortData()
    {
        $time = time();
        $data = [
            'proxy' => [
                'ssl' => true,
                'tls_lib' => 'flex_tls',
                'ext_proxies' => ['1.1.1.1'],
                'multiply' => 3,
                'proxy_connection_type' => 'https',
                'port' => 24000,
                'rules' => [
                    [
                        'action' => [
                            'null_response' => true
                        ],
                        'action_type' => 'null_response',
                        'trigger_type' => 'url',
                        'url' => "\\.(jpg|png|jpeg|svg|gif|mp3|mp4|avi)(#.*|\\?.*)?$"
                    ],
                    [
                        'action' => [
                            'null_response' => true
                        ],
                        'action_type' => 'null_response',
                        'trigger_type' => 'url',
                        'url' => 'test.com'
                    ]
                ]
            ],
            'create_users' => false
        ];

        $portData = (new LuminatiProxyManager\Port)->setExternalProxy($data['proxy']['ext_proxies'])
            ->setTlsLib($data['proxy']['tls_lib'])
            ->setEnableSslAnalyzing($data['proxy']['ssl'])
            ->setMultiplyProxyPort($data['proxy']['multiply'])
            ->setProxyConnectionType($data['proxy']['proxy_connection_type'])
            ->setProxyPort($data['proxy']['port'])
            ->BanMediaContent()
            ->setBanUrlContent('test.com')
            ->getData();

        $this->assertTrue($portData['proxy']['internal_name'] >= $time);
        unset($portData['proxy']['internal_name']);

        $this->assertEquals($data, $portData);
    }

    public function testIntegrationLpmInTAccountChecker()
    {
        $this->aw = $this->getModule('\Helper\Aw');
        $providerCode = "test" . bin2hex(random_bytes(4));
        $this->aw->createAwProvider(null, $providerCode, [], [
            'InitBrowser' => function() {
                parent::InitBrowser();
                $lpm = $this->services->get(LuminatiProxyManager\Client::class);
                if (!is_null($lpm) && $lpm instanceof LuminatiProxyManager\Client) {
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