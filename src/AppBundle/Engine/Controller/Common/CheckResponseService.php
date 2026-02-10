<?php


namespace AppBundle\Controller\Common;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use AppBundle\Model\Resources\PostCheckResponse;
use Doctrine\Common\Persistence\ObjectRepository;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CheckResponseService
{

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(TokenStorageInterface $tokenStorage, SerializerInterface $serializer)
    {
        $this->tokenStorage = $tokenStorage;
        $this->serializer = $serializer;
    }

    public function getCheckResponse(string $id, ObjectRepository $repo, int $apiVersion, string $responseCls): LoyaltyResponseInterface
    {
        $partner = $this->tokenStorage->getToken()->getUser()->getUsername();
        /** @var BaseDocument $row */
        $row = $repo->find($id);
        if (!$row) {
            throw new NotFoundHttpException();
        }
        $rowApiVersion = $row->getApiVersion() ?? 1;
        if ($row->getPartner() !== $partner || $rowApiVersion !== $apiVersion) {
            throw new NotFoundHttpException();
        }

        if ($row instanceof RewardAvailability || $row instanceof RaHotel || $row instanceof RegisterAccount) {
            $response = $row->getResponse();
        } else {
            /** @var BaseCheckResponse $response */
            $response = $this->serializer->deserialize(json_encode($row->getResponse()), $responseCls, 'json');
        }

        if ($partner === 'awardwallet') {
            return $response;
        }

        $removedDate = strtotime("-2 hours");
        if ($response instanceof BaseCheckResponse && $response->getCheckdate() !== null && $response->getCheckdate()->getTimestamp() < $removedDate) {
            throw new NotFoundHttpException();
        }

        return $response;
    }


    public function createPostResponse($mongoRowId): PostCheckResponse
    {
        return (new PostCheckResponse())->setRequestid($mongoRowId);
    }
}