<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 27/12/2017
 * Time: 17:09
 */

namespace AppBundle\Listener;

use AppBundle\Document\BaseDocument;
use AppBundle\Event\WatchdogKillProcessEvent;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\TimeCommunicator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;

class CheckWorkerKillListener
{

    /** @var LoggerInterface */
    private $logger;
    /** @var DocumentManager */
    private $dm;
    /** @var TimeCommunicator */
    private $timeCommunicator;
    /**
     * @var S3Custom
     */
    private $s3;

    public function __construct(LoggerInterface $logger, DocumentManager $dm, TimeCommunicator $timeCommunicator, S3Custom $s3)
    {
        $this->logger = $logger;
        $this->dm = $dm;
        $this->timeCommunicator = $timeCommunicator;
        $this->s3 = $s3;
    }

    public function onWatchdogKillProcess(WatchdogKillProcessEvent $event)
    {
        $context = $event->getContext();
        if(empty($context['logContext']['requestId']) || !class_exists($context['logContext']['document'])){
            $this->ignoreEvent($event);
            return;
        }

        $repo = $this->dm->getRepository($context['logContext']['document']);
        /** @var BaseDocument $row */
        $row = $repo->find($context['logContext']['requestId']);
        $row->setParsingTime(intval($row->getParsingTime()) + $this->timeCommunicator->getCurrentTime() - $event->getStartTime())
            ->incKilledCounter()
            ->setKilled();

        $this->dm->persist($row);
        $this->dm->flush();

        if(isset($context['logDir']) && file_exists($context['logDir'])) {
            file_put_contents($context['logDir'] . "/log.html", "<div style='color: red;'>Killed by watchdog on ".date("Y-m-d H:i:s").", after " . (time() - $event->getStartTime()) . " seconds</div><pre>" . json_encode($context, JSON_PRETTY_PRINT) . "</pre>", FILE_APPEND);
            $this->s3->uploadCheckerLogToBucket($context['logContext']['requestId'], $context['logDir'],
                $context['accountFields'], $repo);
        }
    }

    private function ignoreEvent(WatchdogKillProcessEvent $event)
    {
        $this->logger->notice(get_class($event).' Ignored by '.self::class, $event->getContext()['logContext']);
    }
}