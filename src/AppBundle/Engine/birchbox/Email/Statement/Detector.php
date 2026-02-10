<?php

namespace AwardWallet\Engine\birchbox\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[.@]birchbox[.]com/',
        ];
    }
}
