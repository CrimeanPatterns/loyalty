<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFlysaaSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->disableImages();
        $this->useChromium();
        //$this->http->setRandomUserAgent();
//        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
//            $this->useCache();
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.flysaa.com/us/en/mileagesummarydetails!recentvoyagermileagesummarydetails.action');

        if ($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Logout")]'), 2)
            && ($this->waitForElement(WebDriverBy::id('welcomeText'), 0)
                || $this->waitForElement(WebDriverBy::xpath("//div[@class = 'name']"), 0))) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.flysaa.com/us/en/mileagesummarydetails!recentvoyagermileagesummarydetails.action');

        $form = $this->waitForElement(WebDriverBy::id('us_en_mileagesummarydetails'), 2);
        $voyagerId = $this->waitForElement(WebDriverBy::xpath('//input[@name="voyagerId"]'), 0);
        $pin = $this->waitForElement(WebDriverBy::xpath('//input[@name="pin"]'), 0);
        $button = $this->waitForElement(WebDriverBy::id('loginButton'), 0);

        if (empty($form) || empty($voyagerId) || empty($pin) || empty($button)) {
            $this->logger->error("something went wrong");
            $this->http->saveResponse();

            return $this->checkErrors();
        }

        $this->driver->executeScript("$('#voyagerId').val('{$this->AccountFields['Login']}')");
        $this->driver->executeScript("$('#pin').val('{$this->AccountFields['Pass']}')");
        $this->driver->executeScript("$('#loginButton').get(0).click()");

        return true;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Logout")]'), 2);
        $this->saveResponse();

        if ($logout && $this->waitForElement(WebDriverBy::id('welcomeText'), 0)) {
            return true;
        }

        // Member Does Not Exist
        if ($this->driver->executeScript("return $(':contains(\"Member Does Not Exist\")').length > 0")) {
            throw new CheckException('Member Does Not Exist', ACCOUNT_INVALID_PASSWORD);
        }

        // PIN number does not match
        if ($this->driver->executeScript("return $(':contains(\"PIN number does not match\")').length > 0")) {
            throw new CheckException('PIN number does not match', ACCOUNT_INVALID_PASSWORD);
        }

        // Username/Password invalid
        if ($this->driver->executeScript("return $(':contains(\"Username/Password invalid\")').length > 0")) {
            throw new CheckException('Username/Password invalid', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->driver->executeScript("return $(':contains(\"Login to suspended account is not allowed\")').length > 0")) {
            throw new CheckException('Login to suspended account is not allowed', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->driver->executeScript("return $(':contains(\"Web login is not enabled for this account\")').length > 0")) {
            throw new CheckException('Web login is not enabled for this account', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->driver->executeScript("return $(':contains(\"Voyager number  exceeds 15 characters\")').length > 0")) {
            throw new CheckException('Voyager number  exceeds 15 characters.', ACCOUNT_INVALID_PASSWORD);
        }

        // We are unable to process your request currently, we apologise for any inconvenience.
        // Please try again later
        if ($this->driver->executeScript("return $(':contains(\"We are unable to process your request currently, we apologise for any inconvenience\")').length > 0")) {
            throw new CheckException('We are unable to process your request currently, we apologise for any inconvenience.', ACCOUNT_PROVIDER_ERROR);
        }

        // We have experienced an issue retrieving your Voyager profile.
        // Please contact the Voyager Call Centre for assistance.
        // We apologise for any inconvenience caused.
        if ($this->driver->executeScript("return $(':contains(\"We have experienced an issue retrieving your Voyager profile\")').length > 0")) {
            $this->http->saveResponse();

            throw new CheckException('We have experienced an issue retrieving your Voyager profile. Please contact the Voyager Call Centre for assistance. We apologise for any inconvenience caused.', ACCOUNT_PROVIDER_ERROR);
        }
        // retries
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->driver->executeScript("return $(':contains(\"SOAP header missing\")').length > 0")) {
            throw new CheckRetryNeededException(3, 7);
        }

        if ($this->driver->executeScript("return $(':contains(\"EX001\")').length > 0")
                && $this->driver->executeScript("return $(':contains(\"Message\")').length > 0")
                || $this->driver->executeScript("return $('h4:contains(\"serverMessages.push(\")').length > 0")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->saveResponse();

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        // Read timed out
        if ($this->http->FindPreg('/\$/')) {
            if ($this->driver->executeScript("return $(':contains(\"Read timed out\")').length > 0")
                || $this->driver->executeScript("return $(':contains(\"sun.security.validator.ValidatorException:\")').length > 0")
                || $this->driver->executeScript("return $(':contains(\"The host did not accept the connection within timeout\")').length > 0")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->driver->executeScript("return $(':contains(\"The server encountered an unexpected error that prevented it from fulfilling the request\")').length > 0")) {
                throw new CheckException('The server encountered an unexpected error that prevented it from fulfilling the request', ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }

    public function Parse()
    {
        // Miles to tier //refs #5879
        if ($this->http->currentUrl() != 'https://www.flysaa.com/us/en/mileagesummarydetails!recentvoyagermileagesummarydetails.action') {
            $this->http->GetURL('https://www.flysaa.com/us/en/mileagesummarydetails!recentvoyagermileagesummarydetails.action');
        }
        // Balance - My Miles
        $this->SetBalance($this->http->FindSingleNode("//td[@id = 'myMiles']"));
        // Voyager Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//th[contains(text(), 'Membership #:')]/following-sibling::td"));
        // Full Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'welcomeText']/p", null, true, "/Welcome\s([^<]+)/ims")));
        // Voyager Tier
        $this->SetProperty("Tier", $this->http->FindSingleNode("//td[div[contains(@class,'tier')]]"));
        // Miles to tier
        $this->SetProperty('MilesToTier', $this->http->FindSingleNode('//th[contains(text(), "Miles to Tier")]/following-sibling::td[1]'));
        // A sectors to tier
        $this->SetProperty('ASectorsToTier', $this->http->FindSingleNode('//th[contains(text(), "A sectors to tier")]/following-sibling::td[1]'));

        if ($this->http->FindSingleNode('//th[contains(text(), "A sectors to tier")]/following-sibling::td[1]')) {
            $this->sendNotification('sectors to tier // MI');
        }
        // B sectors to tier
        $this->SetProperty('BSectorsToTier', $this->http->FindSingleNode('//th[contains(text(), "B sectors to tier")]/following-sibling::td[1]'));
        // C sectors to tier
        $this->SetProperty('CSectorsToTier', $this->http->FindSingleNode('//th[contains(text(), "C sectors to tier")]/following-sibling::td[1]'));

        // Expiration Date   //refs #4439
        $this->logger->info('Expiration Date', ['Header' => 3]);
//        $this->http->PostURL("https://www.flysaa.com/us/en/mileagesummarydetails!recentvoyagermileagesummarydetails.action", array());
        $nodes = $this->http->XPath->query("//tr[th[contains(text(), 'Available Miles On')]]");
        $this->logger->debug("Total nodes: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $miles = $this->http->FindSingleNode('td', $nodes->item($i));

                if ($miles > 0) {
                    $exp = $this->http->FindSingleNode('th', $nodes->item($i), true, '/Available\s*Miles\s*On\s*([^:<]+)/ims');
                    //# Miles to Expire
                    $this->SetProperty("MilesToExpire", $miles);
                    //# Expiration Date
                    $this->SetExpirationDate(strtotime($exp));

                    break;
                }// if ($miles > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if (isset($nodes))
    }
}
