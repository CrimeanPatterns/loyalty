<?php

namespace AwardWallet\Engine\ethiopian\Email;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '@ethiopianairlines.com',
        ];
    }
}
