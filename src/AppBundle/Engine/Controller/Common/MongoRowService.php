<?php


namespace AppBundle\Controller\Common;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\EmbeddedDocumentsInterface;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\AutologinWithExtensionRequest;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\UserData;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MongoRowService
{

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var DocumentManager */
    private $manager;

    /** @var SerializerInterface */
    private $serializer;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        TokenStorageInterface $tokenStorage,
        DocumentManager $manager,
        SerializerInterface $serializer
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->manager = $manager;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public function createRow(LoyaltyRequestInterface $request, int $apiVersion, string $rowClass): BaseDocument
    {
        // for debug
        if ($request instanceof RewardAvailabilityRequest) {
            $this->logger->info("createRow RewardAvailabilityRequest: " .
                $this->serializer->serialize($request, 'json'));
        }
        $noSerializer = (
            $request instanceof RewardAvailabilityRequest
            || $request instanceof RaHotelRequest
            || $request instanceof RegisterAccountRequest
            || is_subclass_of($rowClass, EmbeddedDocumentsInterface::class)
        );

        /** @var BaseDocument $row */
        $row = new $rowClass();
        $row->setApiVersion($apiVersion)
            ->setPartner($this->tokenStorage->getToken()->getUser()->getUsername())
            ->setReviewed(false)
            ->setRetries(-1)
            ->setMethod($rowClass::METHOD_KEY)
            ->setUpdatedate(new \DateTime())
            ->setRequest($noSerializer ? $request : $this->serializer->toArray($request));

        $this->manager->persist($row);

        switch (true) {
            case $request instanceof RewardAvailabilityRequest:
                /** @var RewardAvailability $row */
                $response = new RewardAvailabilityResponse($row->getId(), ACCOUNT_UNCHECKED, $request->getUserData(), new \DateTime());
                $row->setResponse($response);
                break;
            case $request instanceof RaHotelRequest:
                /** @var RaHotel $row */
                $response = new RaHotelResponse($row->getId(), ACCOUNT_UNCHECKED, $request->getUserData(), new \DateTime());
                $row->setResponse($response);
                break;
            case $request instanceof RegisterAccountRequest:
                /** @var RegisterAccount $row */
                $response = new RegisterAccountResponse($row->getId(), ACCOUNT_UNCHECKED, $request->getUserData(), 'placed into queue, use requestId to check result', new \DateTime());
                $row->setResponse($response);
                break;
            default:
                $response = new BaseCheckResponse($row->getId(), ACCOUNT_UNCHECKED, $request->getUserData(), new \DateTime());
                $row->setResponse($this->serializer->toArray($response));
                break;
        }

        if ($request instanceof CheckAccountRequest || $request instanceof AutologinWithExtensionRequest || $request instanceof ChangePasswordRequest) {
            $this->fillAwardWalletFields($row, $request->getUserData());
        }

        $this->manager->persist($row);
        $this->manager->flush(); // getting id before flush is unreliable

        return $row;
    }

    public function updateRow(BaseDocument $row): void
    {
        $this->manager->persist($row);
        $this->manager->flush();
    }

    private function fillAwardWalletFields(BaseDocument $row, ?string $userData)
    {
        if ($this->tokenStorage->getToken()->getUser()->getUsername() !== 'awardwallet' || $userData === null) {
            return;
        }

        try {
            /** @var UserData $userData */
            $userData = $this->serializer->deserialize($userData, UserData::class, 'json');
        } catch (\Exception $e) {
            $this->logger->notice(
                'Can not deserialize userData object from awardwallet partner',
                ['requestId' => $row->getId()]
            );
            return;
        }

        $row->setAccountId($userData->getAccountId());
        $row->setSource($userData->getSource());
    }

}