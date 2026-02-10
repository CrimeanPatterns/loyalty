<?php

namespace AppBundle\Command;

use AppBundle\Controller\AccountController;
use AppBundle\Model\Resources\CheckAccountPackageRequest;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\PostCheckPackageResponse;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use Doctrine\DBAL\Connection;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CheckFakeAccountsCommand extends Command
{

    /** @var Connection */
    protected $connection;
    /** @var SerializerInterface */
    private $serializer;
    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var AccountController */
    private $controller;
    /** @var RequestStack */
    private $requestStack;

    protected static $defaultName = 'aw:check-accounts';
    /**
     * @var CheckAccountExecutor
     */
    private $checkAccountExecutor;
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var LoggerInterface
     */
    private $logger;

    protected function configure()
    {
        $this
            ->setDescription('Check "n" accounts of "p" provider')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'accounts count')
            ->addOption('partner', null, InputOption::VALUE_REQUIRED, 'api user')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'provider code')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'leave empty for random logins')
            ->addOption('login2', null, InputOption::VALUE_REQUIRED)
            ->addOption('synchronous', null, InputOption::VALUE_NONE, 'run check in process')
        ;
    }

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AccountController $controller,
        SerializerInterface $serializer,
        Connection $connection,
        RequestStack $requestStack,
        CheckAccountExecutor $checkAccountExecutor,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->serializer = $serializer;
        $this->connection = $connection;
        $this->tokenStorage = $tokenStorage;
        $this->controller = $controller;
        $this->requestStack = $requestStack;
        $this->checkAccountExecutor = $checkAccountExecutor;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        if (empty($input->getOption('partner'))) {
            $output->writeln("Undefined partner param\n");
            return;
        }

        $partner = $input->getOption('partner');
        $this->tokenStorage->setToken($this->createApiRoleUserToken($partner));

        if (empty($input->getOption('provider'))) {
            $output->writeln("Undefined provider code param\n");
            return;
        }
        if (empty($input->getOption('count'))) {
            $output->writeln("Undefined accounts count param\n");
            return;
        }

        $provider = $input->getOption('provider');
        $count = $input->getOption('count');
        $package = [];

        for ($i = 0; $i < $count; $i++) {
            $requestItem = new CheckAccountRequest();

            if ($input->getOption('login')) {
                $login = $input->getOption('login');
            } else {
                $login = md5($i . time() . 'login');
            }

            $requestItem->setProvider($provider)
                ->setLogin($login)
                ->setLogin2($input->getOption('login2'))
                ->setUserid(md5($i . time() . 'userid'))
                ->setPassword('g5f4' . rand(1000, 9999) . '_q')
                ->setPriority(8)
                ->setUserData(json_encode(['accountId' => 1, "priority" => 8, "source" => 1]));

            $package[] = $requestItem;
        }

        $request = (new CheckAccountPackageRequest())->setPackage($package);
        $httpRequest = Request::create('/v2', 'POST', [], [], [], [], $this->serializer->serialize($request, 'json'));
        $this->requestStack->push($httpRequest);
        $httpResponse = $this->controller->checkAccountPackage($httpRequest, 2, !$input->getOption('synchronous'));
        $responses = $this->serializer->deserialize($httpResponse->getContent(), PostCheckPackageResponse::class, 'json');

        $requestIds = [];
        foreach ($responses->getPackage() as $response) {
            /** @var PostCheckResponse $response */
            if (is_array($response) && isset($response['ErrorMessage'])) {
                $output->writeln("request errors: " . $response['ErrorMessage']);
            } else {
                $output->writeln("request sent, id: " . $response->getRequestid());
                $requestIds[] = $response->getRequestid();
            }
        }

        $this->logger->debug("check debug log level 1");
        $this->logger->alert("check alert log level 1");

        if ($input->getOption('synchronous')) {
            $this->synchronousCheck($requestIds);
        }

        $this->logger->debug("check debug log level 2");
        $this->logger->alert("check alert log level 2");

        $output->writeln("done");
    }

    protected function createApiRoleUserToken($partner)
    {
        $sql = <<<SQL
            SELECT PartnerID, Login, Pass, CanDebug, Threads FROM Partner
            WHERE Login = :PARTNER
SQL;
        $result = $this->connection->executeQuery($sql, [':PARTNER' => $partner])->fetch();
        $user = new ApiUser($result['Login'], $result['Pass'], $result['PartnerID'], $result['Threads'],
            array_merge([ApiUser::ROLE_USER], ApiUser::ALLOWED_ROLES));
        return new ApiToken(
            $user,
            $result['Login'] . ':' . $result['Pass'],
            'secured',
            $user->getRoles()
        );
    }

    private function synchronousCheck(array $requestIds) : void
    {
        $this->output->writeln("synchronous check");
        foreach ($requestIds as $requestId) {
            $this->checkAccountExecutor->execute($requestId);
        }
    }

}