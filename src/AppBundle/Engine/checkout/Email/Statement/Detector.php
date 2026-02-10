<?php

namespace AwardWallet\Engine\checkout\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]checkout51[.]com\b/i',
        ];
    }
}
