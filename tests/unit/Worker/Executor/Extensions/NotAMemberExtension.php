<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\Common\Parsing\Exception\NotAMemberException;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class NotAMemberExtension extends \Tests\Unit\Worker\Executor\Extensions\LoggedInExtension implements ParseInterface
{

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        throw new NotAMemberException();
    }
}