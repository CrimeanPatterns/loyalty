<?php

namespace AwardWallet\Engine\etihad;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class EtihadExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.etihadguest.com/en/my-account/account-summary.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrBalance = $tab->evaluate('//button[@id="submitLogin"] | //span[@data-ng-bind = "cmnCtrl.checkComma(accSummeryModel.accdata.guestmiles)"]', EvaluateOptions::new()->nonEmptyString());

        return $loginFieldOrBalance->getNodeName() === 'SPAN';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@data-ng-bind = "::accSummeryModel.accdata.memberNumber"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->querySelector('input[name="emailOrGuestNumber"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->querySelector('input[name="loginPass"]');
        $password->setValue($credentials->getPassword());

        $tab->querySelector('button#submitLogin')->click();

        $errorOrTitle = $tab->evaluate('//p[@data-ng-bind-html="errHold"] | //p[@data-ng-bind-html="succHold"]', EvaluateOptions::new()->visible(false)->nonEmptyString());

        if ($errorOrTitle->getNodeName() === 'H2') {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrTitle->getInnerText();

            if (str_starts_with($error, "Please enter in the one-time")) {
                if (isset($credentials->getAnswers()[$error])) {
                    $sendAnswerError = $this->sendAnswer($tab, $credentials->getAnswers()[$error]);

                    if ($sendAnswerError !== null) {
                        $question = $tab->evaluate('//p[@data-ng-bind-html="succHold"]', EvaluateOptions::new()->visible(false)->nonEmptyString())->getInnerText();

                        return new LoginResult(false, $sendAnswerError, $question);
                    }
                }

                return new LoginResult(false, null, $error);
            }

            return new LoginResult(false, $error);
        }
    }

    public function sendAnswer(Tab $tab, string $answer): ?string
    {
        $this->logger->info("sending answer: $answer");

        $input = $tab->querySelector("input[name='otp']");
        $input->setValue($answer);

        $button = $tab->querySelector("button#OTPDetails");
        $button->click();

        $error = $tab->evaluate('//p[@data-ng-bind-html="errHold"]', EvaluateOptions::new()->visible(false)->nonEmptyString())->getInnerText();

        return $error;
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $master->createStatement()->setBalance(str_replace(',', '', $tab->evaluate('//span[@data-ng-bind = "cmnCtrl.checkComma(accSummeryModel.accdata.guestmiles)"]', EvaluateOptions::new()->nonEmptyString())->getInnerText()));
    }

    public function logout(Tab $tab): void
    {
    }
}
