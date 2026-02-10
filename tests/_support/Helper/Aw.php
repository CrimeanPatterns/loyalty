<?php

namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use AppBundle\Document\AutoLogin;
use AppBundle\Document\CheckAccount;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\HistoryProcessor;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;
use AppBundle\Service\ApiValidator;
use AppBundle\Service\EngineStatus;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use AppBundle\Worker\CheckExecutor\RaHotelExecutor;
use AppBundle\Worker\CheckExecutor\RegisterAccountExecutor;
use AppBundle\Worker\CheckExecutor\RewardAvailabilityExecutor;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Document\HotSession;
use AwardWallet\Common\Document\HotSessionInfo;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\Common\Repository\HotSessionRepository;
use AwardWallet\Common\Selenium\HotSession\KeepActiveHotSessionManager;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use AwardWallet\ExtensionWorker\ParserRunner;
use Codeception\Module\REST;
use Codeception\Module\Symfony;
use Codeception\TestInterface;
use DateTime;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PHPUnit\Framework\MockObject\MockBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Aw extends \Codeception\Module
{

    /** @var TestInterface */
    private $activeTest;
    /** @var \PHPUnit_Framework_MockObject_MockObject[] */
    private $mocks = [];
    // todo: use mongo module
    private $insertedIds = [];

    public function _before(\Codeception\TestInterface $test)
    {
        $this->activeTest = $test;
    }

    public function _after(\Codeception\TestInterface  $test)
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        foreach ($this->mocks as $id => $mock)
            $symfony->unpersistService($id);

        $this->mocks = [];

        /** @var DocumentManager $mongo */
        $mongo = $symfony->grabService(DocumentManager::class);
        foreach($this->insertedIds as $document) {
            try {
                $mongo->createQueryBuilder($document['collection'])->remove()->field('id')->equals($document['id'])->getQuery()->execute();
            } catch(\Exception $e) {
                $this->debug('could not remove document');
            }
        }
        $this->insertedIds = [];
    }

    /**
     * @return int
     */
    public function createAwProvider(
        $name = null,
        $code = null,
        array $fields = [],
        array $instanceMethods = [],
        array $staticMethods = [],
        $useTraits = []
    ) {
        if (empty($code)) {
            $code = 'rp' . bin2hex(random_bytes(9));
        }
        if (empty($name)) {
            $name = "Provider $code";
        }

        if (strlen($code) > 20) {
            throw new \Exception("Provider code should be no longer than 20 chars");
        }

        // load constants
        $this->getModule('Symfony')->grabService("aw.old_loader");

        /** @var CustomDb $I */
        $I = $this->getModule('\Helper\CustomDb');
        $fakeData = [
            'Name' => $name,
            'DisplayName' => $name,
            'ShortName' => $name,
            'Code' => $code,
            'Kind' => PROVIDER_KIND_AIRLINE,
            'State' => PROVIDER_ENABLED,
            'LoginCaption' => 'Login',
            'LoginRequired' => 1,
            'PasswordCaption' => 'Password',
            'PasswordRequired' => 1,
            'CreationDate' => date("Y-m-d H:i:s"),
            'LoginURL' => 'https://some.url/login',
            'WSDL' => 1,
        ];

        $methods = [
            'static' => [],
            'instance' => [],
        ];

        if ($instanceMethods) {
            $defaultInstanceMethodsImpl = [
                'LoadLoginForm' => function () {
                    return true;
                },
                'Login' => function () {
                    return true;
                },
                'Parse' => function () {
                    /** @var $this \TAccountChecker */
                    $this->setBalanceNA();
                },
                'ParseItineraries' => function () {
                    return true;
                },
                'ParseHistory' => function ($startDate = null) {
                    return [];
                }
            ];

            $methods['instance'] = array_merge($defaultInstanceMethodsImpl, $instanceMethods);
        }

        if ($staticMethods) {
            $methods['static'] = $staticMethods;
        }

        $className = 'TAccountChecker' . ucfirst($code);
        $methodsCode = '';

        foreach ($methods as $scope => $scopeMethods) {
            foreach ($scopeMethods as $methodName => $closure) {
                $hash = spl_object_hash($closure);
                ClosureStorage::set($hash, $closure);
                $reflection = new \ReflectionFunction($closure);
                $params = $reflection->getParameters();

                $methodsCode .= sprintf('
                    public %6$s function %1$s(%5$s)
                    {
                        $closure = %2$s::get("%3$s")->bindTo(%7$s, "%4$s");
                        
                        return $closure(%8$s);
                    }
                    ',
                    $methodName,
                    ClosureStorage::class,
                    $hash,
                    $className,
                    implode(", ", array_map(function (\ReflectionParameter $param) {
                        return (method_exists($param,
                                'getType') && $param->getType() ? '\\' . $param->getType() . ' ' : '') . '$' . $param->getName() . ($param->isOptional() ? " = " . var_export($param->getDefaultValue(),
                                    true) : "");
                    }, $params)),
                    ($scope === 'static') ? $scope : '',
                    ($scope === 'static') ? 'null' : '$this',
                    implode(", ", array_map(function (\ReflectionParameter $param) {
                        return '$' . $param->getName();
                    }, $params))
                );
            }
        }

        if ('' !== $methodsCode) {
            $checkerCode = sprintf('
                namespace 
                {
                    class %1$s extends TAccountChecker {
                        %3$s
                        %2$s
                    }
                }
                ',
                $className,
                $methodsCode,
                empty($useTraits) ? "" : "use " . implode(", ", $useTraits) . ";\n"
            );
            eval($checkerCode);
            $className = '\\' . $className;
            new $className(); // prevent autoloading
        }

        $providerCountries = $fields['Countries'] ?? [];
        unset($fields['Countries']);

        $fakeData = array_merge($fakeData, $fields);
        $providerId = $I->haveInDatabase('Provider', $fakeData);

        foreach ($providerCountries as $countryCode => $providerCountryData) {
            $providerCountryData['ProviderID'] = $providerId;
            $providerCountryData['CountryID'] = $I->grabFromDatabase('Country', 'CountryID',
                ['Code' => $countryCode, 'Name' => $providerCountryData['Name']]);
            unset($providerCountryData['Name']);
            $I->haveInDatabase('ProviderCountry', $providerCountryData);
        }

        return $providerId;
    }

    private function getCheckAccountWorker()
    {
        /** @var ContainerInterface $container */
        $container = $this->getModule('Symfony')->grabService('kernel')->getContainer();

        $engineStatus = $this->getMock(EngineStatus::class);
        $engineStatus->method('isFresh')->willReturn(true);

        $memcached = @$this->getMock(\Memcached::class);
        $memcached
            ->method('get')
            ->willReturn(false)
        ;

        $validatorMock = $this->getMock(ApiValidator::class);
        $validatorMock->method('validate')->willReturn([]);

        $tc = @$this->getMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time())
        ;

        $worker = new CheckAccountExecutor(
            $this->getMock(Logger::class),
            $container->get('database_connection'),
            $container->get('doctrine.dbal.shared_connection'),
            $this->getMock(DocumentManager::class),
            $container->get('aw.old_loader'),
            $container->get('aw.checker_factory'),
            $this->getMock(S3Custom::class),
            $this->getMock(Producer::class),
            $this->getMock(MQSender::class),
            $container->get('jms_serializer'),
            $this->getMock(Producer::class),
            $memcached,
            // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $container->getParameter('aes_key_local_browser_state'),
            $validatorMock,
            $this->getMock(ItinerariesFilter::class),
            $this->getMock(MasterSolver::class),
            $this->getMock(Watchdog::class),
            $this->getMock(EventDispatcher::class),
            $this->getMock(Util::class),
            $this->getMock(CurrencyConverter::class),
            $tc,
            $this->getMock(ParserFactory::class),
            $this->getMock(ParserRunner::class),
            $this->getMock(ClientFactory::class),
            $this->getMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo($this->getMock(ObjectRepository::class));
        $worker->setHistoryProcessor($this->getMock(HistoryProcessor::class));

        return $worker;
    }

    public function checkAccount(CheckAccountRequest $request) : CheckAccountResponse
    {
        $row = new CheckAccount();
        $row->setId(bin2hex(random_bytes(10)));
        $partner = 'test_' . bin2hex(random_bytes(5));
        $row->setPartner($partner);
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("Partner", ["Login" => $partner, "ReturnHiddenProperties"  => 0, "Pass" => "123"]);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $row);

        return $response;
    }

    public function createApiRoleUserToken($login, array $roles) :ApiToken
    {
        $pass = bin2hex(random_bytes(5));
        $user = new ApiUser($login, $pass, 2, 1, $roles);
        return new ApiToken(
            $user,
            $login.':'.$pass,
            'secured',
            $user->getRoles()
        );
    }

    public function getMock($className)
    {
        return (new MockBuilder($this->activeTest,
            $className))->disableOriginalConstructor()->getMock();
    }

    public function mockService($id, $mock){
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var ContainerInterface $container */
        $container = $symfony->_getContainer();
//        $def = $container-> getDefinition($id);
        $container->set($id, $mock);
        $symfony->persistService($id);
        $this->mocks[$id] = $mock;
    }

    public function searchRewardAvailability(RewardAvailabilityRequest $request, ?string $partnerLogin = 'awardwallet', ?int $refreshTime = null) : RewardAvailabilityResponse
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);

        $doc = new RewardAvailability();
        $doc->setRequest($request);
        $doc->setApiVersion(1);
        $doc->setPartner($partnerLogin);
        $doc->setRetries(-1);
        $mongoManager->persist($doc);
        $mongoManager->flush();

        $response = new RewardAvailabilityResponse($doc->getId(), ACCOUNT_UNCHECKED, 'blah', new DateTime());
        $doc->setResponse($response);
        $mongoManager->flush();

        /** @var RewardAvailabilityExecutor $executor */
        $executor = $symfony->grabService(RewardAvailabilityExecutor::class);
        if (isset($refreshTime)) {
            $executor->refreshTime = $refreshTime;
        }
        $executor->execute($doc);

        $response= $doc->getResponse();

        return $response;
    }

    public function searchRaHotel(RaHotelRequest $request, ?string $partnerLogin = 'awardwallet', ?int $refreshTime = null): RaHotelResponse
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);

        $doc = new RaHotel();
        $doc->setRequest($request);
        $doc->setApiVersion(1);
        $doc->setPartner($partnerLogin);
        $doc->setRetries(-1);
        $mongoManager->persist($doc);
        $mongoManager->flush();

        $response = new RaHotelResponse($doc->getId(), ACCOUNT_UNCHECKED, 'blah', new DateTime());
        $doc->setResponse($response);
        $mongoManager->flush();

        /** @var RaHotelExecutor $executor */
        $executor = $symfony->grabService(RaHotelExecutor::class);
        if (isset($refreshTime)) {
            $executor->refreshTime = $refreshTime;
        }
        $executor->execute($doc);

        $response = $doc->getResponse();

        return $response;
    }

    public function runRegisterAccountRA(RegisterAccountRequest $request, ?string $partnerLogin = 'awardwallet') : RegisterAccountResponse
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);

        $doc = new RegisterAccount();
        $doc->setRequest($request);
        $doc->setApiVersion(1);
        $doc->setPartner($partnerLogin);
        $doc->setRetries(-1);
        $mongoManager->persist($doc);
        $mongoManager->flush();

        $this->insertedIds[] = ['collection' => RaAccount::class, 'id' => $doc->getId()];

        $response = new RegisterAccountResponse($doc->getId(), ACCOUNT_UNCHECKED, 'blah', 'blah-blah', new DateTime());
        $doc->setResponse($response);
        $mongoManager->flush();

        /** @var RewardAvailabilityExecutor $executor */
        $executor = $symfony->grabService(RegisterAccountExecutor::class);
        $executor->execute($doc);

        $response= $doc->getResponse();

        return $response;
    }

    public function runSearchRewardAvailabilityById(?string $requestId, ?string $partnerLogin = 'awardwallet') : ?RewardAvailabilityResponse
    {
        if (!$requestId)
            return null;

        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var RewardAvailability $doc */
        $doc = $mongoManager->getRepository(RewardAvailability::class)->find($requestId);
        if (!$doc) {
            return null;
        }

        /** @var RewardAvailabilityExecutor $executor */
        $executor = $symfony->grabService(RewardAvailabilityExecutor::class);
        $executor->execute($doc);

        return $doc->getResponse();
    }

    public function haveRaAccount($provider, $login, $password, $lastUseDate = '-5 min', $setLock = null, $errorCode = null): ?RaAccount
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var RaAccountRepository $repo */
        $repo = $mongoManager->getRepository(RaAccount::class);

        $account = $repo->findOneBy(['provider' => $provider, 'login' => $login]);
        $persisted = false;
        if (!$account) {
            $account = new RaAccount($provider, $login, $password, $login.bin2hex(random_bytes(8)));
            $mongoManager->persist($account);
            $persisted = true;
        }
        $account->setLastUseDate(new DateTime($lastUseDate));
        $account->setLockState($setLock ?? false);
        $account->setErrorCode($errorCode ?? 1);
        $mongoManager->flush();
        if ($persisted) {
            $this->insertedIds[] = ['collection' => RaAccount::class, 'id' => $account->getId()];
        }
        return $account;
    }

    public function addHotSession($provider, $prefix, $accountKey  = null, ?int $lastUseDate = null, $isLocked = false): string
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var HotSessionRepository $repo */
        $repo = $mongoManager->getRepository(HotSession::class);

        $sessionInfo = new HotSessionInfo('1.2.3.4', 4444, 'sId' . bin2hex(random_bytes(8)), 'fakeShare', 'fakeFamily', 'fakeVersion', '/somePath');
        $sessionId = $repo->createNewRow($prefix, $provider, $accountKey, $sessionInfo);
        if (null !== $lastUseDate) {
            $lastUseDateStr = date('Y-m-d H:i:s', $lastUseDate);
            $session = $repo->find($sessionId);
            $session
                ->setIsLocked($isLocked)
                ->setStartDate(new \DateTime($lastUseDateStr))
                ->setLastUseDate(new \DateTime($lastUseDateStr));
        }
        $mongoManager->flush();
        $this->insertedIds[] = ['collection' => HotSession::class, 'id' => $sessionId];
        return $sessionId;
    }

    public function haveHotSessions($provider, $prefix, $accountKey = null): int
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var HotSessionRepository $repo */
        $repo = $mongoManager->getRepository(HotSession::class);

        $criteria = ['provider' => $provider, 'prefix' => $prefix];
        if (!empty($accountKey)) {
            $criteria['accountKey'] = $accountKey;
        }
        $sessions = $repo->findBy($criteria);

        return count($sessions);
    }

    public function getHotSession($provider, $prefix, $accountKey = null): ?HotSession
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var HotSessionRepository $repo */
        $repo = $mongoManager->getRepository(HotSession::class);
        $criteria = ['provider' => $provider, 'prefix' => $prefix];
        if (!empty($accountKey)) {
            $criteria['accountKey'] = $accountKey;
        }

        return $repo->findOneBy($criteria);
    }

    public function getHotSessionById(string $id): ?HotSession
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var HotSessionRepository $repo */
        $repo = $mongoManager->getRepository(HotSession::class);

        return $repo->find($id);
    }

    public function runKeepActiveHot(string $providerCode)
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var KeepActiveHotSessionManager $mongoManager */
        $keepActiveHotManager = $symfony->grabService(KeepActiveHotSessionManager::class);
        $keepActiveHotManager->runKeepHot($providerCode);
    }

    public function getRaAccount(array $criteria): ?RaAccount
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var RaAccountRepository $repo */
        $repo = $mongoManager->getRepository(RaAccount::class);

        return $repo->findOneBy($criteria);
    }

    public function checkRaAccountLock($provider, $login, $byProvider = false)
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $mongoManager */
        $mongoManager = $symfony->grabService(DocumentManager::class);
        /** @var RaAccountRepository $repo */
        $repo = $mongoManager->getRepository(RaAccount::class);

        if ($byProvider) {
            $account = $repo->findOneBy(['provider' => $provider, 'login' => $login, 'errorCode' => ACCOUNT_LOCKOUT]);
        } else {
            $account = $repo->findOneBy(['provider' => $provider, 'login' => $login, 'lockState' => RaAccount::PARSE_LOCK]);
        }
        return $account !== null;
    }

    public function setHost($host)
    {
        $this->getModule('Symfony')->client->setServerParameter('HTTP_HOST', $host);
    }

    public function createCheckAccountRow(string $providerCode = "testprovider", bool $persist = true) : CheckAccount
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        $row = new CheckAccount();
        $row->setApiVersion(2);

        if ($persist) {
            /** @var DocumentManager $dm */
            $dm = $symfony->grabService("doctrine_mongodb.odm.default_document_manager");
            $dm->persist($row);
            $dm->flush();
        }

        $symfony->grabService("aw.old_loader");
        $row->setResponse(["state" => ACCOUNT_UNCHECKED, "requestDate" => date("Y-m-d\TH:i:sP"), 'requestId' => $row->getId()]);
        $row->setPartner("awardwallet");
        $row->setRequest(['provider' => $providerCode, 'priority' => 1, "login" => "somelogin", "password" => "somepass", "parseItineraries" => 1]);

        if ($persist) {
            $dm->flush();
        }

        return $row;
    }

    public function createAutoLoginRow() : AutoLogin
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        /** @var DocumentManager $dm */
        $dm = $symfony->grabService("doctrine_mongodb.odm.default_document_manager");
        /** @var MQSender $mqSender */
        $mqSender = $symfony->grabService(MQSender::class);
        $queue = $mqSender->declareTmpQueue();
        $row = new AutoLogin(['provider' => 'testprovider', 'priority' => 1], 'awardwallet', $queue);
        $dm->persist($row);
        $dm->flush();
        $symfony->grabService("aw.old_loader");
        $row->setResponse(["state" => ACCOUNT_UNCHECKED, "requestDate" => date("Y-m-d\TH:i:sP"), 'requestId' => $row->getId()]);
        $dm->flush();

        return $row;
    }

    public function registerExtensionParser(string $providerCode, string $parserFile) : void
    {
        $parserSource = file_get_contents($parserFile);
        $parserSource = preg_replace('#class\s+(\w+)Extension#ims', "class " . ucfirst($providerCode) . "Extension", $parserSource);
        $parserSource = str_replace('namespace Tests\\Unit\\Worker\\Executor\\Extensions;', "namespace AwardWallet\\Engine\\" . $providerCode . ";", $parserSource);
        $parserSource = str_replace('<?php', "", $parserSource);
        eval($parserSource);
    }

    public function createPartnerAndApiKey(array $partnerFields = [])
    {
        $partner = 'partner' . bin2hex(random_bytes(5));
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $partnerId = $db->haveInDatabase('Partner', array_merge(["Login" => $partner, "Pass" => "xxx", "LoyaltyAccess" => 1], $partnerFields));
        $apiKey = $partner . ':' . bin2hex(random_bytes(5));
        $db->haveInDatabase('PartnerApiKey', ["PartnerID" => $partnerId, "ApiKey" => $apiKey]);
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->haveHttpHeader('X-Authentication', $apiKey);
    }

}
