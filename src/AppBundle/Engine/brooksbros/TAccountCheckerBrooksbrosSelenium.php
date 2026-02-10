<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBrooksbrosSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
//        $this->disableImages();
        $this->useChromium();
        $this->useCache();
        $this->keepCookies(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
//        $this->http->GetURL("https://citiretailservices.citibankonline.com/RSnextgen/svc/launch/index.action?siteId=PLCN_BROOKSBROTHERS#signon");
        $this->http->GetURL("https://citiretailservices.citibankonline.com/RSnextgen/svc/launch/index.action?siteId=PLCN_BROOKSBROTHERS");

        $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@name = "userId"]'), 20);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 3);
        $this->driver->executeScript("var overlay1 = $('#site_info-popup'); if (overlay1) overlay1.remove();");
        $this->driver->executeScript("var overlay2 = document.getElementById('site_info-screen'); if (overlay2) overlay2.hidden = true;");
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//form[@data-form-id = \'sign_on\']//button'), 3);
        $this->saveResponse();

        if (empty($loginField) || !$passwordInput || !$loginButton) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $this->logger->debug("Set login");
        $loginField->clear();
        $loginField->sendKeys($this->AccountFields["Login"]);
        $this->logger->debug("Set pass");
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("Click btn");
        $this->driver->executeScript("$('button:contains(\"Sign On\")').click();");
//        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//title[contains(text(), "BrooksBrothers.com - We\'ll be back soon!")]')) {
            throw new CheckException("Due ti scheduled maintenance, our site is temporarily unavailable. Please accept our apologies - and please check back shortly.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 30;
        sleep(5);

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");

            if ($remindLater = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Remind Me Later')]"), 0, true)) {
                $remindLater->click();
                $this->waitForElement(WebDriverBy::xpath("//span[@data-points-balance]"), 5);
            }

            // look for logout link
            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Off')]"), 0, true);
            $this->saveResponse();

            if ($logout && !empty($logout->getText())) {
                $this->waitForElement(WebDriverBy::xpath('//span[@data-points-balance]'), 5);
                $this->saveResponse();

                return true;
            }
            // Identity Checkpoint
            if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Identity Checkpoint")]'), 0)) {
                $this->saveResponse();

                return $this->processSecurityCheckpoint();
            }// if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Identity Checkpoint")]'), 0)) {
            /*
             * The information you entered does not match our records.
             * For your protection, multiple unsuccessful log-ins will result in a temporary account lockout.
             */
            if ($errors = $this->waitForElement(WebDriverBy::xpath('//section[
                    contains(text(), "The information you entered") and contains(., "does not match our records")
                    or contains(text(), "Something you entered wasn\'t right. Try again or retrieve your ")
                ]
                | //span[contains(text(), "Your User ID must be five to 50")]
            '), 0)) {
                throw new CheckException(strip_tags($errors->getText()), ACCOUNT_INVALID_PASSWORD);
            }
            // We encountered a problem processing your request. Please try again later.
            if ($errors = $this->waitForElement(WebDriverBy::xpath("//*[
                    contains(text(), 'We encountered a problem processing your request. Please try again later.')
                    or contains(text(), 'We have had a problem processing your request. Please try again later.')
                ]
                | //section[contains(text(), 'Your request for a receipt could not be processed at this time.')]
                "), 0)
            ) {
                throw new CheckException($errors->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Please Verify Your Card Information
            if ($errors = $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Please Verify Your Card Information')] | //h1//div[@data-page-title = 'data-page-title' and contains(text(), 'Update Your Profile')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $question_1 = $this->waitForElement(WebDriverBy::xpath('//div[input[@name = "answer_1"]]/preceding-sibling::label/span[@class = "question"]'), 0);

        if (!$question_1) {
            return false;
        }

        if (!isset($this->Answers[$question_1->getText()])) {
            $this->logger->debug("asq question 1");
            $this->holdSession();
            $this->AskQuestion($question_1->getText(), null, "Question");

            return false;
        }
        $question_2 = $this->waitForElement(WebDriverBy::xpath('//div[input[@name = "answer_2"]]/preceding-sibling::label/span[@class = "question"]'), 0);
        $this->saveResponse();

        if (!$question_2) {
            return false;
        }

        if (!isset($this->Answers[$question_2->getText()])) {
            $this->logger->debug("asq question 2");
            $this->holdSession();
            $this->AskQuestion($question_2->getText(), null, "Question");

            return false;
        }

        $answer_1 = $this->waitForElement(WebDriverBy::xpath('//input[@name = "answer_1"]'), 0);
        $answer_2 = $this->waitForElement(WebDriverBy::xpath('//input[@name = "answer_2"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
        $this->saveResponse();

        if (!$question_1 || !$question_2 || !$answer_1 || !$answer_2 || !$button) {
            return false;
        }
        $answer_1->clear();
        $answer_1->sendKeys($this->Answers[$question_1->getText()]);
        $answer_2->clear();
        $answer_2->sendKeys($this->Answers[$question_2->getText()]);
        $button->click();

        //  you entered is incorrect.
        // todo
//        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), " you entered is incorrect.")]'), 10)) {
//            $this->logger->notice("resetting answers");
//            $this->Answers = [];
//            $this->holdSession();
//            $this->AskQuestion($question_1->getText(), null, "Question");
//            return false;
//        }
        $this->logger->debug("success");
        $this->logger->debug("CurrentUrl: " . $this->http->currentUrl());
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath('//span[@data-points-balance]'), 5);
        $this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Rewards Points Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@data-points-balance]", null, true, "/([\d\-\.\,]+)/i"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@data-page-title]", null, true, "/Welcome\,\s*(.+)/i")));
        // Account ending in ...
        $this->SetProperty("Number", $this->http->FindPreg("/>\s*Ending\s*in\s*([\d]+)/i"));

        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR) {
            // Your credit card payment is past due.
            $error =
                $this->http->FindPreg("/Your credit card payment is past due\./i")
                ?? $this->http->FindPreg("/Your account is past due\. Please make a payment to bring your account up to date\./i")
            ;
            // For your security, this account has been closed.
            if (!$error) {
                $error = $this->http->FindPreg("/For your security, this account has been closed\./i");
            }

            if (!$error) {
                $error = $this->http->FindPreg("/You recently reported your card as lost or stolen\. For your protection, we have limited access to your account\./i");
            }

            if (!$error) {
                $error = $this->http->FindPreg("/brooks brothers rewards<\/h2><section><figure><\/figure><p>(This service is temporarily unavailable\.\s*Please try again later\.)/i");
            }

            if ($error) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg('/<div data-page-title="data-page-title"[^>]*>\s*Update Your Income/')) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                /*!empty($this->Properties['Name'])
                &&*/ !empty($this->Properties['Number'])
                && ($this->http->FindSingleNode("//h3[contains(text(), 'You Have No Balance')]") || in_array($this->AccountFields['Login'], ['aweisman7b', 'theettlingers']))
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR)
    }
}
