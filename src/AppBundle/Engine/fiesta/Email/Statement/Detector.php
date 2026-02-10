<?php

namespace AwardWallet\Engine\fiesta\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]posadas[.]com\b/i',
        ];
    }
}
