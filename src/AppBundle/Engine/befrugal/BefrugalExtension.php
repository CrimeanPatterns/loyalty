<?php

namespace AwardWallet\Engine\befrugal;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class BefrugalExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.befrugal.com/account/member/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//a[@data-popup-target=".SignupPopupCore"] | //span[contains(@id,"displayName")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(@id,"displayName")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@data-popup-target=".SignupPopupCore"]')->click();

        $login = $tab->evaluate('//form[contains(@class, "Login")]//input[@name="Username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form[contains(@class, "Login")]//input[@name="Password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@name="emailLogin"]')->click();

        $submitResult = $tab->evaluate('
            //button[@id="cunb_btnUpdate"]
            | //span[@class="bf-AuthMessage" and text() and not(contains(text(), "I am not a Robot"))]
            | //iframe[contains(@title, "recaptcha") and not(@width="256") and not(@height="60")]
            | //iframe[@title="reCAPTCHA" and not(@width="256") and not(@height="60")]
            | //div[@class="account-management-page-title-gray" and contains(text(), "Reset Password")]
            | //div[contains(text(), "2-Step Verification")]
        ');

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

            $submitResult = $tab->evaluate('
                //button[@id="cunb_btnUpdate"]
                | //span[@class="bf-AuthMessage" and text() and not(contains(text(), "I am not a Robot"))]
                | //div[@class="account-management-page-title-gray" and contains(text(), "Reset Password")]
                | //div[contains(text(), "2-Step Verification")]
            ', EvaluateOptions::new()->timeout(60));
        }

        if (!strstr($submitResult->getAttribute('class'), "bf-AuthMessage") && !strstr($submitResult->getInnerText(), "2-Step Verification")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getInnerText(), "2-Step Verification")) {
            $question = $tab->evaluate('//div[@force-refresh-next-navigation]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $tab->evaluate('//input[@name="code"]')->setValue($answer);

            $tab->evaluate('//button[@value="submitcode"]')->click();

            $otpSubmitResult = $tab->evaluate('//span[contains(@style,"color:Red") and text()] | //button[@id="cunb_btnUpdate"]');

            if ($otpSubmitResult->getNodeName() == 'SPAN') {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please enter your email address.")
                || strstr($error, "Please enter a valid email address")
                || strstr($error, "Incorrect email address or password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Reset Password")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/account/"]')->click();
        $tab->evaluate('//a[contains(@class, "logOutLink")]')->click();
        $tab->evaluate('//div[contains(@class, "divLogOut") and not(contains(@style,"display:none;"))]//a[contains(@class, "bf-logoutnow")]')->click();
        $tab->evaluate('//a[contains(text(), "Log In")]');
    }
}
