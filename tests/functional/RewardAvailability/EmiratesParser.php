<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

/**
 * @ignore
 */
class EmiratesParser extends \TAccountChecker
{
    use ProxyList;

    public static $withRemember;
    public static $valueRemember;

    public static function reset(): void
    {
        self::$withRemember = null;
        self::$valueRemember = null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        if (self::$withRemember && self::$valueRemember) {
            $this->Answers['rememberNew'] = self::$valueRemember;
        }
        $this->SetWarning('this warning for check');
        return ['routes' => []];
    }

}