<?php

namespace AwardWallet\Engine\azamara\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/\bazamaraclubcruises[.]com\b/i',
        ];
    }
}
