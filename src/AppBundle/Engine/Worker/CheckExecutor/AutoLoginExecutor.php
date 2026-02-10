<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\AutoLogin;
use AppBundle\Document\BaseDocument;
use AppBundle\Extension\AutoLoginProcessor;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\AutoLoginRequest;
use AppBundle\Worker\SkipException;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;

class AutoLoginExecutor implements ExecutorInterface
{

    /** @var AutoLoginProcessor */
    private $processor;
    /** @var MQSender */
    private $mqSender;
    /** @var LoggerInterface */
    private $logger;
    /** @var DocumentManager */
    private $dm;
    /** @var Serializer */
    private $serializer;

    public function __construct(
        LoggerInterface $logger,
        AutoLoginProcessor $processor,
        MQSender $mqSender,
        DocumentManager $dm,
        Serializer $serializer,
        Loader $loader
    ) {
        $this->processor = $processor;
        $this->mqSender = $mqSender;
        $this->logger = $logger;
        $this->dm = $dm;
        $this->serializer = $serializer;
    }

    /**
     * @param AutoLogin $document
     */
    public function execute(BaseDocument $document): void
    {
        /** @var AutoLoginRequest $request */
        $request = $this->serializer->deserialize(json_encode($document->getRequest()), AutoLoginRequest::class,
            'json');
        $this->logger->pushProcessor(static function (array $record) use ($request, $document) {
            $record['extra']['partner'] = $document->getPartner();
            $record['extra']['provider'] = $request->getProvider();
            $record['extra']['tmpQueue'] = $document->getQueueName();
            $record['extra']['login'] = $request->getLogin();
            $record['extra']['userData'] = $request->getUserData();
            return $record;
        });

        if ($document->getRetries() > 1) {
            $this->logger->info('Process autologin breaks because of retries end');
            return;
        }

        $document->setRetries($document->getRetries() + 1);

        if ($document->getFirstCheckDate() === null) {
            $document->setFirstCheckDate(new \DateTime());
        }

        $this->dm->persist($document);
        $this->dm->flush($document);

        try {
            $response = $this->processor->processAutoLoginRequest(
                $request, $document->getId(), $document->getPartner()
            );
            $this->mqSender->pushDataToQueue($response, $document->getQueueName());
        } finally {
            $this->logger->info('Process autologin done');
            $this->logger->popProcessor();
        }
    }

    public function getMethodKey(): string
    {
        return AutoLogin::METHOD_KEY;
    }
}