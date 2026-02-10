<?php


namespace AppBundle\Controller\Common;


use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CheckItemCreatorService
{

    /** @var LoggerInterface */
    private $logger;

    /** @var MongoRowService */
    private $mongoRowService;

    /** @var RabbitMessageCreator */
    private $rabbitMessageCreator;

    /** @var RequestStack */
    private $requestStack;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    public function __construct(
        LoggerInterface $logger,
        MongoRowService $mongoRowService,
        RabbitMessageCreator $rabbitMessageCreator,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->mongoRowService = $mongoRowService;
        $this->rabbitMessageCreator = $rabbitMessageCreator;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function createCheckItem(LoyaltyRequestInterface $request, int $apiVersion, string $rowClass, bool $sendToQueue = true, ?int $messageExpirationSeconds = null): string
    {
        $startTime = time();
        $row = $this->mongoRowService->createRow($request, $apiVersion, $rowClass);
        $mongoTime = time() - $startTime;

        $requestIp = $this->requestStack->getCurrentRequest()->getClientIp();
        $token = $this->tokenStorage->getToken()->getCredentials();

        if ($sendToQueue) {
            //to rabbit
            try {
                $startTime = time();
                $delay = 0;

                if ($request instanceof RegisterAccountRequest) {
                    $registerNotEarlierDate = $request->getRegisterNotEarlierDate();

                    if ($registerNotEarlierDate) {
                        $delay = ($registerNotEarlierDate->getTimestamp() > $startTime)
                            ? $registerNotEarlierDate->getTimestamp() - $startTime
                            : 0;
                    }
                }

                $this->rabbitMessageCreator->createRabbitMessage($row->getId(), $request->getPriority(), $rowClass::METHOD_KEY, $delay, $messageExpirationSeconds);
                $this->logger->debug('Push request to queue time', [
                    'mongoProcessedTime' => $mongoTime,
                    'rabbitProcessedTime' => time() - $startTime,
                    'requestId' => $row->getId(),
                ]);

                $row->setQueuedate(new \DateTime());
                $this->mongoRowService->updateRow($row);
            } catch (\ErrorException $e) {
                $this->logger->critical(
                    'Can not write message to rabbitMQ',
                    ['requestId' => $row->getId(), 'accountId' => $request->getUserData()]
                );
            }
        }

        $this->logger->info(
            'accepted to check',
            array_merge(
                [
                    'requestId' => $row->getId(),
                    'accountId' => $request->getUserData(),
                    'provider' => $request->getProvider(),
                    'partner' => $row->getPartner(),
                    'priority' => $request->getPriority(),
                    'ip' => $requestIp,
                    'apiKey' => sprintf('%s....%s', substr($token, 0,3), substr($token, -3)),
                ],
                $request->getLogContext()
            )
        );

        return $row->getId();
    }

}