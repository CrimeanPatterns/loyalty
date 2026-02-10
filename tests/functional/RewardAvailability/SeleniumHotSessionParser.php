<?php

namespace Tests\Functional\RewardAvailability;


/**
 * @ignore
 */
class SeleniumHotSessionParser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public static $accountKey;
    public static $prefix;
    public static $message;
    public static $provider;
    public static $numRun;
    public static $saveSession;

    public static function reset(): void
    {
        self::$prefix = null;
        self::$accountKey = null;
        self::$provider = null;
        self::$numRun = 0;
        self::$saveSession = true;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->logger->info("SeleniumHotSessionParser InitBrowser");
        $this->UseSelenium();
        $this->useChromium();
        self::$message = null;

        self::$numRun++;
        if (empty(self::$prefix)) {
            self::$prefix = self::class;
        }
        $account = $this->AccountFields['AccountKey'] ?? null;
        if ($account) {
            self::$accountKey = $account;
        }

        $this->seleniumRequest->setHotSessionPool(self::$prefix, self::$provider, self::$accountKey);
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        $this->logger->info("SeleniumHotSessionParser LoadLoginForm");
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $data = [
            'prefix' => self::$prefix,
            'accountKey' => self::$accountKey,
            'provider' => self::$provider,
            'numRun' => self::$numRun,
            'saveSession' => self::$saveSession
        ];
        $this->logger->notice(var_export($data, true));
        if (strpos($this->http->currentUrl(), 'google.com') !== false) {
            self::$message = 'got hot';
        } else {
            self::$message = 'no hot';
        }

        $this->http->GetURL('http://google.com');

        if (self::$numRun == 3) //for check no hot (session reset)
            throw new \CheckException('bad params', ACCOUNT_ENGINE_ERROR);

        if (self::$saveSession){
            $this->keepSession(true);
        }

        $this->SetWarning('no flights');
        return [];

    }
}