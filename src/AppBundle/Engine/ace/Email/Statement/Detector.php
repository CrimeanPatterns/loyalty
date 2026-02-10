<?php

namespace AwardWallet\Engine\ace\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]acehardware[.]com\b/i',
        ];
    }
}
