<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerChangs extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->setProxyNetNut();

        $this->useChromePuppeteer();
        $this->seleniumOptions->userAgent = null;
        /*
        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->useFirefox();
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::firefox()]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email Address must be a valid format.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.pfchangs.com/account/sign-in');
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "email"]'), 3);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "password"]'), 0);

        if (!isset($login, $pwd)) {
            $this->saveResponse();

            if ($this->http->FindSingleNode('//h2[contains(text(), "Forbidden - ID:")]')) {
                $this->DebugInfo = "Forbidden";

                return false;
            }

            return $this->loginSuccessful();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $login->click();

        if ($validationError = $this->waitForElement(WebDriverBy::xpath('//span[@class = "form__error active"]'), 0)) {
            $this->saveResponse();
            $message = $validationError->getText();
            $this->logger->error("[Error]: {$message}");

            if (stripos($message, 'must be a valid format') !== false) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "form-signup__button") and not(@disabled)]'), 0);

        if (!$btn) {
            return false;
        }

        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Temporarily unavailable due to maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "form__error active"] | //div[contains(@class, "dashboard-offers")]//button[contains(@class, "card-panel__toggle")] | //button[contains(text(), "JOIN GOLD")]'), 20);
        $this->saveResponse();
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->loginSuccessful()) {
            return true;
        }

        $error = $this->http->FindSingleNode('//span[@class = "form__error active"]');

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if (stripos($error, 'Sorry, the email address and or password you entered is invalid') !== false
                || stripos($error, "We've upgraded our system which requires you to update your password") !== false
                || stripos($error, "Sorry, we couldn't find an account with that email address") !== false
                || stripos($error, "Finish setting up your account by resetting your password.") !== false
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (stripos($error, 'Our system is experiencing an issue. Please try again') !== false) {
                $this->DebugInfo = 'Request blocked';

                return false;
            }

            $this->DebugInfo = $error;

            return false;
        }

        // AccountID: 6502680
        if ($this->http->FindNodes('//button[contains(text(), "JOIN GOLD")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // https://www.pfchangs.com/account/overview
        if ($this->waitForElement(WebDriverBy::cssSelector('.dashboard-offers button.card-panel__toggle'), 2)) {
            $this->driver->executeScript('document.querySelector(".dashboard-offers button.card-panel__toggle").click();');
            sleep(1);
            $this->saveResponse();
        }
        // Balance - Points earned
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "points-bar__points"]/span', null, true, '#(\d+)/\d+ PTS#'));
        // Points to next reward
        $this->SetProperty('PointsToNextReward', $this->http->FindSingleNode('//div[@class = "points-bar__content"]/p[1]', null, true, "/You're only (\d+) points away from your next .+ Reward/"));
        // Expiration date
        if ($exp = strtotime($this->http->FindSingleNode('//i[@class = "rewards-card__info"]', null, true, '#Points expire \d+ months after your most recent transaction on (\d+/\d+/\d+)#') ?? '')) {
            $this->SetExpirationDate($exp);
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg('#<p [^>]+ class="member-panel__name">NAME</p><p [^>]+ class="member-panel__value">([\w ]+)</p>#')));
        // CardNumber
        $this->SetProperty('CardNumber', $this->http->FindPreg('#<p.+?class="member-panel__name">GOLD REWARDS ID</p>.+?class="member-panel__value">(\d+)</p>#'));
        // Rewards
        $rewards = $this->http->XPath->query('//div[starts-with(@class, "card-panel__header text-left")]');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $balance = $this->http->FindSingleNode('span[starts-with(@class, "card-panel__info")][1]', $reward, true, "/(\d+) X/");
            $name = $this->http->FindSingleNode('span[starts-with(@class, "card-panel__info")][2]', $reward);
            $exp = $this->http->FindSingleNode('span[starts-with(@class, "card-panel__info")][3]', $reward, true, '#Expires: (\d{4}-\d{2}-\d{2})#');

            if (!$name) {
                continue;
            }

            $this->AddSubAccount([
                'Code'           => md5($name),
                'DisplayName'    => $name,
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($exp, false),
            ]);
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['SubAccounts'])
        ) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindSingleNode('//p[contains(text(), "You currently have no Chang’s Ca$h or Offers")]'));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//div[@class = "points-bar__points"]/span')
            ?? $this->http->FindNodes('//div[contains(@class, "dashboard-offers")]//button[contains(@class, "card-panel__toggle")]')
        ) {
            return true;
        }

        return false;
    }
}
