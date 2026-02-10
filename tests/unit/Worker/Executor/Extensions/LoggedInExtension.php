<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class LoggedInExtension extends AbstractParser implements LoginWithIdInterface
{

    public function getStartingUrl(AccountOptions $options): string
    {
        return "http://no.matter.what.local";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        return false;
    }

    public function getLoginId(Tab $tab): string
    {
        return "";
    }

    public function logout(Tab $tab): void
    {

    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        return new LoginResult(true);
    }

}