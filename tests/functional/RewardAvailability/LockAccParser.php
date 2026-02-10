<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

/**
 * @ignore
 */
class LockAccParser extends \TAccountChecker
{
    use ProxyList;

    public static $numRun;
    public static $usedLogin;

    public static function reset(): void
    {
        self::$numRun = null;
        self::$usedLogin = null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        self::$numRun = $this->attempt;
        self::$usedLogin = $this->AccountFields['Login'];
        if ($this->AccountFields['Login'] === 'lockAccount') {
            throw new \CheckException('Your account disabled', ACCOUNT_LOCKOUT);
        }
        if ($this->AccountFields['Login']==='prevLockAccount') {
                throw new \CheckException('Your account disabled', ACCOUNT_PREVENT_LOCKOUT);
        }
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->SetWarning('this warning for check');
        return ['routes' => []];
    }

}