<?php

namespace AwardWallet\Engine\avis\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourStatement extends \TAccountChecker
{
    public $mailFiles = "avis/it-75599960.eml, avis/it-76248718.eml, avis/statements/it-62645178.eml, avis/statements/it-62645505.eml, avis/statements/it-63037853.eml, avis/statements/it-63041461.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'AVAILABLE POINTS' => ['AVAILABLE POINTS', 'Available Points*'],
        ],
    ];

    private $enDatesInverted = false;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'avis@e.avis.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'your Monthly Avis Statement') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".avis.com/") or contains(@href,"click.e.avis.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"This message is sent by Avis") or contains(.,"www.avis.com") or contains(.,"avis@e.avis.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(),"Statement activity is current as of the end of the previous month")]')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Dear")]', null, true, '/^Dear\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;?!]|$)/iu');
        $st->addProperty('Name', $name);

        if ($this->http->XPath->query('//text()[starts-with(normalize-space(),"TIER:") or starts-with(normalize-space(),"CURRENT POINT BALANCE:")]')->length > 0) {
            // it-63037853.eml

            $tier = $this->http->FindSingleNode('//td[not(.//td) and starts-with(normalize-space(),"TIER:")]', null, true, '/^TIER:\s*([^:]+)$/i');
            $st->addProperty('Status', $tier);

            $points = $this->http->FindSingleNode('//td[not(.//td) and starts-with(normalize-space(),"CURRENT POINT BALANCE:")]', null, true, '/^CURRENT POINT BALANCE:\s*(\d[,.\'\d ]*)$/i');

            if ($points !== null) {
                $st->setBalance($this->normalizeAmount($points));
            } elseif ($this->http->XPath->query('//td[not(.//td) and starts-with(normalize-space(),"IN YOUR PROFILE, OPT-IN TO EARN POINTS")]')->length > 0
                || $this->http->XPath->query('//text()[starts-with(normalize-space(),"Dear")]/preceding::tr[not(.//tr) and starts-with(normalize-space(),"TIER:")][1][count(*[' . $xpathNoEmpty . '])=1]')->length > 0
            ) {
                $st->setNoBalance(true);
            }
        } elseif ($this->http->XPath->query('//text()[normalize-space(.)="Your Account Activity"]')->length > 0) {
            $points = $this->http->FindSingleNode("//text()[{$this->contains($this->t('AVAILABLE POINTS'))}]/ancestor::td[1]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('AVAILABLE POINTS'))}/u");

            if ($points !== null) {
                $st->setBalance($this->normalizeAmount($points));
            } else {
                $st->setNoBalance(true);
            }

            $infoText = $this->http->FindSingleNode("//text()[normalize-space(.)='Membership Tier:']/ancestor::td[1]");

            if (preg_match("/^(\D+)\s+Membership Tier:\s*(\D+)\s+Wizard \#\:\s*([A-Z\d\*]+)$/", $infoText, $m)) {
                $st->addProperty('Name', $m[1]);
                $st->addProperty('Status', $m[2]);
                $st->setNumber(str_replace('***', '**', $m[3]))->masked('right');
            }
        } else {
            // it-63041461.eml

            $pointsExpire = $this->http->FindSingleNode('//tr[not(.//tr) and starts-with(normalize-space(),"Your Points Expire on")]', null, true, '/^Your Points Expire on\s*(.{6,})$/i');

            if ($pointsExpire !== null) {
                $st->parseExpirationDate($this->normalizeDate($pointsExpire));
            }
            $points = $this->http->FindSingleNode("//tr[{$this->eq($this->t('AVAILABLE POINTS'))}]/preceding-sibling::tr[normalize-space()][1]", null, true, '/^\d[,.\'\d ]*$/');

            if ($points !== null) {
                $st->setBalance($this->normalizeAmount($points));
            } elseif ($this->http->XPath->query('//a[normalize-space()="Opt In Today"]')->length > 0
                || $this->http->XPath->query('//tr[ descendant::img[contains(@alt,"CURRENT RENTALS") or contains(@title,"CURRENT RENTALS")] ]/following-sibling::tr[ descendant::a[normalize-space()="View My Profile"] ]')->length > 0
            ) {
                $st->setNoBalance(true);
            }

//            $preferredPlusConditions = implode(' ', $this->http->FindNodes('//tr[ *[1][normalize-space()="UNLOCK PREFERRED PLUS:"] ]/*[2]/descendant::text()[normalize-space()]'));
//            if ( preg_match("/You’re\s*(\d{1,3})\s*rentals away from Avis Preferred Plus/i", $preferredPlusConditions, $m) ) {
//                $st->addProperty('RentalsToNextTier', $m[1]);
//            }
//            if ( preg_match("/You’re\s*(.{1,15}?)\s*spend away from Avis Preferred Plus/i", $preferredPlusConditions, $m) ) {
//                $st->addProperty('SpendToNextTier', $m[1]);
//            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        // 03/15/2018
        $in[0] = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';
        /*//Nov 30, 2020
        $in[1] = '/^(\w+)\s*(\d+)\,\s*(\d{4})$/';
        $out[1] = '$2 $1 $3';*/
        return preg_replace($in, $out, $text);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
