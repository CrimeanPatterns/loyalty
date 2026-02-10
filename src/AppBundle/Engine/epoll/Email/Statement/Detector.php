<?php

namespace AwardWallet\Engine\epoll\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]epoll[.]com/i',
        ];
    }
}
