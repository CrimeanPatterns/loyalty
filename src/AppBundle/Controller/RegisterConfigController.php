<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\RegisterConfig;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterConfigRequest;
use AppBundle\Security\ApiUser;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class RegisterConfigController
{
    /** @var ResponseFactory */
    private $responseFactory;

    /** @var RequestValidatorService */
    private $requestValidator;

    /** @var DocumentManager */
    private $manager;

    /** @var Connection */
    private $connection;

    /** @var ObjectRepository */
    private $repo;

    /** @var RequestFactory */
    private $requestFactory;

    public function __construct(
        DocumentManager $manager,
        Connection $connection,
        ObjectRepository $repo,
        RequestValidatorService $requestValidator,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        Loader $loader
    ) {
        $this->manager = $manager;
        $this->connection = $connection;
        $this->requestValidator = $requestValidator;
        $this->responseFactory = $responseFactory;
        $this->repo = $repo;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Getting a list of register config parameters.
     *
     * @Route("/ra-register-config/list", host="%reward_availability_host%", name="ra_register_config_list", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function getRaRegisterConfigListAction(): Response
    {
        return $this->responseFactory->buildNoSwaggerResponse(
            $this->repo->findAll()
        );
    }

    /**
     * Create register config.
     *
     * @Route("/ra-register-config/create", host="%reward_availability_host%", name="ra_register_config_create", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function createRaRegisterConfigAction(Request $request): Response
    {
        $request = $this->requestFactory->buildRequest($request, RegisterConfigRequest::class, 1, false);

        $sql = "
          SELECT Code
          FROM Provider
          WHERE CanRegisterRewardAvailabilityAccount = 1 AND Code <> 'testprovider'
          ORDER BY DisplayName
        ";

        $result = $this->connection->executeQuery($sql)->fetchFirstColumn();

        if (!in_array($request->getProvider(), $result)) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => "Wrong provider!"
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        $config = new RegisterConfig(
            $request->getProvider(),
            $request->getDefaultEmail(),
            $request->getRuleForEmail(),
            $request->getMinCountEnabled(),
            $request->getMinCountReserved(),
            $request->getDelay(),
            $request->getIsActive(),
            $request->getIs2Fa(),
        );

        $this->manager->persist($config);
        $this->manager->flush($config);

        return new Response(
            json_encode([
                'success' => true,
                'message' => "Successful create configuration for {$request->getProvider()} provider. Id {$config->getId()}."
            ]),
            201,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Edit register config by id.
     *
     * @Route("/ra-register-config/{id}/edit", host="%reward_availability_host%", name="ra_register_config_edit", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function editRaRegisterConfigAction(Request $request, string $id): Response
    {
        $request = $this->requestFactory->buildRequest($request, RegisterConfigRequest::class, 1, false);

        $result = $this->manager->createQueryBuilder(RegisterConfig::class)
            ->findAndUpdate()
            ->field('_id')->equals($id)
            ->field('defaultEmail')->set($request->getDefaultEmail())
            ->field('ruleForEmail')->set($request->getRuleForEmail())
            ->field('minCountEnabled')->set($request->getMinCountEnabled())
            ->field('minCountReserved')->set($request->getMinCountReserved())
            ->field('delay')->set($request->getDelay())
            ->field('isActive')->set($request->getIsActive())
            ->field('is2Fa')->set($request->getIs2Fa())
            ->getQuery()->execute();

        if (!$result) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => "Something went wrong. Try again."
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => true,
                'message' => "Successful update configuration id {$id}."
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Delete register config by id.
     *
     * @Route("/ra-register-config/{id}/delete", host="%reward_availability_host%", name="ra_register_config_delete", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function deleteRaRegisterConfigAction(string $id): Response
    {
        $result = $this->manager->createQueryBuilder(RegisterConfig::class)
            ->findAndRemove()
            ->field('_id')->equals($id)
            ->getQuery()->execute();

        if (!$result) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => "Not found configuration."
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => true,
                'message' => "Successful delete configuration."
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}