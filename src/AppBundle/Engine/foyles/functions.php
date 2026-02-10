<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFoyles extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.foyles.co.uk/Customer/Account/ViewAccount.aspx';
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])) {
            return parent::FormatBalance($fields, $properties);
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyGoProxies();
        $this->UseSelenium();
        $this->useFirefox();

        $this->seleniumOptions->addAntiCaptchaExtension = true;
//        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        $wrappedProxy = $this->services->get(WrappedProxyClient::class);
        $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
        $this->seleniumOptions->antiCaptchaProxyParams = $proxy;

        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.foyles.co.uk/account?tab=signin');

        $login = $this->waitForElement(WebDriverBy::id('emailInput-signin'), 20);
        $pass = $this->waitForElement(WebDriverBy::id('passwordInput-signin'), 0);
        $btn = $this->waitForElement(WebDriverBy::cssSelector('#signin-tabpanel .button-primary'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return $this->checkErrors();
        }

        if ($cookieAccept = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Accept All"]'), 0)) {
            $cookieAccept->click();
            sleep(1);
            $this->saveResponse();
        }

        $this->logger->debug('insert credentials');
        $login->sendKeys($this->AccountFields["Login"]);
        $pass->sendKeys($this->AccountFields["Pass"]);
        $this->driver->executeScript('let rem = document.getElementById("remember-me"); if (rem) rem.checked = true;');

        $this->logger->debug('check for captcha progress');
        $this->saveResponse();

        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
        }, 140);

        $this->saveResponse();

        /*
        if ($antigateError = $this->http->FindSingleNode('(//div[@class = "antigate_solver recaptcha error"])[1]')) {
            $this->DebugInfo = $antigateError;

            return false;
        }
        */

        if ($this->http->FindSingleNode('(//a[contains(text(), "Solving is in process...")])[1]')) {
            $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 20);
            $this->saveResponse();
        }
        $this->logger->debug('click by btn');
        $btn->click();

        return true;

        $this->http->GetURL('https://www.foyles.co.uk');

        if ($this->http->Response['code'] > 399) {
            return false;
        }
        sleep(3);
        $this->http->GetURL('https://www.foyles.co.uk/account?tab=signin');

        if (!$this->http->FindSingleNode('//input[@aria-label="Enter your email"]')) {
            return $this->checkErrors();
        }
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $data = [
            'email'                   => $this->AccountFields['Login'],
            'password'                => $this->AccountFields['Pass'],
            'remember'                => true,
            'passwordMigrateRequired' => true,
            'recaptcha'               => $captcha,
            'recaptchaType'           => 'v2',
        ];
        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN'     => $this->http->FindPreg('/window\.csrf_token = "([^"]+)/'),
            'X-XSRF-TOKEN'     => urldecode($this->http->getCookieByName('XSRF-TOKEN', 'www.foyles.co.uk', '/', true)),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.foyles.co.uk/api/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
        // parse form
        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        // login/pass
        $this->http->SetInputValue('ctl00$MainContent$txtUserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$MainContent$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$MainContent$chbRemember', "on");
        // required fields
        $this->http->Form['__EVENTTARGET'] = 'ctl00$MainContent$uxLoginButton';
        $this->http->Form['ctl00$MainContent$ctl00'] = null;
        $this->http->Form['ctl00$ddlSearchBy'] = 1;
        $this->http->Form['ctl00$ddlPrices'] = -1;
        $this->http->Form['ctl00$rblSearchType'] = 3;
        $this->http->Form['ctl00$ddlSort'] = 1;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are experiencing some technical difficulty. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'sorry we have encountered a problem with this page.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Cloudflare
        if ($this->http->FindSingleNode('//h2[contains(text(), "Checking if the site connection is secure")]')) {
            $this->DebugInfo = 'Cloudflare';

            throw new CheckRetryNeededException();
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "loyalty-balance"]/span | //div[@id = "errorModal"] | //div[@id = "signin-tabpanel"]//div[@class = "migrate-password"] | //div[@class = "antigate_solver recaptcha error"]'), 10);
        $migration = $this->waitForElement(WebDriverBy::cssSelector('#signin-tabpanel .migrate-password'), 0);

        if ($migration) {
            $this->throwProfileUpdateMessageException();
        }
        $error = $this->waitForElement(WebDriverBy::cssSelector('#errorModal .modal-body'), 0);

        if ($error) {
            $message = $error->getText();
            $this->logger->error($message);

            if (str_contains($message, 'Your login details are invalid, please try again')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $message;

            return false;
        }
        $this->saveResponse();

        // successful login
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->http->BodyContains('{"passwordRecreated":false,"hasLoyalty":true}', false)
            || $this->http->BodyContains('{"passwordRecreated":false,"hasLoyalty":false}', false)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "Checking if the site connection is secure")]')) {
            throw new CheckRetryNeededException();
        }

