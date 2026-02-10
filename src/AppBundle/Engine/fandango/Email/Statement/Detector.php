<?php

namespace AwardWallet\Engine\fandango\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]fandango[.]com/i',
        ];
    }
}
