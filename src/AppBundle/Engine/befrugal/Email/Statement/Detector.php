<?php

namespace AwardWallet\Engine\befrugal\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@befrugal.com',
        ];
    }
}
