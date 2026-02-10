<?php

namespace AwardWallet\Engine\boots\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]boots[.]com/',
        ];
    }
}
