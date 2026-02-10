<?php

namespace Tests\Functional\RewardAvailability;


use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RegisterConfig;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountAutoRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Security\ApiUser;
use AwardWallet\Common\Parsing\MailslurpApiControllersCustom;
use Codeception\Util\Stub;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use MailSlurp\Apis\InboxControllerApi;
use MailSlurp\Models\InboxDto;
use MailSlurp\Models\InboxPreview;
use MailSlurp\Models\PageInboxProjection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class RegisterControllerCest
{
    private const REGISTER_ACCOUNT_POST = '/ra-account/register';
    private const REGISTER_ACCOUNT_GET = '/ra-account/register/%s';
    private const REGISTER_ACCOUNT_LIST = '/ra-providers/register/list';
    private const REGISTER_ACCOUNT_FIELDS = '/ra-providers/register/%s/fields';
    private const REGISTER_ACCOUNT_AUTO_POST = '/ra-account/register-auto';
    private const REGISTER_ACCOUNT_REPORT_FAIL_REGISTER = '/ra-account/report-fail-register';
    private const REGISTER_ACCOUNT_REGISTER_RETRY = '/ra-account/register-request-retry/%s';
    private const REGISTER_ACCOUNT_REGISTER_CHECK = '/ra-account/register-request-check/%s';
    private const REGISTER_ACCOUNT_REGISTER_QUEUE_CLEAR = '/ra-account/queue/%s/clear';

    protected $userData;
    protected $partner;
    protected $serializer;
    protected $dm;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));

        $this->serializer = $I->grabService("jms_serializer");
        $this->dm = $I->grabService(DocumentManager::class);

        $I->grabService("aw.old_loader");
    }

    public function _after()
    {
        $this->dm->createQueryBuilder(RegisterConfig::class)
            ->remove(RegisterConfig::class)
            ->getQuery()
            ->execute();

        $this->dm->createQueryBuilder(RegisterAccount::class)
            ->remove(RegisterAccount::class)
            ->getQuery()
            ->execute();
    }

    public function testRegisterAccount(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->sendGET(self::REGISTER_ACCOUNT_LIST);
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);

        $I->sendGET(self::REGISTER_ACCOUNT_LIST);
        $I->seeResponseCodeIs(Response::HTTP_OK);

        $I->sendGET(sprintf(self::REGISTER_ACCOUNT_FIELDS, 'testprovider'));
        $I->seeResponseCodeIs(Response::HTTP_OK);

        $d = $this->serializer->serialize($this->buildRequest(), 'json');
        $I->sendPOST(self::REGISTER_ACCOUNT_POST, $d);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
        /** @var PostCheckResponse $postResponse */
        $postResponse = $this->serializer->deserialize($I->grabResponse(), PostCheckResponse::class, 'json');

        $I->sendGET(sprintf(self::REGISTER_ACCOUNT_GET, $postResponse->getRequestid()));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        /** @var RegisterAccountResponse $checkResponse */
        $checkResponse = $this->serializer->deserialize($I->grabResponse(), RegisterAccountResponse::class, 'json');
        $I->assertEquals($postResponse->getRequestid(), $checkResponse->getRequestId());
    }

    public function testReportFailRegister(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->sendGET(self::REGISTER_ACCOUNT_REPORT_FAIL_REGISTER);
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);

        $I->sendGET(self::REGISTER_ACCOUNT_REPORT_FAIL_REGISTER);
        $I->seeResponseCodeIs(Response::HTTP_OK);
    }

    public function testRegisterAccountAutoGmail(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));
    }

    public function testRegisterAccountAutoDomain(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();
        $request->setEmail('@bla.com')->setCount(3);

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));
    }

   public function testRegisterAccountAutoWrongFormat(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();
        $request->setEmail('bla.com')->setCount(3);

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.error');
        $response = json_decode($I->grabResponse());
        $I->assertStringContainsString('Wrong format', $response->error);
    }

    public function testRegisterAccountAutoManual(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();

        $request->setEmail('bl@email.ru')->setCount(3);
        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals(1, count($response));
    }

    public function testRegisterAccountAutoFail(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();

        $request->setEmail('');
        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.error');
    }

    public function testRegisterAccountAutoManualConfig(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();
        $request->setEmail('');
        $this->createConfigManual();

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals(1, count($response));
    }

    public function testRegisterAccountAutoGmailConfig(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest();
        $request->setEmail('');
        $this->createConfigGmail();

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));
    }

    public function testRegisterAccountAutoMailslurpConfig(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $inboxControllerApi = Stub::make(InboxControllerApi::class, [
            'getAllInboxes' => $this->getAllInboxes(),
            'createInbox' => $this->createNewInbox(),
        ]);
        /** @var MailslurpApiControllersCustom $mailslurpApiControllers */
        $mailslurpApiControllers = $I->grabService(MailslurpApiControllersCustom::class);
        $mailslurpApiControllers->setInboxControllerApi($inboxControllerApi);

        $request = $this->buildAutoRequest();
        $request->setEmail('');
        $this->createConfigMailslurp();

        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));
    }

    public function testRetryAndCheckRegisterAccount(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest()->setCount(1);

        /**Successful auto register without config*/
        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));

        $regAcc = $this->dm->getRepository(RegisterAccount::class)
            ->findOneBy(['_id' => $response[0]->requestId]);

        $response = $regAcc->getResponse()->setState(11);
        $regAcc->setResponse($response);
        $this->dm->persist($regAcc);
        $this->dm->flush();

        $I->sendPost(sprintf(self::REGISTER_ACCOUNT_REGISTER_RETRY, $regAcc->getId()), []);
        $I->sendPost(sprintf(self::REGISTER_ACCOUNT_REGISTER_CHECK, $regAcc->getId()), []);

        $this->dm->refresh($regAcc);
        $this->dm->flush();
        $I->assertEquals($regAcc->getResponse()->getState(), 0);
        $I->assertEquals($regAcc->getIsChecked(), 1);
    }

    public function testClearRegisterQueue(\FunctionalTester $I)
    {
        $this->mockTockenStorage($I,[ApiUser::ROLE_USER, ApiUser::ROLE_REWARD_AVAILABILITY, ApiUser::ROLE_ADMIN]);

        $I->setHost(
            $I->grabParameter('reward_availability_host')
        );
        $request = $this->buildAutoRequest()->setCount(2);

        /**Successful auto register without config*/
        $data = $this->serializer->serialize($request, 'json');
        $I->sendPost(self::REGISTER_ACCOUNT_AUTO_POST, $data);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseJsonMatchesJsonPath('$.[*].requestId');
        $response = json_decode($I->grabResponse());
        $I->assertEquals($request->getCount(), count($response));

        $I->sendGET(self::REGISTER_ACCOUNT_REPORT_FAIL_REGISTER);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('queue', $response);
        $I->assertArrayHasKey('testprovider', $response['queue']);
        $I->assertEquals(2, $response['queue']['testprovider']);

        $I->sendPOST(sprintf(self::REGISTER_ACCOUNT_REGISTER_QUEUE_CLEAR, 'testprovider'));
        $I->seeResponseCodeIs(Response::HTTP_OK);

        $I->sendGET(self::REGISTER_ACCOUNT_REPORT_FAIL_REGISTER);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('queue', $response);
    }

    private function buildRequest()
    {
        return (new RegisterAccountRequest())
            ->setProvider('testprovider')
            ->setPriority(7)
            ->setFields([
                'FirstName' => 'Timeout',
                'Email' => 'bla@bla.com',
                'Password' => 'pass' . rand(100, 999),
                'LastName' => 'Johnson',
                'Address' => 'somewhere universe',
                'City' => 'galaxy'
            ])
            ->setUserData($this->userData);
    }

    private function buildAutoRequest()
    {
        return (new RegisterAccountAutoRequest())
            ->setProvider('testprovider')
            ->setEmail('testprovider@gmail.com')
            ->setDelay(0)
            ->setCount(2);
    }

    private function createConfigManual()
    {
        $regConfig = new RegisterConfig(
            'testprovider',
            'testemail@mail.com',
            'dots',
            1,
            3,
            0
        );
        $this->dm->persist($regConfig);
        $this->dm->flush($regConfig);
    }

    private function createConfigGmail()
    {
        $regConfig = new RegisterConfig(
            'testprovider',
            'testemail@gmail.com',
            'dots',
            1,
            3,
            0
        );
        $this->dm->persist($regConfig);
        $this->dm->flush($regConfig);
    }

    private function createConfigMailslurp()
    {
        $regConfig = new RegisterConfig(
            'testprovider',
            'test@mailslurpconfig.com',
            'dots',
            1,
            3,
            0
        );
        $this->dm->persist($regConfig);
        $this->dm->flush($regConfig);
    }

    private function getAllInboxes()
    {
        return new PageInboxProjection([
            'content' => [
                new InboxPreview(['email_address' => '1eaa9914-8730-45ca-9a01-5258a6148bc8@mailslurp.info'])
            ]
        ]);
    }

    private function createNewInbox()
    {
        return new InboxDto(['email_address' => '86d6942a-3485-45d3-95b6-d797a74eee3f@mailslurp.net']);
    }

    private function mockTockenStorage(\FunctionalTester $I, array $roles = [])
    {
        if (empty($roles)) {
            $roles = [ApiUser::ROLE_USER, ApiUser::ROLE_REWARD_AVAILABILITY];
        }
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, $roles)
        ]);
        $I->mockService('security.token_storage', $tokenStorage);
    }
}