<?php

namespace AwardWallet\Engine\basspro;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BassproExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.basspro.com/shop/AjaxLogonForm?myAcctMain=1&catalogId=3074457345616676768&langId=-1&storeId=715838534";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="logonId"] | //a[@id="myAccountLink"]');

        return str_contains($result->getAttribute('id'), 'myAccountLink');
    }

    public function getLoginId(Tab $tab): string
    {
        $firstName = $tab->findText('//div[@class="myaccount_desc_title"]',
            FindTextOptions::new()->preg('/Hi\s+(.+)/'));
        $lastName = $tab->findText('//div[@id = "lastName_initials"]');

        return "$firstName $lastName";
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@id="myAccountLink"]')->click();
        $tab->evaluate('//a[@id="signInOutQuickLink"]')->click();
        $tab->evaluate('//a[@id="Header_GlobalLogin_signInQuickLink"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@name="logonId"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="logonPassword"]')->setValue($credentials->getPassword());

        $tab->evaluate('//a[@id="WC_AccountDisplay_links_2"]')->click();
        $errorOrSuccess = $tab->evaluate('//div[@id="bp-alert-error"] | //div[@id="WC_PasswordUpdateForm_div_1"]//h2 | //a[@id="myAccountLink"]');

        if (str_contains($errorOrSuccess->getInnerText(), 'Please provide a valid email and password.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'Change password')) {
            // TODO: update profile - not implementation
            // return LoginResult::providerError();
        }

        if (str_contains($errorOrSuccess->getAttribute('id'), 'myAccountLink')) {
            $tab->evaluate('//a[@id="myAccountLink"]')->click();
            $tab->evaluate('//a[@id="myAccountLink_dropdown"]')->click();

            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $rewardsLink = $tab->evaluate('//a[@id = "WC_MyAccountSidebarDisplayf_links_4a"]',
            EvaluateOptions::new()->allowNull(true));

        $st = $master->createStatement();
        // CLUB Account -> Rewards Available
        $rewards = $tab->findTextNullable('//div[@id = "clubWalletClubPoints1"]/span');

        if (isset($rewards)) {
            $st->addSubAccount([
                "Code"        => 'bassproClubRewards',
                "DisplayName" => "Club Rewards",
                "Balance"     => $rewards,
            ]);
        }

        if ($rewardsLink) {
            $this->logger->notice("Go to rewards page");

            if (!$tab->findTextNullable("//div[@id='section_list_rewards']//a[contains(text(), 'Outdoor Rewards') and not(contains(text(), 'FAQ'))]")) {
                $this->logger->error("something went wrong");
                $tab->saveScreenshot();

                return;
            }

            $rewardsLink->click();
            $tab->evaluate("
                //*[@id='rewardsBalanceAmount']
                | //div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] 
                | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]
            ", EvaluateOptions::new()->timeout(20));
        } elseif ($tab->evaluate("//div[@class = 'myaccount_desc_title' and (contains(text(),'Welcome, ') or contains(text(),'Hi '))]")) {
            if (isset($rewards)) {
                //# Name
                $st->addProperty('Name',
                    beautifulName("{$tab->findText("//span[@id = 'welcome_header_firstName']")} {$tab->findText('//div[@id = "lastName_initials"]')}"));
                $st->setNoBalance(true);

                return;
            }

            return;
            // TODO - not implementation
            //throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->findPreg('/allow 24 hours for your account number to be generated before logging in/ims',
            $tab->getHtml())) {
            return;
            // TODO - not implementation
            //throw new CheckException('Welcome to Bass Pro Shops Outdoor Rewards! Outdoorsmen know the best rewards come with a little patience. Please allow 24 hours for your account number to be generated before logging in. If you have any questions, please contact Customer Service at 1-800-227-7776 or contact us by email or chat.', ACCOUNT_PROVIDER_ERROR);
        }

        $firstName = $tab->findText("//*[@id='odr_welcome']/text()[contains(., 'Welcome,')]",
            FindTextOptions::new()->preg('/,\s*(.+)/'));
        $lastName = $tab->findText('//div[@id = "lastName_initials"]');
        $number = $tab->findText("//*[@id='or-welcome']/text()[contains(., 'Member ID:')]/following-sibling::span[1]");
        $balance = $tab->findText("//*[@id='rewardsBalanceAmount']",
            FindTextOptions::new()->pregReplace('/[^\d.]+/', ''));

        if ($tab->findTextNullable("//div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]")) {
            return;
            //throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Balance - The value of your current point balance is
        $st->setBalance($balance);
        // Name
        $st->addProperty("Name", beautifulName("$firstName $lastName"));
        // Member ID
        $st->addProperty("Number", $number);
        // My Points
        $st->addProperty("MyPoints", $tab->findText("//span[@id='rewardsPointBalance']"));

        if (!empty($st->getProperties()['Name']) && $balance === '') {
            $st->setNoBalance(true);
        }
    }
}
