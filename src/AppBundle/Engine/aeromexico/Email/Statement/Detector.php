<?php

namespace AwardWallet\Engine\aeromexico\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]clubpremier[.]com\b/i',
        ];
    }
}
