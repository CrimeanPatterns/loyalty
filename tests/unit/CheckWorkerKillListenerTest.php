<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Event\WatchdogKillProcessEvent;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Listener\CheckWorkerKillListener;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Psr\Log\LoggerInterface;

/**
 * @backupGlobals disabled
 */
class CheckWorkerKillListenerTest extends BaseTestClass
{

    public function testSuccess()
    {
        $logger = $this->getCustomMock(LoggerInterface::class);
        $repo = $this->getCustomMock(DocumentRepository::class);
        $dm = $this->getCustomMock(DocumentManager::class);
        $tc = $this->getCustomMock(TimeCommunicator::class);

        $prevParsingTime = 5;
        $startTime = time() - 10;
        $killTime = time();
        $requestId = 'someRequestId';
        $row = (new CheckAccount())->setParsingTime($prevParsingTime);

        $tc->expects($this->once())
           ->method('getCurrentTime')
           ->willReturn($killTime);
        $repo->expects($this->once())
             ->method('find')
             ->with($requestId)
             ->willReturn($row);
        $dm->expects($this->once())
           ->method('getRepository')
           ->with(get_class($row))
           ->willReturn($repo);
        $dm->expects($this->once())
           ->method('persist')
           ->with($row);
        $dm->expects($this->once())
           ->method('flush');

        $logDir = sys_get_temp_dir() . "/" . bin2hex(random_bytes(10));
        mkdir($logDir);
        $accountFields = ['a' => 1, 'b' => 2];
        $s3 = $this->getCustomMock(S3Custom::class);
        $s3->expects($this->once())
            ->method('uploadCheckerLogToBucket')
            ->with($requestId, $logDir, $accountFields, $repo);

        $listener = new CheckWorkerKillListener($logger, $dm, $tc, $s3);
        $event = new WatchdogKillProcessEvent($startTime, ['logContext' => ['requestId' => $requestId, 'document' => get_class($row)], 'logDir' => $logDir, 'accountFields' => $accountFields]);
        $listener->onWatchdogKillProcess($event);

        $this->assertFileExists($logDir . "/log.html");
        $this->assertStringContainsString("Killed by watchdog", file_get_contents($logDir . "/log.html"));
        unlink($logDir . "/log.html");
        rmdir($logDir);

        $this->assertEquals($prevParsingTime + $killTime - $startTime, (int)$row->getParsingTime());
        $this->assertEquals(true, $row->isKilled());
    }

    public function testIgnore()
    {
        $logger = $this->getCustomMock(LoggerInterface::class);
        $dm = $this->getCustomMock(DocumentManager::class);
        $tc = $this->getCustomMock(TimeCommunicator::class);

        $context = ['data1' => 'data1', 'data2' => 132, 'logContext' => ['log1' => 'test']];
        $event = new WatchdogKillProcessEvent(time(), $context);
        $logger->expects($this->once())
               ->method('notice')
               ->with(get_class($event).' Ignored by '.CheckWorkerKillListener::class, ['log1' => 'test']);

        $s3 = $this->getCustomMock(S3Custom::class);

        $listener = new CheckWorkerKillListener($logger, $dm, $tc, $s3);
        $listener->onWatchdogKillProcess($event);
    }

}