//        $this->sendNotification('profile updated // BS');

        // Error message
        $errorMessage = $this->http->FindSingleNode('//div[@class="ValidationMessage"]');
        // Wrong login/password
        if (
            strpos($errorMessage, 'Either the Email or Password entered was not recognised') !== false
            || strpos($errorMessage, 'Sorry this password is incorrect. Please try again or use the forgotten password option') !== false
        ) {
            throw new CheckException($errorMessage, ACCOUNT_INVALID_PASSWORD);
        } elseif (strpos($errorMessage, 'Sorry this email address is not registered.') !== false) {
            throw new CheckException("Sorry this email address is not registered. Please try a different email address or use Create a Login.", ACCOUNT_INVALID_PASSWORD);
        } else {
            $this->logger->error($errorMessage);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "antigate_solver recaptcha error"]')) {
            $this->DebugInfo = $message;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindSingleNode('(//div[@class = "loyalty-balance"]/span)[1]', null, true, self::BALANCE_REGEXP));
        $this->SetProperty('StampsTillNextReward', $stampsTillNextReward = $this->http->FindSingleNode('//div[@id = "loyalty-container"]//div[@class = "line-two"]', null, true, '/(\d+)/'));
        $this->http->GetURL('https://www.foyles.co.uk/account/profile');
        $firstName = $this->waitForElement(WebDriverBy::id('firstnameInput'), 5);
        $lastName = $this->waitForElement(WebDriverBy::id('lastnameInput'), 0);

        if (isset($firstName, $lastName)) {
            $this->SetProperty('Name', beautifulName("{$firstName->getAttribute('value')} {$lastName->getAttribute('value')}"));
        }
        $this->SetProperty('CardNumber', $cardNumber = $this->http->FindPreg("/'gtm-loyalty_card_number', '(\d+)/"));

        if (!empty($stampsTillNextReward)) {
            $this->AddSubAccount([
                'Code'        => 'foylesStamps' . $cardNumber,
                'DisplayName' => 'Stamps',
                'Balance'     => 10 - $stampsTillNextReward,
            ]);
        }
        $this->http->GetURL('https://www.foyles.co.uk/account/gift-cards');

        if (!$this->waitForElement(WebDriverBy::xpath('//p[text() = "You have not added any gift cards yet."]'), 3)) {
            $this->sendNotification('found gift cards // BS');
        }

        /*
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[@id = 'ctl00_MainContent_uxNameLabel']")));

        // Get data for subaccounts
        $foyaltyCards = $this->ParseFoyaltyCards();
        $giftCards = $this->ParseGiftCards();
        // Check for errors
        if (empty($foyaltyCards) && empty($giftCards)) {
            //# Sorry our Foyalty and Gift card system is currently unavailable
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry our Foyalty and Gift card system is currently unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // user has no any cards
            if ($this->http->FindSingleNode("//p[contains(text(), 'There are no Foyalty Cards registered on this Login.')]")
                && $this->http->FindSingleNode("//p[contains(text(), 'There are no Foyalty Cards registered on this Login.')]")
                && !empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }

            return;
        }

        // Subaccounts
        $subAccounts = array_merge($foyaltyCards, $giftCards);

        if (count($subAccounts) > 1) {
            $this->SetProperty("CombineSubAccounts", false);
        }

        if (!empty($subAccounts)) {
            $this->SetProperty('SubAccounts', $subAccounts);
            $this->SetBalanceNA();
        }
        */
    }

    public function ParseFoyaltyCards()
    {
        // sub accounts
        $subAccounts = [];
        // Foreach card
        $nodes = $this->http->XPath->query("//div[@class = 'FoyaltyTable']/div/div[not(@class)]//div[contains(@class, 'ContentTable')]");
        $this->logger->debug("Total {$nodes->length} cards were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $cardNumber = $this->http->FindSingleNode("div[@class = 'CellCardNumber']/text()[last()]", $node);
            $balance = $this->http->FindSingleNode("div[@class = 'CellPointsBalance']/text()[last()]", $node);
            $amountBalance = $this->http->FindSingleNode("div[@class = 'CellAmountBalance']/text()[last()]", $node);
            $subAccounts[] = [
                // Code
                'Code' => "foyles" . $cardNumber,
                // Type
                'DisplayName' => 'Foyalty Card ' . $cardNumber,
                // Card number
                'CardNumber' => $cardNumber,
                // Points Balance - Main Balance
                'Balance' => $balance,
                // Amount Balance
                'AmountBalance' => $amountBalance,
            ];
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $subAccounts;
    }

    public function ParseGiftCards()
    {
        // sub accounts
        $subAccounts = [];
        // Check for cards
        if ($this->http->FindSingleNode("//div[@class = 'GiftCardsTable']", null, true, '/There are no Gift Cards registered on this Login/ims') || $this->http->FindSingleNode("//p[contains(text(), 'Sorry our Foyalty and Gift card system is currently unavailable')]")) {
            return $subAccounts;
        }
        // Notification
        else {
            $this->sendNotification("Foyles - Gift Cards. Account with gift card");
        }

        // result
        return $subAccounts;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindSingleNode('//iframe[@title = "reCAPTCHA"]/@src', null, true, '/&k=([^&]+)/');
//        $this->logger->debug("data-sitekey: $key");
//        if (!$key) return false;
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            'pageurl' => 'https://www.foyles.co.uk/account?tab=signin',
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, '6Lcn_WkfAAAAAHeyJdJ3AziV2oSg3_BpRWx9ZU44', $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('(//div[@class = "loyalty-balance"]/span)[1]')) {
            return true;
        }

        return false;
    }
}
