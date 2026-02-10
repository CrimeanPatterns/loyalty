<?php

namespace AwardWallet\Engine\asiana;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AsianaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    private $loginTypeRadio;

    public function getStartingUrl(AccountOptions $options): string // +
    {
        if ($options->login2 == 'Number' || (empty($options->login2) && is_numeric($options->login2))) {
            $this->loginTypeRadio = '//input[@id="loginType_ACNO"]';
        } else {
            $this->loginTypeRadio = '//input[@id="loginType_ID"]';
        }

        return 'https://flyasiana.com/I/US/EN/MyasianaDashboard.do';
    }

    public function isLoggedIn(Tab $tab): bool // +
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@class="login_wrap"] | //span[@class="mem_number" and not(text()="OZ ")]', EvaluateOptions::new()->visible(false));

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string // +
    {
        $el = $tab->evaluate('//span[@class="mem_number"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        return $this->findPreg('/OZ\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult // -
    {
        $tab->evaluate($this->loginTypeRadio)->click();

        $login = $tab->evaluate('//input[@id="txtID"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="txtPW"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="btnLogin"]')->click();

        try {
            $submitResult = $tab->evaluate('//span[@class="mem_number" and not(text()="OZ ")] | //div[@class="login_fail" and not(@style="display:none;")]/p[text() and strong and not(@style="display: none;")] | //div[@class="login_find" and not(@style="display:none;")]/p', EvaluateOptions::new()->nonEmptyString()->visible(false));

            if ($submitResult->getNodeName() == 'SPAN') {
                return new LoginResult(true);
            } else {
                $error = $submitResult->getInnerText();

                if (
                    strstr($error, "Password entry has failed")
                    || strstr($error, "Password entry has exceeded more than 5 times")
                ) {
                    return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
                }

                return new LoginResult(false, $error);
            }
        } catch (ElementNotFoundException $e) { // alert workaround
            return new LoginResult(false, "Password entry has failed", null, ACCOUNT_INVALID_PASSWORD);
        }
    }

    public function logout(Tab $tab): void // +
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@onclick, "logout")]')->click();
        $tab->evaluate('//a[contains(@onclick, "Login")]');
    }
}
