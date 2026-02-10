<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RegisterConfig;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterConfigRequest;
use AppBundle\Security\ApiUser;
use Codeception\Util\Stub;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class RegisterConfigControllerCest
{
    private const REGISTER_ACCOUNT_CONFIG_LIST = '/ra-register-config/list';
    private const REGISTER_ACCOUNT_CONFIG_CREATE = '/ra-register-config/create';
    private const REGISTER_ACCOUNT_CONFIG_EDIT = '/ra-register-config/%s/edit';
    private const REGISTER_ACCOUNT_CONFIG_DELETE = '/ra-register-config/%s/delete';

    protected $userData;
    protected $partner;
    protected $serializer;
    protected $tokenStorageOk;
    protected $tokenStorageForbidden;
    protected DocumentManager $dm;

    public function _before(\FunctionalTester $I)
    {
        $this->partner = 'test_' . bin2hex(random_bytes(5));
        $this->userData = 'userdata_' . bin2hex(random_bytes(5));

        $this->tokenStorageOk = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner,
                [ApiUser::ROLE_USER, ApiUser::ROLE_ADMIN])
        ]);

        $this->tokenStorageForbidden = Stub::make(TokenStorage::class, [
            'getToken' => $I->createApiRoleUserToken($this->partner,
                [ApiUser::ROLE_USER, ApiUser::ROLE_REWARD_AVAILABILITY])
        ]);

        $this->serializer = $I->grabService("jms_serializer");
        $this->dm = $I->grabService(DocumentManager::class);
    }

    public function _after()
    {
        $this->dm->createQueryBuilder(RegisterConfig::class)
            ->remove(RegisterConfig::class)
            ->getQuery()
            ->execute();
    }

    public function testListConfigs(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorageOk);
        $this->createConfig();
        $I->sendGET(self::REGISTER_ACCOUNT_CONFIG_LIST);
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);

        $I->sendGET(self::REGISTER_ACCOUNT_CONFIG_LIST);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $response = json_decode($I->grabResponse());
        $I->assertEquals(1, count($response));
    }

    public function testCreateConfig(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorageOk);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);

        $configs = $this->dm->getRepository(RegisterConfig::class)
            ->findAll();
        $I->assertEquals(0, count($configs));

        $request = $this->buildRequest();
        $I->sendPOST(self::REGISTER_ACCOUNT_CONFIG_CREATE, $this->serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_CREATED);

        $configs = $this->dm->getRepository(RegisterConfig::class)
            ->findAll();
        $I->assertEquals(1, count($configs));
        $I->assertEquals($configs[0]->getProvider(), $request->getProvider());
        $I->assertEquals($configs[0]->getDefaultEmail(), $request->getDefaultEmail());
        $I->assertEquals($configs[0]->getRuleForEmail(), $request->getRuleForEmail());
        $I->assertEquals($configs[0]->getMinCountEnabled(), $request->getMinCountEnabled());
        $I->assertEquals($configs[0]->getMinCountReserved(), $request->getMinCountReserved());
        $I->assertEquals($configs[0]->getDelay(), $request->getDelay());
    }

    public function testUpdateConfig(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorageOk);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);
        $config = $this->createConfig();

        $request = $this->buildRequest();
        $I->sendPOST(sprintf(self::REGISTER_ACCOUNT_CONFIG_EDIT, $config->getId()), $this->serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_OK);

        $this->dm->refresh($config);
        $I->assertEquals($config->getProvider(), $request->getProvider());
        $I->assertEquals($config->getDefaultEmail(), $request->getDefaultEmail());
        $I->assertEquals($config->getRuleForEmail(), $request->getRuleForEmail());
        $I->assertEquals($config->getMinCountEnabled(), $request->getMinCountEnabled());
        $I->assertEquals($config->getMinCountReserved(), $request->getMinCountReserved());
        $I->assertEquals($config->getDelay(), $request->getDelay());
        $I->assertEquals($config->getIsActive(), $request->getIsActive());
        $I->assertEquals($config->getIs2Fa(), $request->getIs2Fa());
    }

    public function testDeleteConfig(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorageOk);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);
        $config = $this->createConfig();

        $I->sendPOST(sprintf(self::REGISTER_ACCOUNT_CONFIG_DELETE, $config->getId()), []);
        $I->seeResponseCodeIs(Response::HTTP_OK);

        $config = $this->dm->getRepository(RegisterConfig::class)
            ->find($config->getId());
        $I->assertNotTrue($config);
    }

    public function testForbiddenConfig(\FunctionalTester $I)
    {
        $I->mockService('security.token_storage', $this->tokenStorageForbidden);
        $host = $I->grabParameter('reward_availability_host');
        $I->setHost($host);
        $config = $this->createConfig();

        $I->sendGET(self::REGISTER_ACCOUNT_CONFIG_LIST);
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        $request = $this->buildRequest();
        $I->sendPOST(self::REGISTER_ACCOUNT_CONFIG_CREATE, $this->serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        $I->sendPOST(sprintf(self::REGISTER_ACCOUNT_CONFIG_EDIT, $config->getId()), $this->serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        $I->sendPOST(sprintf(self::REGISTER_ACCOUNT_CONFIG_DELETE, $config->getId()), []);
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
    }

    private function buildRequest()
    {
        return (new RegisterConfigRequest())
            ->setProvider('british')
            ->setDefaultEmail('testemail@mail.com')
            ->setRuleForEmail('dots')
            ->setMinCountEnabled(1)
            ->setMinCountReserved(1)
            ->setDelay(0)
            ->setIsActive(true)
            ->setIs2Fa(false);
    }

    private function createConfig() {
        $regConfig = new RegisterConfig(
            'british',
            'newemail@mail.com',
            'plus',
            5,
            5,
            0,
            false,
            true
        );
        $this->dm->persist($regConfig);
        $this->dm->flush($regConfig);

        return $regConfig;
    }
}