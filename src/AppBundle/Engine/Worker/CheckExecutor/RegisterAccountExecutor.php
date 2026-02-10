<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RaAccountAnswer;
use AppBundle\Document\RaAccountRegisterInfo;
use AppBundle\Document\RegisterAccount;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\StopCheckException;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Service\ApiValidator;
use AppBundle\Service\Otc\Cache;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\OneTimeCode\ProviderQuestionAnalyzer;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class RegisterAccountExecutor extends CheckAccountExecutor
{

    protected $RepoKey = RegisterAccount::METHOD_KEY;

    private Cache $otcCache;

    private int $otcWaitTime;

    private string $parseMode;

    public function __construct(
        LoggerInterface $logger,
        Connection $connection,
        Connection $shared_connection,
        DocumentManager $manager,
        Loader $loader,
        CheckerFactory $factory,
        S3Custom $s3Client,
        ProducerInterface $callbackProducer,
        MQSender $mqSender,
        SerializerInterface $serializer,
        ProducerInterface $delayedProducer,
        \Memcached $memcached,
        $aesKey,
        ApiValidator $validator,
        ItinerariesFilter $itinerariesFilter,
        MasterSolver $solver,
        Watchdog $watchdog,
        EventDispatcherInterface $eventDispatcher,
        Util $awsUtil,
        CurrencyConverter $currencyConverter,
        TimeCommunicator $timeCommunicator,
        ParserFactory $parserFactory,
        ParserRunner $parserRunner,
        ClientFactory $clientFactory,
        Cache $otcCache,
        $otcWaitTime,
        string $parseMode,
        ProviderInfoFactory $providerInfoFactory
    ) {
        parent::__construct($logger, $connection, $shared_connection, $manager, $loader, $factory, $s3Client, $callbackProducer, $mqSender,
            $serializer, $delayedProducer, $memcached, $aesKey, $validator, $itinerariesFilter, $solver, $watchdog,
            $eventDispatcher, $awsUtil, $currencyConverter, $timeCommunicator, $parserFactory, $parserRunner, $clientFactory, $providerInfoFactory);

        $this->otcCache = $otcCache;
        $this->otcWaitTime = $otcWaitTime;
        $this->parseMode = $parseMode;
    }

    protected function buildChecker($request, BaseDocument $row): \TAccountChecker
    {
        /** @var RegisterAccountRequest $request */

        $checker = $this->factory->getRewardAvailabilityRegister($request->getProvider());
        $checker->TransferFields = $request->getFields();
        $checker->AccountFields['ParseMode'] = $this->parseMode;

        if (isset($checker->TransferFields['Email'], $checker->TransferFields['Password'])) {
            $account = new RaAccount(
                $request->getProvider(),
                $checker->TransferFields['Email'],
                $this->manager->getRepository(RaAccount::class)->encodePassword($checker->TransferFields['Password'], $this->aesKey),
                $checker->TransferFields['Email']
            );
            $account->setState(RaAccount::STATE_DISABLED);

            $this->manager->persist($account);
            $this->manager->flush();
            $checker->AccountFields['AccountKey'] = $account->getId();
            $this->logger->info('create ra register account', ['email' => $checker->TransferFields['Email'], 'accountKey' => $account->getId(), 'provider' => $account->getProvider()]);
        }

        return $checker;
    }

    public function processRequest($request, $response, $row)
    {
        if ($request instanceof RegisterAccountRequest && isset($request->getFields()['Email'])) {
            $email = $request->getFields()['Email'];
            $account = $this->manager->getRepository(RaAccount::class)->findOneBy([
                'provider' => $request->getProvider(),
                'email' => $email
            ]);
            if ($account) {
                $response->setMessage('process stopped');
                throw new StopCheckException('provider ' . $request->getProvider() . ' already has account with email ' . $email);
            }
        }
        BaseExecutor::processRequest($request, $response, $row);
    }

    protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner)
    {
        $response
            ->setState($checker->ErrorCode)
            ->setMessage($checker->ErrorMessage)
            ->setUserData($request->getUserdata());

        if ($this->partnerCanDebug($partner)) {
            $response->setDebugInfo($checker->DebugInfo);
        }
    }

    private function runParentProcessChecker($checker, $request, $row, $fresh){
        try {
            parent::processChecker($checker, $request, $row, $fresh);
        } catch (\CheckException $e) {
            // this could throw from InitBrowser. Registration should be stopped and tmp-account should be deleted
            $checker->ErrorCode = $e->getCode();
            $checker->DebugInfo = $e->getMessage();
            $checker->ErrorMessage = "Sorry, we couldn't complete your request. Try again later.";
            $this->removeAccount($checker);
        }
    }

    protected function removeAccount(\TAccountChecker $checker)
    {
        $accountKey = $checker->AccountFields['AccountKey'] ?? null;
        if (null === $accountKey) {
            return;
        }
        /** RaAccount $account */
        $account = $this->manager->getRepository(RaAccount::class)->find($accountKey);
        if (!$account) {
            return;
        }

        if ($account->getState() === RaAccount::STATE_INACTIVE) {
            $checker->DebugInfo .= "\ncheck DB with inactive account: accountKey = " . $accountKey;
            $checker->logger->notice("account not deleted. saved as inactive: accountKey = " . $accountKey);
            return;
        }
        $this->manager->remove($account);
        $this->manager->flush();
    }

    protected function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, $fresh = true)
    {
        $this->runParentProcessChecker($checker, $request, $row, $fresh);
        /** @var RaAccount $account */
        /** @var RegisterAccount $row */
        try {
            if (ACCOUNT_QUESTION === $checker->ErrorCode
                && !empty($checker->Question)
                && ProviderQuestionAnalyzer::isQuestionOtc($request->getProvider(), $checker->Question)
                && !empty($checker->AccountFields['AccountKey'])) {
                if (($account = $this->manager->getRepository(RaAccount::class)->find($checker->AccountFields['AccountKey']))) {
                    $account->setQuestion($checker->Question);
                    $this->logger->info('ra register account otc question, updated', [
                        'email' => $checker->TransferFields['Email'],
                        'accountKey' => $account->getId(),
                        'errorCode' => $checker->ErrorCode,
                        'provider' => $account->getProvider()
                    ]);
                    $this->saveRegisteredAccount($account, $checker, $row, RaAccount::STATE_INACTIVE);
                    $this->manager->flush();
                }
                $mark = time();
                $this->logger->info(sprintf('otc question detected, waiting for it, max %d seconds',
                    $this->otcWaitTime), ['accountKey' => $checker->AccountFields['AccountKey']]);
                while (time() - $mark < $this->otcWaitTime) {
                    if ($this->otcCache->isLocked($checker->AccountFields['AccountKey'])) {
                        $this->logger->info('register account was otc locked',
                            ['email' => $checker->TransferFields['Email'], 'accountKey' => $checker->AccountFields['AccountKey']]);
                        return;
                    }
                    if ($otc = $this->otcCache->getOtc($checker->AccountFields['AccountKey'])) {
                        $this->logger->info('otc found',
                            ['email' => $checker->TransferFields['Email'], 'accountKey' => $checker->AccountFields['AccountKey'], 'duration' => time() - $mark]);
                        $checker->Answers[$checker->Question] = $otc;
                        $this->otcCache->deleteOtc($checker->AccountFields['AccountKey']);
                        $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
                        $checker->ErrorMessage = 'Unknown Error';
                        $this->runParentProcessChecker($checker, $request, $row, false);
                        return;
                    }
                    sleep(1);
                }
                $this->logger->info('timed out waiting for otc',
                    ['accountKey' => $checker->AccountFields['AccountKey']]);
            }
        } finally {
            if (!empty($checker->AccountFields['AccountKey'])
                && ($account = $this->manager->getRepository(RaAccount::class)->find($checker->AccountFields['AccountKey']))
            ) {
                $state = RaAccount::STATE_RESERVE;

                $userData = @json_decode($request->getUserData(), true);
                if (isset($userData['state']) && is_numeric($userData['state'])
                    && in_array((int)$userData['state'], RaAccount::STATES)
                ) {
                    $state = (int)$userData['state'];
                }
                if (!$this->saveRegisteredAccount($account, $checker, $row, $state)) {
                    if (ACCOUNT_CHECKED === $checker->ErrorCode) {
                        $checker->ErrorMessage .= ' (account not saved to DB)';
                    }
                    if (ACCOUNT_QUESTION !== $checker->ErrorCode) {
                        $this->manager->remove($account);
                    }
                }
                $this->manager->flush();
            }
        }
    }

    private function saveRegisteredAccount(RaAccount $account, $checker, RegisterAccount $row, $state): bool
    {
        if (!in_array($checker->ErrorCode, [ACCOUNT_CHECKED, ACCOUNT_QUESTION], true)) {
            return false;
        }
        if ($checker->ErrorCode === ACCOUNT_QUESTION && $state !== RaAccount::STATE_INACTIVE) {
            return true;
        }
        $result = json_decode($checker->ErrorMessage);
        if (!isset($result) || !property_exists($result, 'status') || $result->status !== 'success') {
            return false;
        }

        if (isset($result->login) && !empty($result->login)) {
            $account->setLogin($result->login);
        } else {
            $this->logger->error('can not save account information: login is empty');
            return false;
        }
        if (isset($result->login2)) {
            $account->setLogin2($result->login2);
        }
        if (isset($result->login3)) {
            $account->setLogin3($result->login3);
        }
        if (isset($result->questions)) {
            $account->setAnswers([]);
            foreach ($result->questions as $answer) {
                if (strlen($answer->question) > 0 && strlen($answer->answer) > 0) {
                    $account->addAnswer(new RaAccountAnswer($answer->question, $answer->answer));
                }
            }
        }
        if (isset($result->registerInfo)) {
            $account->setRegisterInfo([]);
            foreach ($result->registerInfo as $data) {
                if (strlen($data->key) > 0 && strlen($data->value) > 0) {
                    $account->addInfo(new RaAccountRegisterInfo($data->key, $data->value));
                }
            }
        }
        if (isset($result->active) && $result->active === false) {
            $state = RaAccount::STATE_INACTIVE;
        }
        $account->setState($state);
        $checker->logger->info('account saved to DB, state=' . $account->getState());
        $checker->ErrorMessage = $result->message . ' (account saved to DB, state=' . $account->getState() . ')';

        $row->setIsChecked(!isset($result->active) || $result->active !== false);

        if (ACCOUNT_QUESTION === $checker->ErrorCode && $row->getIsChecked()) {
            if (isset($result->active) && $result->active) {
                $this->logger->error('cannot be an active account if the process step is a question');
            }
            $row->setIsChecked(false);
            $account->setState(RaAccount::STATE_INACTIVE);
        }
        $row->setAccId($checker->AccountFields['AccountKey']);
        $this->manager->persist($row);

        return true;
    }

    public function getMongoDocumentClass(): string
    {
        return RegisterAccount::class;
    }

    protected function getRequestClass(int $apiVersion): string
    {
        return RegisterAccountRequest::class;
    }

    protected function getResponseClass(int $apiVersion): string
    {
        return RegisterAccountResponse::class;
    }

    protected function saveLogs($response, \TAccountChecker $checker)
    {
        $arrayResponse = json_decode($this->serializer->serialize($response, 'json'), true);

        $checker->logger->info('Loyalty Response', ['Header' => 2]);
        $checker->logger->info(var_export($arrayResponse, true), ['pre' => true]);

        if ($checker->http !== null && $this->uploadOnS3) {
            $this->s3Client->uploadCheckerLogToBucket(
                $response->getRequestId(),
                $checker->http->LogDir,
                $checker->AccountFields,
                $this->repo
            );
        }
    }

    protected function saveResponseGeneral($response, &$row)
    {
        $canDebug = $this->partnerCanDebug($row->getPartner());
        if (!$canDebug) {
            $response->setDebuginfo(null);
        }

        $row->setUpdatedate(new \DateTime());
        $this->manager->persist($row);
        $this->manager->flush();
    }

    protected function supportPackageCallback()
    {
        return false;
    }

    public function sendCallback(LoyaltyRequestInterface $request, $row)
    {
        BaseExecutor::sendCallback($request, $row);
    }

    public function getMethodKey(): string
    {
        return RegisterAccount::METHOD_KEY;
    }

}