<?php

namespace AwardWallet\Engine\foyles\Email\Statement;

class Detector extends \AwardWallet\Engine\Detector
{
    protected function getFrom(): array
    {
        return [
            '/[@.]foyles[.]co[.]uk\b/i',
        ];
    }
}
