<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirchinaMobile extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useChromium();
//        $this->disableImages();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        $this->http->GetURL("https://m.airchina.com.cn/ac/c/invoke/login@pg");
        $login = $this->waitForElement(WebDriverBy::id('loginName'), 7);
        $pass = $this->waitForElement(WebDriverBy::id('password'), 0);
        $sbm = $this->waitForElement(WebDriverBy::id('subbtn'), 0);

        if (!$login || !$pass || !$sbm) {
            return false;
        }

        if (strpos($this->AccountFields['Login'], 'CA') !== false || strpos($this->AccountFields['Login'], 'CA') > 0) {
            $this->AccountFields['Login'] = str_replace('CA', '', $this->AccountFields['Login']);
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $sbm->click();

        return true;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 10;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            // look for logout link
            $logout = $this->waitForElement(WebDriverBy::xpath("//button[contains(@onclick, 'logout()')]"), 0);

            if ($logout) {
                return true;
            }
            $this->logger->notice("check errors");

            if ($error = $this->waitForElement(WebDriverBy::id("errorDiv"), 0)) {
                $message = $error->getText();
                $this->logger->error($message);
                // You can only log in with your mobile phone number/ID card/member card number.
                if (strstr($message, '只能用手机号/身份证/会员卡号登录哦。')
                    // wrong user name or password
                    || strstr($message, '用户名或密码错误')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
            }// if ($error = $this->waitForElement(WebDriverBy::id("errorDiv"), 0))

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        $this->logger->notice("Last saved screen");
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Parse()
    {
        // Balance - Points balance
        $balance = $this->waitForElement(WebDriverBy::id("mileage"), 0);
        $this->saveResponse();

        if (!$balance) {
            return;
        }
        $this->SetBalance($this->http->FindPreg("/当前可用里程:(\d+)/", false, $balance->getText()));
        // Name
        if ($username = $this->waitForElement(WebDriverBy::id("uname"), 0)) {
            $this->SetProperty("Name", $username->getText());
        }
        // Status
        $status = $this->http->FindPreg("/(.+)会员/", false, $balance->getText());

        switch ($status) {
            case '普通':
                $this->SetProperty("Status", "Ordinary");

                break;

            case '银卡':
                $this->SetProperty("Status", "Silver");

                break;

            default:
                $this->sendNotification("airchibna. Unknown status: '{$status}'");
        }
    }
}
