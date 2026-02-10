<?php

namespace AppBundle\Controller;

use AppBundle\Document\AutoLoginWithExtension;
use AppBundle\Document\BrowserState;
use AppBundle\Document\EmbeddedDocumentsInterface;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RaAccountAnswer;
use AppBundle\Document\RaAccountRegisterInfo;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\Loader;
use AppBundle\Extension\S3Custom;
use AppBundle\Model\Resources\AdminLogsRequest;
use AppBundle\Model\Resources\AdminLogsResponse;
use AppBundle\Model\Resources\AdminRaAccount;
use AppBundle\Model\Resources\AdminStatisticRequest;
use AppBundle\Model\Resources\AdminStatisticResponse;
use AppBundle\Model\Resources\LogItem;
use AppBundle\Security\ApiUser;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\MongoDB\ArrayIterator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Builder;
use Elastica\Client;
use Elastica\Query;
use Elastica\Search;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @Route("/admin", name="controller_admin_")
 */
class AdminController
{

    use ContainerAwareTrait;

    /** @var DocumentManager */
    private $manager;
    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var LoggerInterface */
    private $logger;
    /** @var S3Custom */
    private $s3client;
    /** @var Loader */
    private $loader;
    /** @var Client */
    private $statClient;
    /** @var DocumentManager  */
    private $dm;
    /** @var string */
    private $aesKey;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    private const QUERY_LOGS_LIMIT = 50;
    private const LOGS_LIMIT = 50;
    private const API_VERSION = 1;

    public function __construct(
        DocumentManager $manager,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        S3Custom $s3client,
        Loader $loader,
        Client $statClient,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        DocumentManager $dm,
        $elasticStatIndex,
        $elasticStatType,
        $aesKey

    ) {
        $this->manager = $manager;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->s3client = $s3client;
        $this->statClient = $statClient;
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->dm = $dm;
        $this->aesKey = $aesKey;
    }

