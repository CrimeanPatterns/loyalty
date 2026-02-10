<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

/**
 * @ignore
 */
class BrowserStateParser extends \TAccountChecker
{
    use ProxyList;

    private const STATE_KEY = 'test-state';

    public static $stateOnEnter;
    public static $stateOnExit;
    public static $loginOnEnter;
    public static $checkChecker;

    public static function reset() : void
    {
        self::$stateOnEnter = null;
        self::$stateOnExit = null;
        self::$loginOnEnter = null;
        self::$checkChecker = true;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        self::$loginOnEnter = $this->AccountFields["Login"];

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields) {
        self::$stateOnEnter = $this->State[self::STATE_KEY] ?? null;

        $this->State[self::STATE_KEY] = bin2hex(random_bytes(4));
        self::$stateOnExit = $this->State[self::STATE_KEY];

        return ['routes' => [
            [
                'num_stops' => 0,
                'times' => ['flight' => '11:20', 'layover' => '00:00'],
                'redemptions' => ['miles' => 55000, 'program' => 'british'],
                'payments' => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                'connections' => [
                    [
                        'departure' => [
                            'date' => date('Y-m-d', $fields['DepDate']) . ' 17:30',
                            'airport' => 'CDG',
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d', strtotime('+1 day', $fields['DepDate'])) . ' 06:50',
                            'airport' => 'JFK',
                        ],
                        'cabin' => 'business',
                        'flight' => ['BA0269'],
                        'airline' => 'BA',
                        'aircraft' => 'Boeing 787 jet',
                        'times' => ['flight' => '11:20', 'layover' => '00:00'],
                        'num_stops' => 0,
                    ],
                ],
                'award_type' => 'Standard Reward',
            ]
        ]];
    }

    public function checkInitBrowserRewardAvailability()
    {
        if (self::$checkChecker) {
            return;
        }
        self::$checkChecker = true;
        throw new \CheckException('InitBrowser has wrong parameters');
    }

}