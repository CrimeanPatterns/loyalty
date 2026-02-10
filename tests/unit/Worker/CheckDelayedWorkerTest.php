<?php

namespace Tests\Unit\Worker;

use AppBundle\Document\CheckAccount;
use AppBundle\Worker\CheckDelayedWorker;
use Codeception\Module\Symfony;
use Codeception\Stub;
use Doctrine\ODM\MongoDB\DocumentManager;
use Helper\Aw;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class CheckDelayedWorkerTest extends \Codeception\TestCase\Test
{

    public function testCheckAccountDelayed()
    {
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
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

        $aw->mockService(AMQPChannel::class, Stub::makeEmpty(AMQPChannel::class, [
            'basic_publish' => Stub\Expected::once(function(AMQPMessage $message, string $routeKey, string $queue) {
                self::assertEquals(
                    serialize(['id' => '123', 'method' => CheckAccount::METHOD_KEY]),
                    $message->body
                );
                self::assertEquals(
                    "loyalty_check_account_awardwallet",
                    $queue
                );
            })
        ], $this));

        $worker = $symfony->grabService(CheckDelayedWorker::class);

        $worker->execute(new AMQPMessage(json_encode(['id' => '123', 'method' => CheckAccount::METHOD_KEY, 'partner' => 'awardwallet', 'priority' => 1])));
    }

}