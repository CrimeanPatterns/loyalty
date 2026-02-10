<?php

namespace AwardWallet\Engine\aviancataca;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AviancatacaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.lifemiles.com/account/overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//div[@class="lm-social-section"] | //input[@id="username"] | //div[@class="account-ui-AccountActivityCard_userId"]', EvaluateOptions::new()->timeout(60));

        return $el->getNodeName() == 'DIV' && strstr($el->getAttribute('class'), "account-ui");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="account-ui-AccountActivityCard_userId"]')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@id="social-Lifemiles"]')->click();

        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $login = $tab->evaluate('//input[@id="username"]'); // prevent form errors
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@id="Login-confirm" and contains(@class, "buttonBlue")]', EvaluateOptions::new()->timeout(60))->click();

        $submitResult = $tab->evaluate('//div[@class="account-ui-AccountActivityCard_userId"] | //p[@class="authentication-ui-GeneralErrorModal_description"] | //p[@class="authentication-ui-ConfirmIdentity_label" and not(B)] | //button[@class="account-ui-OverviewNewForYou_hiddenContentClose"]', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getAttribute('class') == "account-ui-OverviewNewForYou_hiddenContentClose") {
            $tab->evaluate('//button[@class="account-ui-OverviewNewForYou_hiddenContentClose"]')->click();

            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "DIV") {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getAttribute('class'), "authentication-ui-GeneralErrorModal_description")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Los datos proporcionados no coinciden con nuestros registros. Por favor, inténtalo nuevamente")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif (strstr($submitResult->getAttribute('class'), "authentication-ui-ConfirmIdentity_label")) {
            $question = $submitResult->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputElements = $tab->evaluateAll('//div[@class="authentication-ui-Code_codeWrapper"]/div');

            for ($i = 0; $i < count($inputElements); $i++) {
                $tab->evaluate('//input[@id="' . $i . ']')->setValue($answer[$i]);
            }

            $tab->evaluate('//div[@class="authentication-ui-ConfirmIdentity_buttonWrapperApp"]')->click();

            $submitResult = $tab->evaluate('//div[@class="account-ui-AccountActivityCard_userId"] | //img[contains(@src, "mfa-error")]/../p');

            if ($submitResult->getNodeName() == "P") {
                return new LoginResult(false, $submitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        sleep(1);
        $tab->evaluate('//img[contains(@src, "white-arrow-down")]/../..')->click();
        sleep(1);
        $tab->evaluate('//button[contains(@class, "logoutButton")]')->click();
        sleep(3);
        $tab->evaluate('//a[@class="menu-ui-Menu_login"]', EvaluateOptions::new()->timeout(30));
    }
}
