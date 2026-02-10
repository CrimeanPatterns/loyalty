<?php

namespace AwardWallet\Engine\bing;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class BingExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.bing.com/myprofile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//span[@id="id_n" and not(@style="display:none")] | //a[@class="login_btn"] | //div[@class="bpmp_person_info"]//h2');

        return $el->getNodeName() == "SPAN" || $el->getNodeName() == 'H2';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "titleinfo")]/h2', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $tab->evaluate('//a[@class="login_btn"]')->click();

        $login = $tab->evaluate('//input[@name="loginfmt"] | //input[@type="button" and contains(@id, "Cancel")]');

        if ($login->getAttribute('type') == 'button') {
            $login->click();
            $login = $tab->evaluate('//input[@name="loginfmt"]');
        }

        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@id, "Error")] | //input[@name="passwd"] | //div[@aria-label="Personal account"]//button');

        if ($submitResult->getNodeName() == 'BUTTON') {
            $submitResult->click();
            $submitResult = $tab->evaluate('//div[contains(@id, "Error")] | //input[@name="passwd"]');
        }

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@id, "Error")] | //span[@id="id_n"] | //div[@class="bpmp_person_info"]//h2 | //button[@id="acceptButton"] | //div[contains(text(), "Email") and not(contains(text(), "*"))]');

        if ($submitResult->getNodeName() == 'BUTTON') {
            $submitResult->click();
            $submitResult = $tab->evaluate('//div[contains(@id, "Error")] | //span[@id="id_n"] | //div[contains(text(), "Email") and not(contains(text(), "*"))]');
        }

        if ($submitResult->getNodeName() == 'SPAN' || $submitResult->getNodeName() == 'H2') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'DIV' && !strstr($submitResult->getInnerText(), "Email")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your account or password is incorrect. If you don't remember your password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Sign-in is blocked")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'DIV' && strstr($submitResult->getInnerText(), "Email")) {
            $submitResult->click();

            $question = $tab->evaluate('//div[@class="text-block-body overflow-hidden"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $tab->evaluate('//input[@name="otc"]')->setValue($answer);

            $tab->evaluate('//input[@type="submit"]')->click();

            $otpSubmitResult = $tab->evaluate('//div[contains(@class, "titleinfo")]/h2 | //div[contains(@id, "Error")]/span');

            if ($otpSubmitResult->getNodeName() == 'H2') {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@id="id_n"]')->click();
        $tab->evaluate('//a[contains(@href, "signout")]')->click();
        $tab->evaluate('//a[@class="login_btn"]');
    }
}
