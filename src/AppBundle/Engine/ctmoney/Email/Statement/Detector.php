<?php

namespace AwardWallet\Engine\ctmoney\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]triangle[.]com\b/i',
        ];
    }
}
