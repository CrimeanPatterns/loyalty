<?php

namespace AwardWallet\Engine\airnewzealand;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AirnewzealandExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public $regionOptions = [
        ""                => "Select your region",
        "Australia"       => "Australia",
        "Canada"          => "Canada",
        "China"           => "China",
        "HongKong"        => "Hong Kong",
        "Japan"           => "Japan",
        "NewZealand"      => "New Zealand & Continental Europe",
        "PacificIslands"  => "Pacific Islands",
        "UK"              => "United Kingdom & Republic of Ireland",
        "USA"             => "United States",
    ];

    private $host = 'www.airnewzealand.co.nz';

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->setRegionSettings($options);

        return "https://{$this->host}/airpoints-account/airpoints/member/dashboard";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="signInName"] | //div[@class="points"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@class="points"]', EvaluateOptions::new()->nonEmptyString());

        $this->logger->debug("abc");

        $this->logger->debug($el->getInnerText());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="signInName"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@id="next"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "error") and @role="alert" and not(@style="display: none;")]/p | //a[contains(text(), "Use another authentication method")]');

        if ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Airpoints™ number / username doesn't match our records.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        $otpMenuElement = $tab->evaluate('//span[contains(text(), "Email OTP")] | //input[@id="verificationCode"]');

        if ($otpMenuElement->getNodeName() == 'SPAN') {
            $otpMenuElement->click();
        }

        $question = $tab->evaluate('//h1[@id="heading"]/following-sibling::p')->getInnerText();

        if (!isset($credentials->getAnswers()[$question])) {
            return new LoginResult(false, null, $question);
        }

        $answer = $credentials->getAnswers()[$question];

        $this->logger->info("sending answer: $answer");

        $tab->evaluate('//input[@id="verificationCode"]')->setValue($answer);

        $otpSubmitResult = $tab->evaluate('//span[contains(text(), "Code is invalid or has expired")] | //div[@class="points"] | //*[contains(text(), "Website Temporarily Unavailable")]');

        if (strstr($otpSubmitResult->getInnerText(), 'Website Temporarily Unavailable')) {
            return new LoginResult(false, $otpSubmitResult->getInnerText(), $question, ACCOUNT_PROVIDER_ERROR);
        }

        if ($otpSubmitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            return new LoginResult(false, "Code is invalid or has expired", $question);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@class="nav-link"]')->click();
        $tab->evaluate('//a[contains(text(), "Sign out")]')->click();
        $tab->evaluate('//button[contains(text(), "Sign in")]', EvaluateOptions::new()->timeout(30));
    }

    private function setRegionSettings($options)
    {
        $this->logger->notice(__METHOD__);
        $options->login2 = $this->checkRegionSelection($this->AccountFields['Login2'] ?? null);
        $this->logger->notice('Region => ' . $options->login2);
        // Identification host
        if (!empty($options->login2)) {
            // http://www.airnewzealand.eu/gateway
            switch ($options->login2) {
                case 'Australia':
                    $this->host = 'www.airnewzealand.com.au';

                    break;

                case 'Canada':
                    $this->host = 'www.airnewzealand.ca';

                    break;

                case 'China':
                    $this->host = 'www.airnewzealand.com.cn';

                    break;

                case 'HongKong':
                    $this->host = 'www.airnewzealand.com.hk';

                    break;

                case 'Japan':
                    $this->host = 'www.airnewzealand.co.jp';

                    break;

                case 'PacificIslands':
                    $this->host = 'www.pacificislands.airnewzealand.com';

                    break;

                case 'UK':
                    $this->host = 'www.airnewzealand.co.uk';

                    break;

                case 'USA':
                    $this->host = 'www.airnewzealand.com';

                    break;

                default:
                    $this->host = 'www.airnewzealand.co.nz';
            }
        }
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'NewZealand';
        }

        return $region;
    }
}
