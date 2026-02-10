<?php

namespace AwardWallet\Engine\flybuys\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]flybuys\b/',
        ];
    }
}
