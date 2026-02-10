<?php

namespace AppBundle\Controller;


use AppBundle\Document\CheckAccount;
use AppBundle\Document\PasswordRequestDocument;
use AppBundle\Model\Resources\PasswordRequest;
use AppBundle\Security\ApiUser;
use Doctrine\MongoDB\ArrayIterator;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/admin-aw", name="aw_controller_admin_")
 */
class AwAdminController
/* TODO: migrate to AdminController after removing paths admin/... from swagger-schema refs #16368 */
{

    /** @var DocumentManager */
    private $dm;
    /** @var DocumentManager */
    private $dmReplica;
    /** @var SerializerInterface */
    private $serializer;
    /** @var TokenStorageInterface */
    private $tokenStorage;

    public function __construct(
        DocumentManager $dm,
        DocumentManager $dmReplica,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage
    ) {
        $this->dm = $dm;
        $this->serializer = $serializer;
        $this->tokenStorage = $tokenStorage;
        $this->dmReplica = $dmReplica;
    }

    /**
     * @Route("/password-request/list", name="passwords_request_list", methods={"GET"})
     * @param Request $httpRequest
     * @return JsonResponse
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function listPasswordRequestAction(Request $httpRequest)
    {
        $this->checkIsAdmin();
        /** @var ArrayIterator $query */
        $query = $this->dm->createQueryBuilder(PasswordRequestDocument::class)
            ->sort('requestDate', 'desc')
            ->getQuery()->execute();
        $list = $query->toArray();
        return new JsonResponse(['list' => $this->serializer->toArray($list)]);
    }

    /**
     * @Route("/password-request/{id}", name="password_request_item", methods={"GET"})
     * @param Request $httpRequest
     * @return JsonResponse
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function itemPasswordRequestAction(Request $httpRequest, $id)
    {
        $this->checkIsAdmin();
        $repo = $this->dm->getRepository(PasswordRequestDocument::class);
        $row = $repo->find($id);
        if (!$row) {
            return new JsonResponse(['result' => 'failed', 'message' => 'request not found'], 404);
        }
        return new JsonResponse(['item' => $this->serializer->toArray($row)]);
    }

    /**
     * @Route("/password-request", name="password_request", methods={"POST"})
     * @param Request $httpRequest
     * @return JsonResponse
     */
    public function registerPasswordRequestAction(Request $httpRequest)
    {
        $this->checkIsAdmin();
        /** @var PasswordRequest $request */
        $request = $this->serializer->deserialize($httpRequest->getContent(), PasswordRequest::class, 'json');
        if (empty($request->getPartner())) {
            return new JsonResponse(['result' => 'failed', 'message' => '\'partner\' is required field '], 400);
        }
        if (empty($request->getProvider())) {
            return new JsonResponse(['result' => 'failed', 'message' => '\'provider\' is required field '], 400);
        }

        $params = [
            'partner' => $request->getPartner(),
            'provider' => $request->getProvider(),
        ];
        if (!empty($request->getLogin())) {
            $params['login'] = $request->getLogin();
        }

        $repo = $this->dm->getRepository(PasswordRequestDocument::class);
        $row = $repo->findOneBy($params);
        if (!$row) {
            $row = (new PasswordRequestDocument())
                ->setPartner($request->getPartner())
                ->setProvider($request->getProvider())
                ->setLogin($request->getLogin())
                ->setNote($request->getNote())
                ->setRequestDate(new \DateTime())
                ->setUserId($request->getUserId());

            $this->dm->persist($row);
            $this->dm->flush();
        }

        return new JsonResponse(['requestId' => $row->getId()]);
    }

    /**
     * @Route("/password-request/edit/{requestId}", name="password_request_edit", methods={"POST"})
     * @param $requestId
     * @param Request $httpRequest
     * @return JsonResponse
     */
    public function editPasswordRequestAction(Request $httpRequest, $requestId)
    {
        $this->checkIsAdmin();
        /** @var PasswordRequest $request */
        $request = $this->serializer->deserialize($httpRequest->getContent(), PasswordRequest::class, 'json');
        if (empty($request->getPartner())) {
            return new JsonResponse(['result' => 'failed', 'message' => '\'partner\' is required field '], 400);
        }
        if (empty($request->getProvider())) {
            return new JsonResponse(['result' => 'failed', 'message' => '\'provider\' is required field '], 400);
        }

        $repo = $this->dm->getRepository(PasswordRequestDocument::class);
        /** @var PasswordRequestDocument $row */
        $row = $repo->find($requestId);
        if (!$row) {
            return new JsonResponse(['result' => 'failed', 'message' => 'Unavailable requestId'], 400);
        }

        $row->setPartner($request->getPartner())
            ->setProvider($request->getProvider())
            ->setLogin($request->getLogin())
            ->setNote($request->getNote())
            ->setRequestDate(new \DateTime());

        $this->dm->persist($row);
        $this->dm->flush();

        return new JsonResponse(['result' => 'success']);
    }

    /**
     * @Route("/password-request/remove", name="password_request_remove", methods={"POST"})
     * @param Request $httpRequest
     * @return JsonResponse
     */
    public function removePasswordRequestAction(Request $httpRequest)
    {
        $this->checkIsAdmin();
        /** @var PasswordRequest $request */
        $data = @json_decode($httpRequest->getContent(), true);
        $id = $data['id'] ?? null;
        if (empty($id)) {
            return new JsonResponse(['result' => 'failed', 'message' => 'id is required param'], 400);
        }

        $repo = $this->dm->getRepository(PasswordRequestDocument::class);
        $fishingRow = $repo->find($id);
        if (!$fishingRow) {
            return new JsonResponse(['result' => 'failed', 'message' => 'Unavailable requestId'], 400);
        }

        $this->dm->remove($fishingRow);
        $this->dm->flush();

        return new JsonResponse(['result' => 'success']);
    }

    /**
     * @Route("/partner-queue-priority/{partner}", name="partner_queue_priority", methods={"GET"})
     * @param Request $httpRequest
     * @param $partner
     * @return JsonResponse
     */
    public function getPartnerQueuePrioritiesAction(Request $httpRequest, $partner)
    {
        $this->checkIsAdmin();
        /** @var ArrayIterator $query */
        $query = $this->dmReplica->createQueryBuilder(CheckAccount::class)
            ->group(['request.priority' => 1], ['count' => 0])
            ->reduce('
                                function (obj, prev) { 
                                    prev.count++; 
                                }
                            ')
            ->field('partner')->equals($partner)
            ->field('response.state')->equals(0)
            ->getQuery()->execute();

        $list = $query->toArray();
        return new JsonResponse([
            'priorities' => $this->serializer->toArray($list),
        ]);
    }

    private function checkIsAdmin()
    {
        if (!in_array(ApiUser::ROLE_ADMIN, $this->tokenStorage->getToken()->getUser()->getRoles())) {
            throw new AccessDeniedException();
        }
    }

}