    /**
     * @Route("/logs", name="get_logs", methods={"POST"})
     */
    public function getLogs(Request $httpRequest): Response
    {
        /** @var AdminLogsRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, AdminLogsRequest::class, self::API_VERSION);
        $this->checkUserAccess();

        $queryParams = [
            'id' => $request->getRequestid(),
            'partner' => $request->getPartner(),
            'request.provider' => $request->getProvider(),
            'request.login' => $request->getLogin(),
            'request.login2' => $request->getLogin2(),
            'request.login3' => $request->getLogin3(),
        ];

        /** @var Builder $queryB */
        $queryB = $this->manager->createQueryBuilder('AppBundle\\Document\\' . ucfirst($request->getMethod()));
        $sort = [];

        foreach ($queryParams as $param => $value) {
            if (!empty($value)) {
                $queryB->field($param)->equals($value);
                $sort[$param] = 1;
            }
        }

        if (!empty($request->getUserdata())) {
            $accId = in_array(strtolower($request->getMethod()), ['checkaccount', 'autologinwithextension']) ? (int)$request->getUserdata() : $request->getUserdata();
            $queryB->field('accountId')->equals($accId);
            $sort['accountId'] = 1;
        } elseif (strtolower($request->getMethod()) !== 'rewardavailability' && strtolower($request->getMethod()) !== 'rewardavailabilityhotel' && strtolower($request->getMethod()) !== 'registeraccount') {
            if (!empty($sort['id'])) {
                $queryB->hint(["_id" => 1]);
            } else {
                $queryB->hint(["partner" => 1, "request.provider" => 1, "request.login" => 1, "queuedate" => -1]);
            }
            if (!isset($sort['partner'])) {
                return new JsonResponse("Partner or AccountID required", 400);
            }
        }

        $sort['queuedate'] = -1;

        /*

        test indexes with queries:

         # accountId
         db.CheckAccount.find({"partner" : "awardwallet", "accountId" : 644564, "queuedate" : {"$ne" : null}}, {"_id" : 1, "partner" : 1, "request.provider" : 1,"updatedate" : 1}).sort({"partner": 1, "accountId" : 1, "queuedate": -1}).limit(50).explain()
         *
         # login, login2
         db.CheckAccount.find({"partner" : "awardwallet", "request.provider": "aa", "request.login" : "644564", "request.login2": "xxx", "queuedate" : {"$ne" : null}}, {"_id" : 1, "partner" : 1, "request.provider" : 1}).sort({"partner": 1, "request.provider": 1, "request.login" : 1, "request.login2" : 1, "queuedate": -1}).limit(50).explain()
         *
         # login
         db.CheckAccount.find({"partner" : "awardwallet", "request.provider": "aa", "request.login" : "644564", "queuedate" : {"$ne" : null}}, {"_id" : 1, "partner" : 1, "request.provider" : 1}).sort({"partner": 1, "request.provider": 1, "request.login" : 1, "queuedate": -1}).limit(50).explain()
         *
         # provider
         db.CheckAccount.find({"partner" : "awardwallet", "request.provider": "aa", "queuedate" : {"$ne" : null}}, {"_id" : 1, "partner" : 1, "request.provider" : 1}).sort({"partner": 1, "request.provider": 1, "queuedate": -1}).limit(50).explain()
         *
         # request id
         db.CheckAccount.find({"_id": "123", "partner" : "awardwallet", "queuedate" : {"$ne" : null}}, {"_id" : 1, "partner" : 1, "request.provider" : 1}).sort({"_id": 1, "partner": 1, "queuedate": -1}).limit(50).explain()
         */

        /** @var ArrayIterator $queryResult */
        $queryResult = $queryB->select('_id', 'partner', 'request.provider')
            ->field('queuedate')->notEqual(null)
            ->sort($sort)
            ->limit(self::QUERY_LOGS_LIMIT)
            ->getQuery()->execute();//getIterator();

        $rows = $queryResult->toArray();

        $files = [];
        foreach ($rows as $row) {
            $provider = $row instanceof RewardAvailability || $row instanceof RegisterAccount || $row instanceof RaHotel || $row instanceof EmbeddedDocumentsInterface ? $row->getRequest()->getProvider() : $row->getRequest()['provider'];
            $method = strtolower($request->getMethod());

            if ($row instanceof AutoLoginWithExtension) {
                $method = AutoLoginWithExtension::METHOD_KEY;
            }

            $prefix = $row->getPartner() . '_' . $method . '_' . $provider . '_' . $row->getId();
            $this->logger->info("searching files with prefix: $prefix");
            $existing = $this->s3client->listFiles($prefix);
            foreach ($existing as $file) {
                $item = new LogItem();
                $item->setFilename($file['Key'])
                    ->setUpdatedate($file['LastModified']);
                $files[] = $item;
                if (count($files) >= self::LOGS_LIMIT) {
                    break 2;
                }
            }

        }

        return $this->responseFactory->buildResponse(
            (new AdminLogsResponse())->setBucket($this->s3client->getBucket())->setFiles($files),
            self::API_VERSION
        );
    }

