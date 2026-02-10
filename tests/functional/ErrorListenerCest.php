<?php

namespace Tests\Functional;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\SwaggerErrorListener;
use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;
use Codeception\Stub\Expected;
use Codeception\Util\Stub;
use Doctrine\ODM\MongoDB\DocumentRepository;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Monolog\Logger;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Debug\Exception\UndefinedMethodException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @backupGlobals disabled
 */
class ErrorListenerCest
{
    /** @var CheckAccount */
    private $row;

    const PARTNER_LOGIN = 'test';
    const PARTNER_PASS = 'test';

    public function _before(\FunctionalTester $I)
    {
        $token = new ApiToken(
            new ApiUser(self::PARTNER_LOGIN, self::PARTNER_PASS, 1, 1, [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO]),
            self::PARTNER_LOGIN . ':' . self::PARTNER_PASS,
            '',
            [ApiUser::ROLE_USER, ApiUser::ROLE_ACCOUNT_INFO]
        );
        $tokenStorage = Stub::make(TokenStorage::class, [
            'getToken' => $token
        ]);
        $I->mockService('security.token_storage', $tokenStorage);

        $this->row = (new CheckAccount())
                        ->setId(bin2hex(random_bytes(10)))
                        ->setPartner(self::PARTNER_LOGIN)
                        ->setApiVersion(1)
                        ->setResponse(['state' => 1]);
    }

    public function test404WebErrorOnlyNotice(\FunctionalTester $I)
    {
        $repo = Stub::make(DocumentRepository::class, [
            'find' => Expected::once(function ($id) use ($I) {
                $I->assertEquals($this->row->getId(), $id);
                return null;
            })
        ]);
        $I->mockService('aw.mongo.repo.account', $repo);

        $result = ['message' => 'Not Found'];
        $serializerErrorLitener = Stub::makeEmpty(SerializerInterface::class, [
            'serialize' => Expected::once(function($data, $format) use($I, $result){
                $I->assertEquals("json", $format);
                $I->assertEquals($result['message'], $data->getMessage());
                return json_encode($result);
            })
        ]);
        $logger = Stub::make(Logger::class, [
            'notice' => Expected::once(function($message, $context) use($I){
                $I->assertTrue(is_string($message));
                $I->assertTrue(is_array($context));
                $I->assertArrayHasKey('logref', $context);
            })
        ]);

        $errorListener = new SwaggerErrorListener($logger, $serializerErrorLitener);
        $I->mockService('kernel.listener.swagger.vnd_error_exception', $errorListener);

        $I->sendGET('/account/check/' . $this->row->getId());
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $I->seeResponseContainsJson($result);
    }

    public function test500WebErrorEmailReport(\FunctionalTester $I)
    {
        $repo = Stub::make(DocumentRepository::class, [
            'find' => Expected::once(function ($id) use ($I) {
                $I->assertEquals($this->row->getId(), $id);
                return $this->row;
            })
        ]);
        $I->mockService('aw.mongo.repo.account', $repo);

        $serializerController = Stub::makeEmpty(SerializerInterface::class, [
            'deserialize' => Expected::once(function($data, $type, $format) use($I){
                $I->assertEquals($data, json_decode($this->row->getResponse()));
                throw new FatalThrowableError(new UndefinedMethodException('UndefinedMethod', new \ErrorException()));
            })
        ]);
        $I->mockService(Serializer::class, $serializerController);

        $result = ['message' => 'Internal Server Error'];
        $serializerErrorListener = Stub::makeEmpty(SerializerInterface::class, [
            'serialize' => Expected::once(function($data, $format) use($I, $result){
                $I->assertEquals("json", $format);
                $I->assertEquals($result['message'], $data->getMessage());
                return json_encode($result);
            })
        ]);
        $logger = Stub::make(Logger::class, [
            'critical' => Expected::once(function($message, $context) use($I){
                $I->assertTrue(is_string($message));
                $I->assertTrue(is_array($context));
                $I->assertArrayHasKey('logref', $context);
            })
        ]);

        $errorListener = new SwaggerErrorListener($logger, $serializerErrorListener);
        $I->mockService('kernel.listener.swagger.vnd_error_exception', $errorListener);

        $I->sendGET('/account/check/' . $this->row->getId());
        $I->seeResponseCodeIs(Response::HTTP_INTERNAL_SERVER_ERROR);
        $I->seeResponseMatchesJsonType(['message' => 'string']);
        $I->seeResponseContainsJson($result);
    }

}