<?php
namespace Tests\Functional;

use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AccessChecker;
use Codeception\Util\Stub;
use Helper\Aw;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class CheckAccountPermissionsCest
{
    protected $urlCheckAccount = "/account/check";
    protected $partner;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
    }

    private function buildRequest(){
        return (new CheckAccountRequest())
                        ->setProvider('testprovider')
                        ->setPriority(7)
                        ->setLogin('SomeLogin')
                        ->setPassword('qqq')
                        ->setUserId('SomeUserID');
    }

    public function testNoAccountInfoRole(\FunctionalTester $I)
    {
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER])
        ]);

        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest();
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => AccessChecker::getAccessDeniedMessage(ApiUser::ROLE_ACCOUNT_INFO)]);
    }

    public function testNoReservationsInfoRole(\FunctionalTester $I)
    {
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest()->setParseitineraries(true);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => AccessChecker::getAccessDeniedMessage(ApiUser::ROLE_RESERVATIONS_INFO)]);
    }

    public function testNoAccountHistoryRole(\FunctionalTester $I)
    {
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest()->setHistory((new RequestItemHistory())->setRange(History::HISTORY_COMPLETE));
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => AccessChecker::getAccessDeniedMessage(ApiUser::ROLE_ACCOUNT_HISTORY)]);
    }

    public function testAllRoles(\FunctionalTester $I)
    {
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO, ApiUser::ROLE_RESERVATIONS_INFO, ApiUser::ROLE_ACCOUNT_HISTORY])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest()->setHistory((new RequestItemHistory())->setRange(History::HISTORY_COMPLETE))->setParseitineraries(true);
        $I->sendPOST($this->urlCheckAccount, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
    }

}