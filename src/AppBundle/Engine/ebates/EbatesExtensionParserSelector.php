<?php

namespace AwardWallet\Engine\ebates;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class EbatesExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        switch ($request->getLogin2()) {
            case 'Canada':
                return EbatesExtensionCA::class;

            case 'Germany':
                return EbatesExtensionDE::class;

            case 'USA':
            case 'UK':
            default:
                return EbatesExtension::class;
        }
    }
}
