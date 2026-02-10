<?php

namespace AwardWallet\Engine\emiratesky\RewardAvailability;

use AwardWallet\Engine\skywards\RewardAvailability\Parser as SkywardsParser;

class Parser extends SkywardsParser
{
    public $partners = true;
    public static $useMobile = false;
    public $isRewardAvailability = true;

    /*    public static function GetAccountChecker($accountInfo)
        {
            // no mobile
            return new static();
        }*/
}
