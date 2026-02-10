<?php

namespace AwardWallet\Engine\essoextra\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@essoextra.com',
        ];
    }
}
