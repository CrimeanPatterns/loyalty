<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class HistoryExtension extends \Tests\Unit\Worker\Executor\Extensions\LoggedInExtension
    implements ParseInterface, ParseHistoryInterface
{

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $master->createStatement()->setBalance(100);
    }

    public function parseHistory(Tab $tab, Master $master, AccountOptions $options, ParseHistoryOptions $historyOptions): void
    {
        $master->createStatement()->addActivityRow([
            'PostingDate' => strtotime('2020-01-01'),
            'Description' => 'Test history row',
            'Miles' => 200,
        ]);
    }
}