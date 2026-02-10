<?php

namespace AwardWallet\Engine\calvinklein\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]calvinklein[.]com/i',
        ];
    }
}
