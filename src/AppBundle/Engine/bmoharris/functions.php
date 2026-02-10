<?php

class TAccountCheckerBmoharris extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.bmoharrisrewards.com/';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $this->setKeepProfile(true);
        */
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        $this->http->GetURL('https://www.bmoharrisrewards.com/externalLogin.htm');
        $this->http->GetURL('https://www1.bmoharris.com/www/#/login');
//        $login = $this->waitForElement(WebDriverBy::xpath('//input[@data-id = "username"]'), 5);
        $login = $this->waitForElement(WebDriverBy::xpath('//*[@formcontrolname="username"]'), 5);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@data-id = "password" or @name="password"]'), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath('//button[@data-id="primary-button"] | //*[@data-id="primary-button"]'), 0);
        $this->saveResponse();

        if (empty($login) || empty($pass) || empty($submitButton)) {
            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }

        $this->driver->executeScript('
            function triggerInput(selector, enteredValue) {
                let input = document.querySelector(selector);
                input.dispatchEvent(new Event(\'focus\'));
                input.dispatchEvent(new KeyboardEvent(\'keypress\',{\'key\':\'a\'}));
                let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, \'value\').set;
                nativeInputValueSetter.call(input, enteredValue);
                let inputEvent = new Event("input", { bubbles: true });
                input.dispatchEvent(inputEvent);
            }
            triggerInput(\'input[name="username"]\', \'' . $this->AccountFields['Login'] . '\');
        ');

//        $login->clear();
//        $login->sendKeys($this->AccountFields['Login']);

        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();
        $submitButton->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "error-summary")]
            | //h1[contains(text(), "Get your code")]
            | //h1[contains(text(), "Session timed out.")]
            | //span[@class = "slogan__first-name"]
        '), 10);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//form[@translationpath="login"]//span[contains(@class, "error-summary")]')) {
            $this->logger->error("[Error]: {$message}");

            // prevent wrong error message
            sleep(2);
            $this->saveResponse();

            if ($maintenance = $this->http->FindSingleNode('//span[contains(text(), "We’ve pulled into the shop for some routine maintenance. We’ll be up and running again in no time, so check back soon!")]')) {
                throw new CheckException($maintenance, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Hmm. The information you provided doesn\'t match our records. Try again?')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindSingleNode('//ion-label[contains(text(), "I have read and agreed to the Digital Banking Agreement.")]')) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $input = $this->waitForElement(WebDriverBy::xpath("//input[@formcontrolname = 'otp']"), 0);
        $this->saveResponse();

        if (!$input) {
            $this->logger->error('Failed to find input field for "answer"');

            return false;
        }

        $input->clear();
        $input->sendKeys($this->Answers[$this->Question]);
        // do not keep Advanced Access Code
        unset($this->Answers[$this->Question]);

        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@data-id = 'primary-button' and contains(., 'SUBMIT')]"), 0);
        $this->saveResponse();

        if (!$submitButton) {
            $this->logger->error('Failed to find submit button');

            return false;
        }
        $submitButton->click();

        sleep(5);

        $error = $this->waitForElement(WebDriverBy::xpath("//strong[contains(text(), 'Invalid code. Please try again.')]"), 0); //todo

        if ($error) {
            $this->logger->debug("Ask question. Wrong Code.");
            $input->clear();
            $this->holdSession();
            $this->AskQuestion($this->Question, $error->getText(), "Question");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "slogan__first-name"]'), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@class = "slogan__first-name"]')));

        $dinersClubLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@data-ana, "diners club") and contains(@data-ana, "link")]'), 0);
        $this->saveResponse();

        if (!$dinersClubLink) {
            return;
        }

        try {
            $dinersClubLink->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->saveResponse();
            $this->driver->executeScript('document.querySelector(\'a[data-ana *= "diners club"][data-ana *= "link"]\').click();');
        }

        $viewLink = $this->waitForElement(WebDriverBy::xpath('//a[@data-id="view-and-redeem-button"]'), 10);
        $this->saveResponse();
        // open rewards site in the same window
        $this->driver->executeScript('var windowOpen = window.open; window.open = function(url) { windowOpen(url, \'_self\'); }');

        if (!$viewLink) {
            return;
        }

        try {
            $viewLink->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->saveResponse();
            $this->driver->executeScript('document.querySelector(\'a[data-id="view-and-redeem-button"]\').click();');
        }
        /*
        $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Account Activity")]'), 3);
        $this->saveResponse();

        $this->http->GetURL("https://clubrewardsus.com/home/get.htm");
        */
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "exposed-rewards-value"]'), 10);
        $this->saveResponse();
        // Current Points
        $this->SetBalance($this->http->FindSingleNode('//div[@id = "exposed-rewards-value"]//span[@id and not(span)]'));
        // Name
        $name = $this->http->FindSingleNode('//span[@class = "dashboard_name"]');

        if ($name) {
            $this->SetProperty('Name', beautifulName($name));
        }

        $activityLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Account Activity")]'), 0);
        $this->saveResponse();

        if ($activityLink) {
            $activityLink->click();
        } else {
            $this->http->GetURL("https://clubrewardsus.com/accountActivity/get.htm");
        }

        $this->waitForElement(WebDriverBy::xpath('//h4[contains(text(), "Account Activity")]'), 5);
        $this->saveResponse();
        // Earnings
        $this->SetProperty('Earnings', $this->http->FindSingleNode('//div[contains(@class, "accountactivity-summary-div") and contains(., "Earnings")]//span[@id and not(span)]'));
        // Redemptions
        $this->SetProperty('Redemptions', $this->http->FindSingleNode('//div[contains(@class, "accountactivity-summary-div") and contains(., "Redemptions")]//span[@id and not(span)]'));
        // Other Activity
        $this->SetProperty('OtherActivity', $this->http->FindSingleNode('//div[contains(@class, "accountactivity-summary-div") and contains(., "Other Activity")]//span[@id and not(span)]'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[@class = "slogan__first-name"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We are currently working on improving your online banking experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "delivery-method__button") and contains(., "EMAIL")]'), 0);
        $this->saveResponse();

        if (
            $this->http->FindSingleNode('//button[contains(@class, "delivery-method__button") and contains(., "EMAIL")]/following-sibling::div/small[
                contains(text(), "We\'re unable to verify you at this email address. Please Choose another option.")
                or contains(text(), "We\'re unable to verify you at this contact method. Please Choose another option.")
            ]')
        ) {
            $question = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "delivery-method__button") and contains(., "TEXT")]'), 0);
        }

        if (!isset($question)) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $sentTo = $question->getText();

        $question->click();
        $this->holdSession();

        $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Enter your 6-digit code now")]'), 10);
        $this->saveResponse();

        $this->Question = "Please enter your 6-digit code which was delivered by {$sentTo}";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
