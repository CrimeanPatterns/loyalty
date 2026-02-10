<?php

namespace AwardWallet\Engine\divoire;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class DivoireExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://vre.frequentflyer.aero/cranelogin?code=Landing&lang=en";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $frame = $tab->selectFrameContainingSelector($xpath = '//input[@name="txtUser"] | //a[normalize-space()="Logout"]',
            SelectFrameOptions::new()->method('evaluate'));
        $result = $frame->evaluate($xpath);

        return str_contains($result->getInnerText(), 'Logout');
    }

    public function getLoginId(Tab $tab): string
    {
        $frame = $tab->selectFrameContainingSelector($xpath = '//div[contains(text(),"Card Number ")]',
            SelectFrameOptions::new()->method('evaluate'));

        return $frame->findText($xpath, FindTextOptions::new()->preg('/Card Number (\d+)/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&amp;wmode=transparent');
        $tab->evaluate('//a[normalize-space()="Logout"]')->click();

        $frame = $tab->selectFrameContainingSelector('//input[@name="txtUser"]',
            SelectFrameOptions::new()->method('evaluate'));
        $frame->evaluate('//input[@name="txtUser"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $frame = $tab->selectFrameContainingSelector('//input[@name="txtUser"]',
            SelectFrameOptions::new()->method("evaluate"));
        $frame->evaluate('//input[@name="txtUser"]')->setValue($credentials->getLogin());
        $frame->evaluate('//input[@name="txtPass"]')->setValue($credentials->getPassword());
        $frame->evaluate('//input[@name="btnSubmit"]')->click();
        $errorOrSuccess = $frame->evaluate('//div[@class="errorMessage errorcont"] | //a[normalize-space()="Logout"]');

        if (str_contains($errorOrSuccess->getInnerText(), 'Logout')) {
            $tab->gotoUrl('https://vre.frequentflyer.aero/cranelogin?code=Landing&lang=en');

            return new LoginResult(true);
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'INVALID_LOGIN')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $frameLeft = $tab->selectFrameContainingSelector('//div[@id="descr"]/p[contains(text(),"Dear")]',
            SelectFrameOptions::new()->method('evaluate'));
        $frameRight = $tab->selectFrameContainingSelector('//div[contains(text(),"Card Number ")]',
            SelectFrameOptions::new()->method('evaluate'));
        $st = $master->createStatement();

        // Name
        $st->addProperty('Name',
            beautifulName($frameLeft->findText('//div[@id="descr"]/p[contains(text(),"Dear")]', FindTextOptions::new()->preg('/Dear (.+?),/'))));

        // Card Number
        $cardNumber = $frameRight->findTextNullable('//div[contains(text(),"Card Number ")]');
        $st->addProperty('CardNumber', $this->findPreg('/Card Number (\d+)/', $cardNumber));

        // Tier
        $st->addProperty('Status', $this->findPreg('/Tier ([\w\s]+)/', $cardNumber));

        $st->setBalance($frameRight->findText('//div[contains(text(),"Award Miles :")]', FindTextOptions::new()->preg('/:\s*([\d,.]+)/')));
    }
}
