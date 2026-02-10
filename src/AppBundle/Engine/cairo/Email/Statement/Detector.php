<?php

namespace AwardWallet\Engine\cairo\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@flyaircairo.com',
        ];
    }
}
