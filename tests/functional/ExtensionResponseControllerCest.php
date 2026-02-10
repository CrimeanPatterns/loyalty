<?php

namespace Tests\Functional;

use AppBundle\Security\ApiUser;
use Codeception\Util\Stub;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ExtensionResponseControllerCest
{
    private string $userData;
    private string $partner;
    private TokenStorage $tokenStorage;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));
        $this->tokenStorage = $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO])
        ]);
    }

    public function testAccessDenied(\FunctionalTester $I)
    {
        $I->sendPOST('/v2/extension/response', '{}');
        $I->seeResponseCodeIs(401);
    }

    public function testUnknownSession(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorage);
        $I->sendPOST('/v2/extension/response', '{"sessionId": "123", "result": "ok", "requestId": "456"}');
        $I->seeResponseCodeIs(200);
    }
}