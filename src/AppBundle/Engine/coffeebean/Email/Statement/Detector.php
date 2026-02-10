<?php

namespace AwardWallet\Engine\coffeebean\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]coffeebean[.]com/',
        ];
    }
}
