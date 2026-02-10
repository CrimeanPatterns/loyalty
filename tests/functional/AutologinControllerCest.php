<?php

namespace Tests\Functional;


use AppBundle\Controller\AutoLoginController;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\AutoLoginRequest;
use AppBundle\Model\Resources\AutoLoginResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\CryptPasswordService;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Codeception\Stub\Expected;
use Codeception\Util\Stub;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AutologinControllerCest
{
    private const AUTOLOGIN = '/autologin';
    private const AUTOLOGIN_V2 = '/v2/autologin';

    protected $userData;
    protected $partner;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));

        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);
    }

    private function buildRequest(bool $valid = true): AutoLoginRequest
    {
        return (new AutoLoginRequest())
            ->setProvider("testprovider")
            ->setLogin("Checker.AutoLogin")
            ->setPassword($valid ? 'valid-autologin' : 'invalid-autologin')
            ->setUserId('userId' . rand(1000, 9999) . time())
            ->setUserData('userData' . rand(1000, 9999) . time())
            ->setSupportedProtocols(['http']);
    }

    public function testAutologinTimeout(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService('jms_serializer');

        $request = $this->buildRequest();
        $mqChannel = Stub::makeEmpty(AMQPChannel::class, [
            'basic_consume' => Expected::once(),
            'basic_qos' => Expected::once(),
            'wait' => Expected::once(function () {
                throw new AMQPTimeoutException();
            }),
            'queue_declare' => Expected::atLeastOnce(function ($queue, $passive = false, $durable = false, $exclusive = false){
                return ["SomeTmpQueueName", 0, 0];
            })
        ]);

        $controller = $this->prepareController($I, $mqChannel, $request);
        $I->mockService(AutoLoginController::class, $controller);

        $I->sendPOST(self::AUTOLOGIN, $serializer->serialize($request, 'json'));

        $I->seeResponseCodeIs(Response::HTTP_OK);
        /** @var AutoLoginResponse $postResponse */
        $response = $serializer->deserialize($I->grabResponse(), AutoLoginResponse::class, 'json');

        $I->assertEquals($request->getUserData(), $response->getUserData());
    }

    public function testAutologinSuccess(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService('jms_serializer');

        $request = $this->buildRequest();
        $response = (new AutoLoginResponse())->setResponse('success html')->setUserData($request->getUserData());
        $mqMsg = new AMQPMessage(serialize($response));
        $mqMsg->delivery_info = [
            'channel' => Stub::makeEmpty(AMQPChannel::class),
            'delivery_tag' => '',
        ];

        $testCallback = null;
        $mqChannel = Stub::makeEmpty(AMQPChannel::class, [
            'basic_consume' => Expected::once(function($queue, $consumer_tag, $no_local, $no_ack, $exclusive, $nowait, $callback) use(&$testCallback) {
                $testCallback = $callback;
            }),
            'basic_qos' => Expected::once(),
            'wait' => Expected::once(function () use(&$testCallback, $mqMsg) {
                call_user_func($testCallback, $mqMsg);
            }),
            'queue_declare' => Expected::atLeastOnce(function (){
                return ["SomeTmpQueueName", 0, 0];
            })
        ]);

        $controller = $this->prepareController($I, $mqChannel, $request);
        $I->mockService(AutoLoginController::class, $controller);

        $I->sendPOST(self::AUTOLOGIN, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        /** @var AutoLoginResponse $result */
        $result = $serializer->deserialize($I->grabResponse(), AutoLoginResponse::class, 'json');
        $I->assertEquals($request->getUserData(), $result->getUserData());
    }

    public function testUnavailableProvider(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService('jms_serializer');

        $request = $this->buildRequest();
        $mqChannel = Stub::makeEmpty(AMQPChannel::class);
        $controller = $this->prepareController($I, $mqChannel, $request, true);
        $I->mockService(AutoLoginController::class, $controller);

        $I->sendPOST(self::AUTOLOGIN, $serializer->serialize($request, 'json'));

        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string']);
    }

    public function testUnavailableProtocols(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService('jms_serializer');

        $request = $this->buildRequest()->setSupportedProtocols(['fas', 'httpl']);
        $mqChannel = Stub::makeEmpty(AMQPChannel::class);
        $controller = $this->prepareController($I, $mqChannel, $request);
        $I->mockService(AutoLoginController::class, $controller);

        $I->sendPOST(self::AUTOLOGIN, $serializer->serialize($request, 'json'));

        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string']);
    }

    private function prepareController(\FunctionalTester $I, AMQPChannel $mqChannel, $request, $unavailableProvider = false)
    {
        /** @var Connection $serializer */
        $connection = $I->grabService('database_connection');
        /** @var Serializer $serializer */
        $serializer = $I->grabService('jms_serializer');
        /** @var Loader $serializer */
        $loader = $I->grabService(Loader::class);
        /** @var Producer $delayedProducer */
        $delayedProducer = $I->grabService("old_sound_rabbit_mq.check_delayed_producer");

        $token = Stub::makeEmpty(TokenStorageInterface::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER])
        ]);

        $connectionMock = Stub::make(Connection::class, [
            'executeQuery' => Expected::atLeastOnce(function($query, $params = [], $types = [], $qcp = null) use($connection, $unavailableProvider) {
                if ($unavailableProvider) {
                    return $connection->executeQuery("SELECT LoginURL FROM Provider WHERE Code = 'SomeUnavailableProvider'");
                }
                return $connection->executeQuery("SELECT 'SomeUrl' AS LoginURL, 1 AS AutoLogin");
            })
        ]);

        $prophet = (new Prophet());
        $dm = $prophet->prophesize(DocumentManager::class)
            ->persist(Argument::cetera())
            ->will(function ($arguments) {
                $row = $arguments[0];
                $row->setId('someId_'.rand(1000, 9999).time());
            })
            ->getObjectProphecy()
            ->flush()
            ->shouldBeCalled()
            ->getObjectProphecy()
            ->reveal();

        $requestFactory = Stub::make(RequestFactory::class, [
            'buildRequest' => $request
        ]);
        $responseFactory = Stub::make(ResponseFactory::class, [
            'buildResponse' => Expected::atLeastOnce(function ($response, $apiVersion) use($serializer) {
                return new Response($serializer->serialize($response, 'json'));
            })
        ]);

        $logger = Stub::makeEmpty(Logger::class);
        $providerHelper = Stub::makeEmpty(ProvidersHelper::class);
        $mqSender = new MQSender($logger, $mqChannel, Stub::makeEmpty(SerializerInterface::class), true, $delayedProducer);

        return new AutoLoginController(
            $connectionMock,
            $token,
            $mqSender,
            $serializer,
            $logger,
            $loader,
            $dm,
            new CryptPasswordService($connection),
            $providerHelper,
            $requestFactory,
            $responseFactory
        );
    }

}