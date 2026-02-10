<?php

namespace AwardWallet\Engine\chase;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class ChaseExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if ($request->getLogin2() === 'canada') {
            return ChaseExtensionCanada::class;
        }

        return ChaseExtension::class;
    }
}
