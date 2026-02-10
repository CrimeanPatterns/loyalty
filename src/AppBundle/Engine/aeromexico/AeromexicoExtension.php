<?php

namespace AwardWallet\Engine\aeromexico;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AeromexicoExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://member.aeromexicorewards.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="account"] | //h3[@class="as-cp-account-number"]');

        return $el->getNodeName() == "H3";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//h3[@class="as-cp-account-number"]')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="account"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        sleep(2);

        $tab->evaluate('//button[@id="btnSubmit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "alert-code-security") and not(contains(@class, "d-none"))] | //div[contains(@class, "invalid-feedback")]/span | //h3[@class="as-cp-account-number"] | //div[@class="alert alert-danger alert-general"]/p', EvaluateOptions::new()->visible(false));

        if ($submitResult->getNodeName() == 'H3') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == 'DIV') {
            $question = $tab->evaluate('//div[contains(@class, "alert-code-security") and not(contains(@class, "d-none"))]/p')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $otp = $tab->evaluate('//input[@id="code-security"]');
            $otp->setValue($answer);

            $login = $tab->evaluate('//input[@id="account"]');
            $login->setValue($credentials->getLogin());

            $password = $tab->evaluate('//input[@id="password"]');
            $password->setValue($credentials->getPassword());

            $tab->evaluate('//button[@id="btnSubmit" and not(@disabled)]')->click();

            $otpSubmitResult = $tab->evaluate('//div[contains(@class, "invalid-feedback")]/span | //h3[@class="as-cp-account-number"] | //div[@class="alert alert-danger alert-general"]/p', EvaluateOptions::new()->visible(false));

            if ($otpSubmitResult->getNodeName() == 'H3') {
                return new LoginResult(true);
            } elseif ($otpSubmitResult->getNodeName() == 'SPAN') {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
            } else {
                $error = $otpSubmitResult->getInnerText();

                if (
                    strstr($error, "El número de cuenta y/o contraseña son incorrectos.")
                    || strstr($error, "Código de verificación inválido.")
                ) {
                    return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
                }

                return new LoginResult(false, $error);
            }
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "El número de cuenta y/o contraseña son incorrectos.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="userinfo"]/following-sibling::div//a[contains(@href, "salir")]')->click();
        $tab->evaluate('//input[@id="account"]');
    }
}
