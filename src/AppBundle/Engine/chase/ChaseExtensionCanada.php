<?php

namespace AwardWallet\Engine\chase;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ChaseExtensionCanada extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://this-is-chase-canada-site.com';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        return false;
    }

    public function getLoginId(Tab $tab): string
    {
        // TODO: Implement getLoginId() method.
    }

    public function logout(Tab $tab): void
    {
        // TODO: Implement logout() method.
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        // TODO: Implement login() method.
    }
}
