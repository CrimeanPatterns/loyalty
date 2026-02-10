<?php


namespace AppBundle\Email;


use AppBundle\Document\RaAccount;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Service\Otc\Cache;
use AwardWallet\Common\API\Email\V2\ParseEmailResponse;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class CallbackProcessor
{

    /** @var LoggerInterface  */
    private $logger;

    /** @var Cache  */
    private $cache;

    /** @var RaAccountRepository */
    private $accountRepository;

    private $requestId;
    private $accountKey;

    public function __construct(LoggerInterface $logger, Cache $cache, DocumentManager $mongo)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->accountRepository = $mongo->getRepository(RaAccount::class);
    }

    public function processResponse(ParseEmailResponse $response): bool
    {
        if ($this->logger instanceof Logger) {
            $this->logger->pushProcessor([$this, 'addLogInfo']);
        }
        try {
            $this->requestId = $response->requestId;
            return $this->processOtc($response);
        }
        finally {
            if ($this->logger instanceof Logger) {
                $this->logger->popProcessor();
            }
        }
    }

    private function processOtc(ParseEmailResponse $response): bool
    {
        if (empty($response->providerCode) || empty($response->metadata) || empty($response->metadata->to) || empty($response->oneTimeCodes)) {
            return false;
        }
        if (!empty($response->metadata->receivedDateTime) && strtotime($response->metadata->receivedDateTime) < (time() - 15 * 60)) {
            $this->logger->info('otc is too old');
            return false;
        }
        $otc = trim($response->oneTimeCodes[0]);
        if (empty($otc))
            return false;
        $this->logger->info('received otc', ['code' => $otc]);
        foreach($response->metadata->to as $toAddress) {
            if (!empty($toAddress->email) && ($account = $this->match($response->providerCode, strtolower($toAddress->email)))) {
                $this->accountKey = $account->getId();
                $this->logger->info('matched to account');
                $this->cache->saveOtc($account->getId(), $otc);
                return true;
            }
        }
        return false;
    }

    private function match($providerCode, $toAddress): ?RaAccount
    {
        return $this->accountRepository->findOneBy(['provider' => $providerCode, 'email' => $toAddress]);
    }

    public function addLogInfo($record): array
    {
        if (!empty($this->requestId)) {
            $record['context']['requestId'] = $this->requestId;
        }
        if (!empty($this->accountKey)) {
            $record['context']['accountKey'] = $this->accountKey;
        }
        return $record;
    }


}