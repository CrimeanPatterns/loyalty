<?php

namespace AwardWallet\Engine\chinasouthern;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ChinasouthernExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string // +
    {
        return 'https://b2c.csair.com/B2C40/modules/ordernew/orderManagementFrame.html';
    }

    public function isLoggedIn(Tab $tab): bool // +
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@id, "loginBox")] | //a[@class="zsl-logined-name"]');

        return $el->getNodeName() == "A";
    }

    public function getLoginId(Tab $tab): string // +
    {
        return $tab->evaluate('//a[@class="zsl-logined-name"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult // +
    {
        $tab->evaluate('//li[@data-boxname="memberBox"]')->click();

        $login = $tab->evaluate('//input[@id="userId"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//input[@id="passWord"]')->click();

        $password = $tab->evaluate('//input[@id="passWord"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="loginProtocol"]')->click();

        $inputResult = $tab->evaluate('//input[@id="verifyCode"] | //button[@id="mem_btn_login" and not(contains(@class, "disabled"))]');

        if ($inputResult->getNodeName() == "INPUT") {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//a[@class="zsl-logined-name"] | //div[contains(@class, "error") and text()] | //div[@class="help-txt" and text()]', EvaluateOptions::new()->timeout(60));
        } else {
            $inputResult->click();
            $submitResult = $tab->evaluate('//a[@class="zsl-logined-name"] | //div[contains(@class, "error") and text()] | //div[@class="help-txt" and text()]');
        }

        if (strstr($submitResult->getAttribute('class'), "zsl-logined-name")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), "error")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "抱歉，您输入的用户名或者密码有误!")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void // +
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@class="zsl-logout"]')->click();
        $tab->evaluate('//div[@class="zsl-unlogin"]//a[contains(@href, "login")]');
    }
}
