<?php

namespace Tests\Functional;


use AppBundle\Document\CheckAccount;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\CheckAccountPackageRequest;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\CryptPasswordService;
use AppBundle\Worker\CheckExecutor\CheckAccountPreExecutor;
use Codeception\Example;
use Codeception\Stub\Expected;
use Codeception\Util\Stub;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ObjectRepository;
use Helper\CustomDb;
use JMS\Serializer\Serializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockBuilder;
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class CheckAccountControllerCest
{
    private const CHECK_ACCOUNT_POST = '/account/check';
    private const CHECK_ACCOUNT_PACKAGE_POST = '/account/check/package';
    private const CHECK_ACCOUNT_GET = '/account/check/%s';
    private const CHECK_ACCOUNT_QUEUE = '/account/queue';
    private const CHECK_ACCOUNT_POST_V2 = '/v2/account/check';
    private const CHECK_ACCOUNT_GET_V2 = '/v2/account/check/%s';
    private const CHECK_ACCOUNT_QUEUE_V2 = '/v2/account/queue';
    private const CHECK_ACCOUNT_PACKAGE_POST_V2 = '/v2/account/check/package';

    protected $userData;
    protected $partner;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));

        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);
    }

    private function buildRequest(string $providerCode = 'testprovider')
    {
        return (new CheckAccountRequest())
            ->setProvider($providerCode)
            ->setPriority(7)
            ->setLogin('SomeLogin')
            ->setPassword('qqq')
            ->setUserId('SomeUserID')
            ->setUserData($this->userData);
    }

    /**
     * @dataProvider getCheckAccountCestData
     */
    public function testCheckAccount(\FunctionalTester $I, Example $example)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $I->sendPOST($example['Post'], $serializer->serialize($this->buildRequest(), 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
        /** @var PostCheckResponse $postResponse */
        $postResponse = $serializer->deserialize($I->grabResponse(), PostCheckResponse::class, 'json');

        $I->sendGET(sprintf($example['Get'], $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        /** @var BaseCheckResponse $checkResponse */
        $checkResponse = $serializer->deserialize($I->grabResponse(), $example['ResponseCls'], 'json');
        $I->assertEquals($this->userData, $checkResponse->getUserdata());
        $I->assertEquals($postResponse->getRequestid(), $checkResponse->getRequestId());

        $I->sendGET(sprintf($example['WrongVersionGet'], $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider checkAccountMethodData
     */
    public function testCheckAccountMethod(\FunctionalTester $I, Example $example)
    {
        $rabbitMessages = [];
        $I->mockService(AMQPChannel::class, Stub::makeEmpty(AMQPChannel::class, [
            'basic_publish' => function($message, $exchange, $routingKey) use (&$rabbitMessages) {
                $rabbitMessages[] = [
                    'message' => $message,
                    'exchange' => $exchange,
                    'routingKey' => $routingKey,
                ];
            }
        ]));

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $providerCode = "p" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $providerCode, ['IsExtensionV3ParserEnabled' => $example['IsExtensionV3ParserEnabled']]);
        $request = $this->buildRequest($providerCode);
        call_user_func($example['configureRequest'], $request);
        $I->sendPOST('/account/check', $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs($example['expectedCode']);

        if ($example['expectedCode'] !== Response::HTTP_OK) {
            return;
        }

        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
        /** @var PostCheckResponse $postResponse */
        $postResponse = $serializer->deserialize($I->grabResponse(), PostCheckResponse::class, 'json');

        // one to check_account, one to news
        $I->assertCount(2, $rabbitMessages);
        /** @var AMQPMessage $message */
        $message = $rabbitMessages[0]['message'];
        $body = unserialize($message->body);
        $I->assertEquals($example['expectedRabbitMessageMethod'], $body['method']);
        $I->assertEquals($postResponse->getRequestid(), $body['id']);

        /** @var ObjectRepository $repo */
        $repo = $I->grabService("aw.mongo.repo.account");
        /** @var CheckAccount $row */
        $row = $repo->find($postResponse->getRequestid());
        /** @var Serializer $serializer */
        $serializer = $I->grabService(Serializer::class);
        /** @var CheckAccountRequest $request */
        $request = $serializer->deserialize(json_encode($row->getRequest()), CheckAccountRequest::class, 'json');
        $I->assertEquals($example['expectedBrowserExtensionAllowed'], $request->isBrowserExtensionAllowed());
        $I->assertEquals($example['expectedBrowserExtensionSessionId'], !empty($request->getBrowserExtensionSessionId()));
    }

    private function checkAccountMethodData() : array
    {
        \AwardWalletOldConstants::load();

        return [
            [
                'IsExtensionV3ParserEnabled' => false,
                'configureRequest' => fn(CheckAccountRequest $request) => $request,
                'expectedCode' => Response::HTTP_OK,
                'expectedRabbitMessageMethod' => 'account',
                'expectedBrowserExtensionAllowed' => false,
                'expectedBrowserExtensionSessionId' => false,
            ],
            [
                'IsExtensionV3ParserEnabled' => true,
                'configureRequest' => fn(CheckAccountRequest $request) => $request,
                'expectedCode' => Response::HTTP_OK,
                'expectedRabbitMessageMethod' => 'account',
                'expectedBrowserExtensionAllowed' => false,
                'expectedBrowserExtensionSessionId' => false,
            ],
            [
                'IsExtensionV3ParserEnabled' => false,
                'configureRequest' => function(CheckAccountRequest $request) {
                    $request->setBrowserExtensionAllowed(true);
                },
                'expectedCode' => Response::HTTP_OK,
                'expectedRabbitMessageMethod' => 'account',
                'expectedBrowserExtensionAllowed' => true,
                'expectedBrowserExtensionSessionId' => false,
            ],
            [
                'IsExtensionV3ParserEnabled' => true,
                'configureRequest' => function(CheckAccountRequest $request) {
                    $request->setBrowserExtensionAllowed(true);
                },
                'expectedCode' => Response::HTTP_OK,
                'expectedRabbitMessageMethod' => 'account',
                'expectedBrowserExtensionAllowed' => true,
                'expectedBrowserExtensionSessionId' => true,
            ],
        ];
    }

    public function testCheckAccountPackage(\FunctionalTester $I)
    {
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");

        $request = (new CheckAccountPackageRequest)->setPackage([$this->buildRequest(), $this->buildRequest()]);
        $I->sendPOST(self::CHECK_ACCOUNT_PACKAGE_POST, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['package' => 'array']);

        $I->sendPOST(self::CHECK_ACCOUNT_PACKAGE_POST_V2, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['package' => 'array']);
    }

    public function testCheckAccountQueue(\FunctionalTester $I)
    {
        foreach ([self::CHECK_ACCOUNT_QUEUE, self::CHECK_ACCOUNT_QUEUE_V2] as $url) {
            $I->sendGET($url);
            $I->seeResponseCodeIs(Response::HTTP_OK);
            $I->seeResponseMatchesJsonType(['queues' => 'array']);
        }
    }

    public function testCryptPassword(\FunctionalTester $I)
    {
        $I->grabService(Loader::class);
        $originPassword = 'g5f4' . rand(1000, 9999) . '_q';
        $request = $this->buildRequest()->setLogin('balance.point')->setPassword($originPassword);

        $serializer = $I->grabService("jms_serializer");
        $I->sendPOST(self::CHECK_ACCOUNT_POST, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
        $requestId = $I->grabDataFromResponseByJsonPath('.requestId')[0];
        $I->assertNotEmpty($requestId);

        /** @var DocumentManager $dm */
        $dm = $I->grabService("doctrine_mongodb.odm.default_document_manager");
        $row = $dm->find(CheckAccount::class, $requestId);
        $dm->refresh($row);
        $I->assertNotEquals($originPassword, base64_decode($row->getRequest()['password']));
        $I->assertNotEmpty($row->getRequest()['password']);
    }

    protected function getCheckAccountCestData()
    {
        return [
            [
                'Post' => self::CHECK_ACCOUNT_POST,
                'Get' => self::CHECK_ACCOUNT_GET,
                'WrongVersionGet' => self::CHECK_ACCOUNT_GET_V2,
                'ResponseCls' => CheckAccountResponse::class
            ],
            [
                'Post' => self::CHECK_ACCOUNT_POST_V2,
                'Get' => self::CHECK_ACCOUNT_GET_V2,
                'WrongVersionGet' => self::CHECK_ACCOUNT_GET,
                'ResponseCls' => \AppBundle\Model\Resources\V2\CheckAccountResponse::class
            ],
        ];
    }
}