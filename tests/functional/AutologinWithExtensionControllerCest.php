<?php

namespace Tests\Functional;


use AppBundle\Controller\AutoLoginController;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\AutoLoginRequest;
use AppBundle\Model\Resources\AutoLoginResponse;
use AppBundle\Model\Resources\AutologinWithExtensionRequest;
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

class AutologinWithExtensionControllerCest
{
    private const ROUTE = '/v2/account/autologin-with-extension';

    protected $partner;

    public function _before(\FunctionalTester $I)
    {
        $I->createPartnerAndApiKey(['CanDebug' => 1]);
    }

    public function testSuccess(\FunctionalTester $I)
    {
        $providerCode = "p" . bin2hex(random_bytes(5));
        $I->createAwProvider(null, $providerCode, ['AutologinV3' => 1]);
        $request = (new AutoLoginWithExtensionRequest())
            ->setProvider($providerCode)
            ->setLogin("somelogin")
            ->setPassword('somepass')
            ->setUserId('userId')
            ->setUserData('userData')
        ;
        /** @var SerializerInterface $serializer */
        $serializer = $I->grabService(SerializerInterface::class);
        $I->sendPost(self::ROUTE, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIsSuccessful();
    }

    public function testDisabled(\FunctionalTester $I)
    {
        $providerCode = "p" . bin2hex(random_bytes(5));
        $I->createAwProvider(null, $providerCode, ['AutologinV3' => 0]);
        $request = (new AutoLoginWithExtensionRequest())
            ->setProvider($providerCode)
            ->setLogin("somelogin")
            ->setPassword('somepass')
            ->setUserId('userId')
            ->setUserData('userData')
        ;
        /** @var SerializerInterface $serializer */
        $serializer = $I->grabService(SerializerInterface::class);
        $I->sendPost(self::ROUTE, $serializer->serialize($request, 'json'));
        $I->seeResponseCodeIs(400);
    }

}