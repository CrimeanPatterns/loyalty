<?php

namespace AwardWallet\Engine\bing\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]engage[.]windows[.]com\b/i',
        ];
    }
}
