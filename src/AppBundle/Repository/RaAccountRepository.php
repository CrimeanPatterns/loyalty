<?php


namespace AppBundle\Repository;


use AppBundle\Document\RaAccount;
use AwardWallet\Common\DateTimeUtils;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\MongoDBException;

class RaAccountRepository extends DocumentRepository
{

    /**
     * @param string $password
     * @param $key
     * @return string
     */
    public function decodePassword(string $password, $key): string
    {
        if (0 === strlen($password) || 0 === strlen($key))
            return $password;
        return AESDecode(base64_decode($password), $key);
    }

    /**
     * @param string $password
     * @param $key
     * @return string
     */
    public function encodePassword(string $password, $key): string
    {
        if (0 === strlen($password) || 0 === strlen($key))
            return $password;
        return base64_encode(AESEncode($password, $key));
    }

    /**
     * @param string $provider
     * @param bool $checkParseLock
     * @param mixed $exceptLockout
     * @return RaAccount
     * @throws MongoDBException
     */

    public function findBestAccount(string $provider, bool $checkParseLock, ?bool $exceptLockout = false, ?array $priorityList = []): ?RaAccount
    {
        $checkWarmUp = $provider === 'skywards';
        if ($checkWarmUp) {
            // todo: tmp
            $this->createQueryBuilder()
                ->updateMany()
                ->field('provider')->equals($provider)
                ->field('warmedUp')->equals(RaAccount::WARMUP_LOCK)
                ->field('lastUseDate')->lt(new \DateTime('-15 minutes'))
                ->field('warmedUp')->set(RaAccount::WARMUP_NONE);
        }

        $builder = $this->createQueryBuilder();

        $builder
            ->addAnd($builder->expr()->field('provider')->equals($provider))
            ->addAnd($builder->expr()->field('state')->equals(RaAccount::STATE_ENABLED));

        if (!empty($priorityList)) {
            $builder->addAnd($builder->expr()->field('_id')->in($priorityList));
        }

        if ($checkWarmUp) {
            $builder->addAnd($builder->expr()->field('warmedUp')->notEqual(RaAccount::WARMUP_LOCK));
        }

        if ($checkParseLock) {
            $builder->addAnd($builder->expr()->field('lockState')->notEqual(RaAccount::PARSE_LOCK));
        }

        if ($exceptLockout) {
            $builder->addAnd($builder->expr()->field('errorCode')->notEqual(ACCOUNT_LOCKOUT));
        }

        // hold on ACCOUNT_PREVENT_LOCKOUT
        $builder->addAnd(
            $builder
                ->expr()
                ->addOr(
                    $builder
                        ->expr()
                        ->field('errorCode')
                        ->notEqual(ACCOUNT_PREVENT_LOCKOUT)
                )->addOr(
                    $builder
                        ->expr()
                        ->field('lastUseDate')
                        ->gte(new \DateTime('-1 hour'))
                )
        );

        if ($checkParseLock) {
            $account = $builder
                ->sort('lastUseDate', 'asc')
                ->findAndUpdate()
                ->returnNew()
                // update
                ->field('lockState')->set(RaAccount::PARSE_LOCK)
                ->field('lastUseDate')->set(new \DateTime())
                ->getQuery()
                ->execute();
            if ($account) {
                return $account;
            }
            if (!empty($priorityList)) {
                return $this->findBestAccount($provider, $checkParseLock, $exceptLockout, []);
            }
            return null;
        }

        /** @var RaAccount[] $accounts */
        $accounts = $builder
            ->sort('lastUseDate', 'asc')
            ->getQuery()
            ->execute();
        $bestAcc = null;
        $bestPrio = 100;
        foreach ($accounts as $account) {
            if (0 === ($prio = $this->calcPrio($account))) {
                return $account;
            }
            if ($prio < $bestPrio) {
                $bestPrio = $prio;
                $bestAcc = $account;
            }
        }
        if (!empty($priorityList) && $bestAcc === null) {
            return $this->findBestAccount($provider, $checkParseLock, $exceptLockout, []);
        }
        return $bestAcc;
    }

    /**
     * @param array $criteria
     * @param array $criteriaNot
     * @return RaAccount
     * @throws MongoDBException
     */
    public function findOneByWithNot(array $criteria, array $criteriaNot): ?RaAccount
    {
        $builder = $this->createQueryBuilder();
        /** @var RaAccount[] $accounts */

        foreach ($criteria as $f => $v) {
            $builder = $builder->field($f)->equals($v);
        }
        foreach ($criteriaNot as $f => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $builder = $builder->field($f)->notEqual($vv);
                }
            } else {
                $builder = $builder->field($f)->notEqual($v);
            }
        }
        $accounts = $builder
            ->sort('lastUseDate', 'asc')
            ->getQuery()
            ->execute()->toArray();

        if (!empty($accounts)) {
            return array_shift($accounts);
        }
        return null;
    }

    private function calcPrio(RaAccount $account): int
    {
        switch ($account->getErrorCode()) {
            case ACCOUNT_LOCKOUT:
                return 10;
            case ACCOUNT_INVALID_PASSWORD:
                return 9;
            case ACCOUNT_QUESTION:
                return $this->calcTimeout($account, 20, 5);
            default:
                return 0;
        }
    }

    private function calcTimeout(RaAccount $account, int $timeoutMinutes, int $prio): int
    {
        return (time() - $account->getLastUseDate()->getTimestamp()) > $timeoutMinutes * 60 ? 0 : $prio;
    }

}