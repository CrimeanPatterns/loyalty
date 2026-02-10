<?php
namespace Tests\Functional;

use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AccessChecker;
use Codeception\Util\Stub;
use Doctrine\DBAL\Connection;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class PasswordChangeCest
{
    const CHANGE_PASS_URL = '/v2/account/password/set';

    /** @var Connection */
    private $connection;

    private $tokenStorage;

    public function _before(\FunctionalTester $I)
    {
        $partner = 'test_' . bin2hex(random_bytes(5));
        $this->tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($partner, [ApiUser::ROLE_USER, ApiUser::ROLE_CHANGE_PASSWORD])
        ]);

        $this->connection = $I->grabService('database_connection');
        $this->connection->executeUpdate("UPDATE `Provider` SET CanChangePasswordServer = 1 WHERE `Code` = 'testprovider'");
    }

    private function buildRequest(){
        return (new ChangePasswordRequest())
                        ->setProvider('testprovider')
                        ->setPriority(7)
                        ->setLogin('SomeLogin')
                        ->setPassword('qqq')
                        ->setNewPassword('q1w2e3')
                        ->setUserId('SomeUserID');
    }

    //1.1. АПИ пользователь вызывает смену пароля при отсутствии прав
    public function testNoRights(\FunctionalTester $I)
    {
        $partner = 'test_' . bin2hex(random_bytes(5));
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($partner, [ApiUser::ROLE_USER])
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest();
        $I->sendPOST(self::CHANGE_PASS_URL, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => AccessChecker::getAccessDeniedMessage(ApiUser::ROLE_CHANGE_PASSWORD)]);
    }

    //1.2. АПИ пользователь вызывает смену пароля провайдера, который не реализует серверный метод смены пароля
    public function testUnavailableProvider(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorage);
        $this->connection->executeUpdate("UPDATE `Provider` SET CanChangePasswordServer = 0 WHERE `Code` = 'testprovider'");
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest();
        $I->sendPOST(self::CHANGE_PASS_URL, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_METHOD_NOT_ALLOWED);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'Changing passwords for this provider from AwardWallet servers is not supported at this time.']);
    }

    //1.3. В запросе отсутсвует или невалидное поле NewAccountPassword
    public function testInvalidNewPassword(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorage);
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest()->setNewPassword('  ');
        $I->sendPOST(self::CHANGE_PASS_URL, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_BAD_REQUEST);
        $I->seeResponseMatchesJsonType(['message' => 'string', 'logref' => 'string']);
        $I->seeResponseContainsJson(['message' => 'The specified parameter was rejected: the "newPassword" property is required for "'.$request->getProvider().'" provider']);
    }

    //1.4. Запрос на смену пароля успешно принят
    public function testSuccess(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorage);
        /** @var Serializer $serializer */
        $serializer = $I->grabService("jms_serializer");
        $request = $this->buildRequest();
        $I->sendPOST(self::CHANGE_PASS_URL, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseMatchesJsonType(['requestId' => 'string']);
    }

}