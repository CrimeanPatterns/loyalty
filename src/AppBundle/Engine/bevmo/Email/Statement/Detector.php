<?php

namespace AwardWallet\Engine\bevmo\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]bevmo[.]com/',
        ];
    }
}
