<?php

namespace AwardWallet\Engine\avis;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AvisExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public $regionOptions = [
        'Australia'   => 'Australia',
        'Belgium'     => 'Belgium',
        'Finland'     => 'Finland',
        'France'      => 'France',
        "Germany"     => "Germany",
        "Italy"       => "Italy",
        'Sweden'      => 'Sweden',
        'Norway'      => 'Norway',
        'Spain'       => 'Spain',
        'Switzerland' => 'Switzerland',
        "UK"          => "UK",
        "USA"         => "USA",
    ];

    private $invalidCredentials = [
        'Es tut uns leid, aber Ihre E-Mail-Adresse und Ihr Passwort stimmen nicht überein. Bitte überprüfen Sie Ihre Eingaben und versuchen Sie es erneut',
        'Uw e-mailadres en wachtwoord komen niet overeen. Controleer ze en probeer het opnieuw',
        'Valitettavasti sähköpostiosoitteesi ja salasanasi eivät täsmää. Ole hyvä ja tarkista tiedot ja yritä uudelleen',
        'Siamo spiacenti, il tuo indirizzo e-mail e la tua password non coincidono. Ti preghiamo di controllare e provare di nuovo',
        'Beklager, e-postadressen din og passordet stemmer ikke overens. Vennligst sjekk og prøv igjen',
        'Lo sentimos, tu email y contraseña no coinciden. Por favor, compruébalo de nuevo',
        'Sorry, your email address and password don\'t match. Please check and try again',
        'Din e-postadress och lösenord matchar inte. Vänligen kontrollera och försök igen',
        'The information provided does not match our records. Please ensure that the information you have entered is correct and try again',
        'Nous sommes désolés mais votre adresse e-mail et votre mot de passe ne correspondent pas. Merci de réessayer',
        'ES259',
    ];

    private $providerError = [
        'We are Sorry, the site has not properly responded to your request. If the problem persists, please contact Avis',
        'We could not process your request',
    ];

    private $urls = [
        'Germany'     => 'https://www.avis.de',
        'Belgium'     => 'https://www.avis.be',
        'France'      => 'https://www.avis.fr',
        'Finland'     => 'https://www.avis.fi',
        'Italy'       => 'https://www.avisautonoleggio.it',
        'Norway'      => 'https://www.avis.no',
        'Spain'       => 'https://www.avis.es',
        'Sweden'      => 'https://www.avis.se',
        'Switzerland' => 'https://www.avis.ch',
        'UK'          => 'https://www.avis.co.uk',
        'USA'         => 'https://www.avis.com/en/home',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        if (!in_array($options->login2, array_flip($this->regionOptions))) {
            $options->login2 = 'USA';
        }

        return $this->urls[$options->login2];
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        try {
            $this->logger->debug('closing cookies popup');
            $tab->evaluate('//a[@id="consent_prompt_accept"]', EvaluateOptions::new()->timeout(5))->click(); // close cookies pupup
        } catch (ElementNotFoundException $e) {
            $this->logger->debug('cookies popup not found');
        }

        try {
            $tab->evaluate('//dd[@id="custwizdetail"] | //h1[contains(text(), "Profile")] | //a[contains(text(), "Welcome")]', EvaluateOptions::new()->timeout(0));

            return true;
        } catch (ElementNotFoundException $e) {
            return false;
        }
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//a[contains(@class, "welcome-menu-trigger")] | //button[@id="your-avis-button"]')->click();
        sleep(1);

        try {
            return $tab->evaluate('//dd[@id="custwizdetail"]', EvaluateOptions::new()->timeout(1))->getInnerText();
        } catch (ElementNotFoundException $e) {
            $this->logger->debug('seems that not logged in');
        }

        return $tab->findText('//div[contains(@class, "left-sidebar")]//div[@class="hidden-sm"]//h3', FindTextOptions::new()->preg('/#\s*(\w+)/'));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@id="supp_nav_user_icon"] | //ul[@class="header-secondary"]//a[@id="res-login-profile"]')->click(); // open login form

        $login = $tab->evaluate('//input[@id="username"] | //input[@id="login-email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"] | //input[@id="login-hidtxt"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="res-login-profile"] | //button[@id="loginSubmitButton"]')->click(); // submit login form

        try {
            $tab->evaluate('//dd[@id="custwizdetail"] | //h1[contains(text(), "Profile")]', EvaluateOptions::new()->timeout(5)); // for not america

            return new LoginResult(true);
        } catch (ElementNotFoundException $e) {
            $this->logger->debug('seems that not logged in');
        }

        $errorOrSuccess = $tab->evaluate('//p[@class="alert__message"] | //span[contains(@class,"mainErrorText") and not(contains(text(), "You are now logged out of your Avis account"))] | //*[contains(text(), "For added security")]');

        foreach ($this->invalidCredentials as $invalidCredentials) {
            if (strstr($errorOrSuccess->getInnerText(), $invalidCredentials)) {
                return new LoginResult(false, $errorOrSuccess->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
            }
        }

        foreach ($this->providerError as $providerError) {
            if (strstr($errorOrSuccess->getInnerText(), $providerError)) {
                return new LoginResult(false, $errorOrSuccess->getInnerText(), null, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if (strstr($errorOrSuccess->getInnerText(), "For added security")) {
            $tab->evaluate('//a[contains(text(), "Choose Verification Method ")]')->click();
            $tab->evaluate('//button[@id="otp_emailme"]')->click();

            $question = $tab->evaluate('//span[contains(text(), "For added security")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@name="otp"]');
            $input->setValue($answer);

            $errorOrSuccess = $tab->evaluate('//span[@class="platform-error-message error"] | //h1[contains(text(), "Profile")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();

            if (strstr($errorOrSuccess, "Something went wrong. Request a new code")) {
                return new LoginResult(false, $errorOrSuccess, $question);
            } elseif (strstr($errorOrSuccess, "Profile")) {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false, $errorOrSuccess->getInnerText());
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@class, "welcome-menu-trigger")] | //button[@id="your-avis-button"]')->click();
        $tab->evaluate('//button[contains(text(), "Log Out")] | //button[@data-trigger="sign-out-modal"]',
            EvaluateOptions::new()->nonEmptyString())
            ->click();
        $tab->evaluate('//a[contains(@class, "welcome-menu-trigger")] | //button[@id="your-avis-button"]');
    }
}
