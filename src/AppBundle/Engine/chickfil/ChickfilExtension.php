<?php

namespace AwardWallet\Engine\chickfil;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ChickfilExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.chick-fil-a.com/myprofile/points';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="pf.username"] | //a[contains(@href,"/Account/Logout")]',
            EvaluateOptions::new()->timeout(15));

        return str_starts_with($result->getInnerText(), "Sign Out");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//div[contains(@class,"membership-number membership-item")]/span',
            FindTextOptions::new()->preg('/^[\d]+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@href,"/Account/Logout")]')->click();
        $tab->evaluate('//form[@id="main-menu-signin"]');
        $tab->evaluate('//form[@id="main-menu-signin"]/button')->click();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="pf.username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="pf.pass"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@name="pf.ok"]')->click();

        $result = $tab->evaluate('
                //h1[contains(text(),"We don\'t recognize this device")]
                | //p[contains(text(),"We just need to verify your details. We\'ve sent a verification code to:")] 
                | //a[contains(@href,"/Account/Logout")] 
            ');
        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "We don't recognize this device")) {
            // TODO
            $result = $tab->evaluate('//button[contains(text(),"Log out")]', EvaluateOptions::new()->timeout(90)->allowNull(true));

            if (!$result) {
                return LoginResult::providerError('Message from AwardWallet: In order to log in into this account please Verify this device and click the “Next” button. Once logged in, sit back and relax, we will do the rest.');
            }
        }

        if (str_starts_with($result->getInnerText(), "That email or password doesn’t look right")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (str_starts_with($result->getInnerText(), "Sign Out")) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        if ($tab->findTextNullable('//div[@id = "titleSubText" and (contains(text(), "We\'ve made some updates to our Privacy Policy. Learn more about the updates to the Privacy Policy and how we") or contains(text(), "We\'ve made some updates to our Chick-fil-A Terms"))]')) {
            //$this->throwAcceptTermsMessageException();
            return; // TODO
        }

        // Balance - REWARDS BALANCE: 1803 PTS
        if (!$st->setBalance($tab->findTextNullable('//span[contains(text(), "Rewards balance:")]', FindTextOptions::new()->preg("/:\s*(.+)\s+pts/i")))) {
            $st->setBalance($tab->findText('//p[contains(text(), "Points last updated")]/following-sibling::h1'));
        }
        // Name
        $st->addProperty('Name', beautifulName($tab->findText("//div[@class='cp-nav__details']//h4")));
        // CHICK-FIL-A ONE MEMBER
        $st->addProperty('Status', $tab->findText('//div[contains(@class, "member-tier")]/p[2] | //div[contains(@class, "--active")]//h5[contains(@class, "member-category")]'));
        // Your Chick-fil-A One™ red status is valid through ...
        $st->addProperty('StatusExpiration',
            $tab->findTextNullable("//div[contains(@class, 'status-until')]/p[2]", null, false, '/([^\.]+)/')
            ?? $tab->findText('//div[contains(@class, "--active")]//span[contains(text(), "Status valid until")]', null, false, '/until\s*([^\.]+)/')
        );
        // Lifetime points earned
        $st->addProperty('TotalPointsEarned', $tab->findText('//h5[contains(text(), "Lifetime points earned")]/following-sibling::p | //h5[contains(text(), "Lifetime points earned")]/following-sibling::span'));
        // Earn ... to reach ... Status.
        $st->addProperty('PointsNextLevel', $tab->findTextNullable('//div[contains(@class, "progress-bar-anim")]/p | //span[contains(text(), "more points by the end of the year")]', null, true, "/Earn\s*(\d+)/ims"));
        // MEMBERSHIP #
        $st->addProperty('AccountNumber', $tab->findText("//h5[contains(text(),'Membership #')]/following-sibling::*[self::p or self::span]"));
        // MEMBER SINCE
        $st->addProperty('MemberSince', $tab->findTextNullable("//h5[contains(text(),'Member Since')]/following-sibling::p"));

        $tab->gotoUrl('https://order.chick-fil-a.com/account/pending-orders');
        $tab->gotoUrl('https://order.chick-fil-a.com/my-rewards');

        $tab->findText("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward'] 
        | //h5[contains(text(),'You currently do not have any rewards available')]",
            FindTextOptions::new()->allowNull(true));

        if ($tab->findTextNullable("//h5[contains(text(),'You currently do not have any rewards available')]")) {
            $this->logger->notice("Rewards not found");

            return;
        }

        $tab->evaluate("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward']",
            EvaluateOptions::new()->timeout(5)->allowNull(true));
        $rewards = $tab->evaluateAll("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward']");
        $this->logger->debug("Total " . count($rewards) . " rewards were found");

        foreach ($rewards as $reward) {
            $displayName = $tab->findText(
                ".//div[div/div/div[@class = 'reward-details'] and position() = 1]//div[@class = 'reward-details']/h5 
                | //h4[@data-cy = 'RewardName']", FindTextOptions::new()->contextNode($reward));
            $exp = $tab->findText(".//*[self::p or self::div][contains(text(), 'Valid through')]",
                FindTextOptions::new()->contextNode($reward)->preg("/Valid\s*through\s*(.+)/"));
            $this->logger->debug("{$displayName} / Exp date: {$exp}");
            $exp = strtotime($exp, false);
            $st->addSubAccount([
                'Code'           => 'chickfil' . str_replace([' ', '®', '™', ','], '', $displayName) . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }
    }
}