    /**
     * @Route("/log/{filename}", name="get_log", methods={"GET"})
     */
    public function getLog(string $filename): Response
    {
        $this->checkUserAccess();
        return new Response($this->s3client->getLog($filename), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename: ' . $filename
        ]);
    }

    /**
     * @Route("/statistic", name="get_statistic", methods={"POST"})
     */
    public function getStatistic(Request $httpRequest): Response
    {
        /** @var AdminStatisticRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, AdminStatisticRequest::class, self::API_VERSION);
        $this->checkUserAccess();

        $search = new Search($this->statClient);
        $search->addIndex('statistic')->addType('partners');

        $query = new Query([
            'query' => [
                'match' => ['Partner' => $request->getPartner()]
            ],
            'aggs' => [
                'providers' => [
                    'terms' => ['field' => 'Provider']
                ],
                'users' => [
                    'cardinality' => ['field' => 'UserID']
                ]
            ]
        ]);

        $result = $search->setQuery($query)->search();
        $resultRows = $result->getAggregations();

        $providers = [];
        foreach ($resultRows['providers']['buckets'] as $provider) {
            $providers[$provider['key']] = $provider['doc_count'];
        }

        $response = (new AdminStatisticResponse())
            ->setTotalAccounts($result->getTotalHits())
            ->setUniqueUsers($resultRows['users']['value'])
            ->setProviders($providers);

        return $this->responseFactory->buildResponse($response, self::API_VERSION);
    }

    /**
     * @Route("/ra-accounts/list/{id}", name="ra_accounts_list", methods={"GET"}, requirements={"id"="all|[a-z\d]{24}"})
     */
    public function listRaAccounts(string $id)
    {
        $this->checkUserAccess();
        $rows = [];
        $query = $this->dm->createQueryBuilder(RaAccount::class)->find();
        if ($id === 'all') {
            $query->sort('provider', 'asc')->sort('lastUseDate', 'asc');
        }
        else {
            $query->field('id')->equals($id);
        }
        /** @var RaAccount $account */
        foreach($query->getQuery()->execute() as $account) {
            $row = [
                'id' => $account->getId(),
                'provider' => $account->getProvider(),
                'login' => $account->getLogin(),
                'login2' => $account->getLogin2(),
                'login3' => $account->getLogin3(),
                'password' => $this->dm->getRepository(RaAccount::class)->decodePassword($account->getPass(), $this->aesKey),
                'email' => $account->getEmail(),
                'code' => $account->getErrorCode(),
                'state' => $account->getState(),
                'lockState' => $account->getLockState(),
                'lastUse' => $account->getLastUseDate()->format('Y-m-d H:i:s'),
                'answers' => [],
                'registerInfo' => [],
            ];
            foreach($account->getAnswers() as $ans) {
                $row['answers'][] = [$ans->getQuestion(), $ans->getAnswer()];
            }
            foreach($account->getRegisterInfo() as $data) {
                $row['registerInfo'][] = [$data->getKey(), $data->getValue()];
            }
            $rows[] = $row;
        }
        return new JsonResponse(['rows' => $rows]);
    }

    /**
     * @Route("/ra-accounts/edit/{id}", requirements={"id"="new|[a-z\d]{24}"}, name="ra_accounts_edit")
     */
    public function editRaAccount(Request $httpRequest, $id)
    {
        /** @var AdminRaAccount $request */
        $request = $this->requestFactory->buildRequest($httpRequest, AdminRaAccount::class, self::API_VERSION, false);
        $this->checkUserAccess();
        $request->clear();
        if (!$request->validate()) {
            return new JsonResponse(['data' => json_decode($httpRequest->getContent(), true), 'success' => false, 'error' => 'Invalid data']);
        }
        if ($id === 'new') {
            $account = new RaAccount();
            $this->dm->persist($account);
        }
        else {
            /** @var RaAccount $account */
            $account = $this->dm->getRepository(RaAccount::class)->find($id);
            if (!$account) {
                return new JsonResponse(['data' => json_decode($httpRequest->getContent(), true), 'success' => false, 'error' => 'Invalid account ID']);
            }
            if ($request->getReset()) {
                $account->setErrorCode(ACCOUNT_UNCHECKED);
            }
        }
        $account
            ->setProvider($request->getProvider())
            ->setLogin($request->getLogin())
            ->setLogin2($request->getLogin2())
            ->setLogin3($request->getLogin3())
            ->setPass($this->dm->getRepository(RaAccount::class)->encodePassword($request->getPassword(), $this->aesKey))
            ->setEmail($request->getEmail())
            ->setState($request->getState());
        foreach($account->getAnswers() as $answer) {
            $this->dm->remove($answer);
        }
        $account->setAnswers([]);
        foreach($request->getAnswers() as $answer) {
            if (strlen($answer->getQuestion()) > 0 && strlen($answer->getAnswer()) > 0) {
                $account->addAnswer(new RaAccountAnswer($answer->getQuestion(), $answer->getAnswer()));
            }
        }
        $account->setRegisterInfo([]);
        foreach($request->getRegisterInfo() as $data) {
            if (strlen($data->getKey()) > 0 && strlen($data->getValue()) > 0) {
                $account->addInfo(new RaAccountRegisterInfo($data->getKey(), $data->getValue()));
            }
        }
        try {
            $this->dm->flush();
        }
        catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'data' => json_decode($httpRequest->getContent(), true), 'error' => $e->getMessage()]);
        }
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/ra-accounts/bulk/{method}", requirements={"method"="delete|reset|setEnabled|setDisabled|setLocked|setReserve"}, name="ra_accounts_bulk")
     */
    public function bulkRaAccount(Request $httpRequest, $method)
    {
        $this->checkUserAccess();
        $ids = json_decode($httpRequest->getContent());
        if (!is_array($ids)) {
            throw new BadRequestHttpException();
        }
        foreach($ids as $id) {
            if (!is_string($id)) {
                continue;
            }
            $account = $this->dm->getRepository(RaAccount::class)->find($id);
            if (!$account) {
                continue;
            }
            switch($method) {
                case 'delete':
                    $this->dm->remove($account);
                    break;
                case 'reset':
                    $account->setErrorCode(ACCOUNT_UNCHECKED);
                    if ($bs = $this->dm->getRepository(BrowserState::class)->findOneBy(['key' => $account->getId()])) {
                        $this->dm->remove($bs);
                    }
                    break;
                case 'setEnabled':
                    $account->setState(RaAccount::STATE_ENABLED);
                    break;
                case 'setDisabled':
                    $account->setState(RaAccount::STATE_DISABLED);
                    break;
                case 'setLocked':
                    $account->setState(RaAccount::STATE_LOCKED);
                    break;
                case 'setReserve':
                    $account->setState(RaAccount::STATE_RESERVE);
                    break;
            }
        }
        $this->dm->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/balance/{type}", requirements={"type"="rucaptcha|antigate"}, name="ra_balance", methods={"POST"})
     */
    public function balance($type)
    {
        if (!in_array(ApiUser::ROLE_ADMIN, $this->tokenStorage->getToken()->getUser()->getRoles())
            && !in_array(ApiUser::ROLE_REWARD_AVAILABILITY, $this->tokenStorage->getToken()->getUser()->getRoles())
        ) {
            throw new AuthenticationException();
        }

        switch ($type) {
            case 'rucaptcha':
                $url = "https://rucaptcha.com/res.php?key=" . RUCAPTCHA_KEY . "&action=getbalance";
                break;
            case 'antigate':
                $url = "https://anti-captcha.com/res.php?key=" . ANTIGATE_KEY . "&action=getbalance";
                break;
        }
        if (!isset($url)) {
            $balance = null;
        } else {
            $balance = $this->curlGetBalance($url);
        }
        return new JsonResponse(['balance' => $balance]);
    }

    /**
     * @Route("/{method}/{requestId}", name="get_full_mongo_row", methods={"GET"})
     */
    public function getFullMongoRow(Request $httpRequest, string $method, string $requestId): Response
    {
        $this->checkUserAccess();
        $repo = $this->getMongoRepo('Check' . ucfirst($method));
        $row = $repo->find($requestId);
        if (!$row) {
            throw new NotFoundHttpException();
        }

        $request = $row->getRequest();
        $request['password'] = '';
        $row->setRequest($request);
        return $this->responseFactory->buildNoSwaggerResponse($row);
    }

    /**
     * @param $repoName (short ODM classname from AppBundle/Document)
     * @return ObjectRepository
     * @throws InvalidParametersException
     */
    private function getMongoRepo($repoName)
    {
        if (!class_exists("AppBundle\\Document\\$repoName")) {
            throw new InvalidParametersException('Unavailable method', []);
        }

        /** @var ManagerRegistry $mongo */
        $mongo = $this->container->get('doctrine_mongodb');
        /** @var ObjectRepository $obj */
        $obj = $mongo->getRepository('AppBundle:' . $repoName);
        return $obj;
    }

    private function checkUserAccess()
    {
        if (!in_array(ApiUser::ROLE_ADMIN, $this->tokenStorage->getToken()->getUser()->getRoles())) {
            throw new AuthenticationException();
        }
    }

    private function curlGetBalance($url)
    {
        $query = curl_init($url);
        curl_setopt($query, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($query, CURLOPT_TIMEOUT, 15);
        curl_setopt($query, CURLOPT_HEADER, false);
        curl_setopt($query, CURLOPT_FAILONERROR, false);
        curl_setopt($query, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($query);
        $code = curl_getinfo($query, CURLINFO_HTTP_CODE);

        if ($response === false || $code != '200') {
            $this->logger->critical(
                "GetBalance curl failed, http code: $code, network error: " . curl_errno($query) . ' ' . curl_error($query),
                ['curlResponse' => $response]
            );
        }

        curl_close($query);

        return round($response, 2);
    }

}