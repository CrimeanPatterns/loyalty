<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 23/12/2016
 * Time: 15:17
 */

namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\CallbackPackageProcessor;
use AppBundle\Extension\MongoCommunicator;
use AppBundle\Extension\MQMessages\CallbackRequest;
use AppBundle\Extension\TimeCommunicator;
use Codeception\Exception\TestRuntimeException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

/**
 * @backupGlobals disabled
 */
class CallbackPackageProcessorTest extends BaseTestClass
{

    const DEFAULT_PACKET_PRIORITY = 5;
    const DEFAULT_PACKET_DELAY = 10;

    public function testFullSendInFirstIteration()
    {
        $numPartners = 3;
        $mongoData = $this->generateMongoRows($numPartners, [20, 20, 20]);

        $logger = $this->getCustomMock(Logger::class);
        $callbackProducer = $this->getCustomMock(Producer::class);
        $mongoCommunicator = $this->getCustomMock(MongoCommunicator::class);
        $time = $this->getCustomMock(TimeCommunicator::class);

        $mongoCommunicator->expects($this->once())->method('getPackageCallbacks')->with([])->willReturn($mongoData);
        $callbackProducer->expects($this->exactly($numPartners))->method('publish'); // TODO: допилить with()
        $mongoCommunicator->expects($this->exactly($numPartners))->method('updatePackageCallbacksIsSent'); // TODO: допилить with()

        $processor = new CallbackPackageProcessor($logger, $this->getConnectionMock($numPartners), $mongoCommunicator, $callbackProducer, $this->createMemcachedMock(), $time, 1);
        $processor->run();
    }

    public function testSplitByCallbackUrl()
    {
        $mongoData = array_merge(
            $this->generateMongoRows(1, [5], 'http://some/url/1'),
            $this->generateMongoRows(1, [15], 'http://some/url/2')
        );
        shuffle($mongoData);

        $logger = $this->getCustomMock(Logger::class);
        $callbackProducer = $this->getCustomMock(Producer::class);
        $mongoCommunicator = $this->getCustomMock(MongoCommunicator::class);
        $time = $this->getCustomMock(TimeCommunicator::class);

        $mongoCommunicator->expects($this->once())->method('getPackageCallbacks')->with([])->willReturn($mongoData);
        $publishCallNumber = 0;
        $callbackProducer
            ->method('publish')
            ->willReturnCallback(function(string $msgBody) use(&$publishCallNumber) {
                /** @var CallbackRequest $message */
                $message = \unserialize($msgBody);
                if ($publishCallNumber === 0) {
                    $this->assertCount(5, $message->getIds());
                    foreach ($message->getIds() as $id) {
                        $this->assertStringStartsWith('http://some/url/1|', $id);
                    }
                }
                elseif ($publishCallNumber == 1) {
                    $this->assertCount(15, $message->getIds());
                    foreach ($message->getIds() as $id) {
                        $this->assertStringStartsWith('http://some/url/2|', $id);
                    }
                }
                $publishCallNumber++;
            })
        ;
        $mongoCommunicator->expects($this->exactly(2))->method('updatePackageCallbacksIsSent'); // TODO: допилить with()

        $processor = new CallbackPackageProcessor($logger, $this->getConnectionMock(1), $mongoCommunicator, $callbackProducer, $this->createMemcachedMock(), $time, 1);
        $processor->run();
    }

    private function getConnectionMock($numPartners)
    {
        $stmtMock = $this->getCustomMock(Statement::class);
        $stmtMock->expects($this->exactly($numPartners))
                 ->method('fetch')
                 ->willReturn([
                     'PacketPriority' => self::DEFAULT_PACKET_PRIORITY,
                     'PacketDelay' => self::DEFAULT_PACKET_DELAY
                 ]);

        $connection = $this->getCustomMock(Connection::class);
        $connection->expects($this->any())
                   ->method('executeQuery')
                   ->willReturn($stmtMock);

        return $connection;
    }

    /**
     * @param int $numPartner
     * @param array $numPartnerRows
     * @return array
     */
    private function generateMongoRows($numPartner, array $numPartnerRows, string $callbackUrl = 'http://some.url')
    {
        if (count($numPartnerRows) !== $numPartner) {
            throw new TestRuntimeException('NumPartnerRows array count needs to be equal NumPartner variable');
        }

        $mongoData = [];
        for ($j=0; $j<$numPartner; $j++) {
            for ($i=0; $i<$numPartnerRows[$j]; $i++) {
                $id = $callbackUrl . '|' . $i . $j;
                $mongoData[$id] = (new CheckAccount())->setPartner('Partner'.$j)->setId($id)->setRequest(['callbackUrl' => $callbackUrl]);
            }
        }

        return $mongoData;
    }

    private function createMemcachedMock()
    {
        $memCas = 'some_cas_'.time();
        $memVal = gethostname().'_'.getmypid();
        $memcached = $this->getCustomMock(\Memcached::class);
        $memcached->expects($this->once())
                  ->method('get')
                  ->with(CallbackPackageProcessor::PROCESSOR_KEY_NAME, null, \Memcached::GET_EXTENDED)
                  ->willReturn(["cas" => $memCas, "value" =>$memVal]);
        $memcached->expects($this->once())
                  ->method('cas')
                  ->willReturn(true);
        return $memcached;
    }

}