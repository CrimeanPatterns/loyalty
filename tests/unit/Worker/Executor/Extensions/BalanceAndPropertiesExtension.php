<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class BalanceAndPropertiesExtension extends \Tests\Unit\Worker\Executor\Extensions\LoggedInExtension implements ParseInterface
{

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $master->createStatement()->setBalance(100);
        $master->getStatement()->setNumber('1234');
    }
}