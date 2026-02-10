<?php

namespace AppBundle\Controller;

use AwardWallet\Common\Document\HotSession;
use AwardWallet\Common\Selenium\HotSession\KeepActiveHotSessionManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Route("/hot-session", name="controller_ra_admin_")
 */
class HotSessionController
{
    /** @var DocumentManager */
    private $manager;

    /** @var KeepActiveHotSessionManager */
    private $hotManager;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(
        DocumentManager $manager,
        SerializerInterface $serializer,
        KeepActiveHotSessionManager $hotManager
    ) {
        $this->manager = $manager;
        $this->hotManager = $hotManager;
        $this->serializer = $serializer;
    }

    /**
     * Getting a list of hot sessions.
     *
     * @Route("", host="%reward_availability_host%", name="get_list", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function getListAction()
    {
        $repo = $this->manager->getRepository(HotSession::class);
        $data = $repo->findAll();
        $data = $this->serializer->serialize($data, 'json');
        $data = json_decode($data);
        $data = array_map(function ($s) {
            unset($s->sessionInfo);
            return $s;
        }, $data);

        return new JsonResponse($data);
    }

    /**
     * Stop hot session by id.
     *
     * @Route("/stop", host="%reward_availability_host%", name="stop", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function stopAction(Request $httpRequest)
    {
        $data = json_decode($httpRequest->getContent(), true);
        if (!isset($data['id'])) {
            return new BadRequestHttpException('wrong format');
        }

        $this->hotManager->stopHotById($data['id']);

        return new JsonResponse(['success' => true]);
    }
}