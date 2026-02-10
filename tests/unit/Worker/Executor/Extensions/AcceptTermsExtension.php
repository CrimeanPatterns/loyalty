<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\Common\Parsing\Exception\AcceptTermsException;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class AcceptTermsExtension extends \Tests\Unit\Worker\Executor\Extensions\LoggedInExtension implements ParseInterface
{

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        throw new AcceptTermsException();
    }
}