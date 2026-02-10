<?php 
namespace Tests\Functional;

use AppBundle\Model\Resources\PasswordRequest;
use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;
use Codeception\Util\Stub;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class PasswordRequestCest
{
    const PARTNER_LOGIN = 'test';
    const PARTNER_PASS = 'test';
    const PASSWORD_REQUEST_URL = '/admin-aw/password-request';
    const PASSWORD_REQUEST_LIST_URL = '/admin-aw/password-request/list';

    /** @var PasswordRequest */
    private $request;

    public function _before(\FunctionalTester $I)
    {
        $this->request = (new PasswordRequest())
                            ->setProvider('testprovider')
                            ->setUserId('SomeUserID');
    }

    public function testForbidden(\FunctionalTester $I)
    {
        $token = new ApiToken(
            new ApiUser(self::PARTNER_LOGIN, self::PARTNER_PASS, 1, 1, [ApiUser::ROLE_USER]),
            self::PARTNER_LOGIN . ':' . self::PARTNER_PASS,
            '',
            [ApiUser::ROLE_USER]
        );
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $token
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $I->sendPOST(self::PASSWORD_REQUEST_URL, $serializer->serialize($this->request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->seeResponseIsJson();
    }

    public function testEmptyPartner(\FunctionalTester $I)
    {
        $token = new ApiToken(
            new ApiUser(self::PARTNER_LOGIN, self::PARTNER_PASS, 1, 1, [ApiUser::ROLE_USER, ApiUser::ROLE_ADMIN]),
            self::PARTNER_LOGIN . ':' . self::PARTNER_PASS,
            '',
            [ApiUser::ROLE_USER, ApiUser::ROLE_ADMIN    ]
        );
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $token
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $I->sendPOST(self::PASSWORD_REQUEST_URL, $serializer->serialize($this->request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseIsJson();
    }

    public function testAvailableList(\FunctionalTester $I)
    {
        $token = new ApiToken(
            new ApiUser(self::PARTNER_LOGIN, self::PARTNER_PASS, 1, 1, [ApiUser::ROLE_USER, ApiUser::ROLE_ADMIN]),
            self::PARTNER_LOGIN . ':' . self::PARTNER_PASS,
            '',
            [ApiUser::ROLE_USER, ApiUser::ROLE_ADMIN    ]
        );
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $token
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        $I->sendGET(self::PASSWORD_REQUEST_LIST_URL);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseIsJson();
    }

}