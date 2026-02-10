<?php

namespace Tests\Unit\Worker;

use AppBundle\Document\AutoLogin;
use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\V2\CheckAccountResponse;
use AppBundle\Service\EngineStatus;
use AppBundle\Worker\CheckExecutor\CheckAccountExtensionExecutor;
use AppBundle\Worker\CheckExecutor\ExecutorInterface;
use AppBundle\Worker\CheckWorker;
use AwardWallet\ExtensionWorker\ParserFactory;
use Codeception\Module\Symfony;
use Codeception\Stub;
use Doctrine\ODM\MongoDB\DocumentManager;
use Helper\Aw;
use Monolog\Handler\TestHandler;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Logger;

class CheckWorkerTest extends \Codeception\TestCase\Test
{

    public function testNoExecutor()
    {
        $handler = new TestHandler();
        $worker = new CheckWorker(
            new Logger('app', [$handler]),
            Stub::makeEmpty(EngineStatus::class, ['isFresh' => true], $this),
            Stub::makeEmpty(DocumentManager::class),
            1000000000
        );
        $executorStub = Stub::makeEmpty(ExecutorInterface::class, ['getMethodKey' => 'one', 'execute' => Stub\Expected::never()], $this);
        $worker->addExecutor($executorStub);

        $worker->execute(new AMQPMessage(serialize(['id' => '123', 'method' => 'two'])));
        self::assertTrue($handler->hasNoticeThatContains('skip check message'));
        self::assertTrue($handler->hasCriticalThatContains('No executor for method two'));
    }

    public function testUnmappedMethod()
    {
        $handler = new TestHandler();
        $worker = new CheckWorker(
            new Logger('app', [$handler]),
            Stub::makeEmpty(EngineStatus::class, ['isFresh' => true], $this),
            Stub::makeEmpty(DocumentManager::class),
            1000000000
        );
        $executorStub = Stub::makeEmpty(ExecutorInterface::class, ['getMethodKey' => 'one', 'execute' => Stub\Expected::never()], $this);
        $worker->addExecutor($executorStub);

        $worker->execute(new AMQPMessage(serialize(['id' => '123', 'method' => 'one'])));
        self::assertTrue($handler->hasNoticeThatContains('skip check message'));
        self::assertTrue($handler->hasNoticeThatContains('Class not found for Method key: one'));
    }

    public function testValidExecutor()
    {
        $handler = new TestHandler();
        $worker = new CheckWorker(
            new Logger('app', [$handler]),
            Stub::makeEmpty(EngineStatus::class, ['isFresh' => true]),
            Stub::makeEmpty(DocumentManager::class, [
                'find' => Stub\Expected::once(function(string $class, string $id) {
                    self::assertEquals(CheckAccount::class, $class);
                    self::assertEquals('123', $id);

                    $result = new CheckAccount();
                    $result->setId('123');

                    return $result;
                })
            ], $this),
            1000000000
        );
        $worker->addExecutor(Stub::makeEmpty(ExecutorInterface::class, ['getMethodKey' => CheckAccount::METHOD_KEY, 'execute' => Stub\Expected::once(function(BaseDocument $row) {
            self::assertEquals('123', $row->getId());
        })], $this));

        $worker->execute(new AMQPMessage(serialize(['id' => '123', 'method' => CheckAccount::METHOD_KEY])));
        self::assertFalse($handler->hasNoticeThatContains('skip check message'));
        self::assertFalse($handler->hasCriticalThatContains('No executor for method one'));
    }

    public function testExtensionExecutor()
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $dm */
        $dm = $symfony->grabService("doctrine_mongodb.odm.default_document_manager");

//        $aw->mockService(ParserFactory::class, Stub::makeEmpty(ParserFactory::class, [
//            'getParser' => Stub\Expected::once()
//        ], $this));

        $row = $aw->createCheckAccountRow();
        $row->setRequest(array_merge($row->getRequest(), ['browserExtensionAllowed' => true, 'browserExtensionSessionId' => '123']));
        $dm->flush();
        $worker = $symfony->grabService(CheckWorker::class);

        self::assertNull($row->getFirstCheckDate());
        $worker->execute(new AMQPMessage(serialize(['id' => $row->getId(), 'method' => CheckAccount::METHOD_KEY])));
        $dm->refresh($row);
        self::assertNotNull($row->getFirstCheckDate());
        self::assertEquals(ACCOUNT_ENGINE_ERROR, $row->getResponse()['state']);
        self::assertEquals('Parser not found', $row->getResponse()['errorReason']);
    }

    public function testCheckAccountDelayed()
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $row = $aw->createCheckAccountRow();
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $dm */
        $dm = $symfony->grabService("doctrine_mongodb.odm.default_document_manager");

        $providerCode = "p" . bin2hex(random_bytes(8));
        $aw->createAwProvider(null, $providerCode, [], [
            'Login' => function() {
                throw new \ThrottledException(60);
            }
        ]);
        $row->setRequest(array_merge($row->getRequest(), ['provider' => $providerCode]));
        $dm->flush();

        // mocking Producer instead of ProducerInterface because OldSoundRabbitMqBundle::shutdown will call Producer::close()
        $aw->mockService("old_sound_rabbit_mq.check_delayed_producer", Stub::makeEmpty(Producer::class, [
            'publish' => Stub\Expected::once(function(string $messageBody) use ($row) {
                self::assertEquals(
                    json_encode(['id' => $row->getId(), 'method' => CheckAccount::METHOD_KEY, 'partner' => 'awardwallet', 'priority' => 1]),
                    $messageBody
                );
            }),
        ], $this));

        $worker = $symfony->grabService(CheckWorker::class);

        self::assertNull($row->getFirstCheckDate());
        $worker->execute(new AMQPMessage(serialize(['id' => $row->getId(), 'method' => CheckAccount::METHOD_KEY])));
        $dm->refresh($row);
        self::assertNotNull($row->getFirstCheckDate());
        self::assertEquals(ACCOUNT_UNCHECKED, $row->getResponse()['state']);
    }

    public function testAutoLoginExecutor()
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $row = $aw->createAutoLoginRow();
        /** @var DocumentManager $dm */
        $dm = $symfony->grabService("doctrine_mongodb.odm.default_document_manager");

        /** @var CheckWorker $worker */
        $worker = $symfony->grabService(CheckWorker::class);

        $worker->execute(new AMQPMessage(serialize(['id' => $row->getId(), 'method' => AutoLogin::METHOD_KEY])));
        $dm->refresh($row);
        self::assertNotNull($row->getFirstCheckDate());
        self::assertEquals(ACCOUNT_UNCHECKED, $row->getResponse()['state']);
    }

}