<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "fairmont/it-1939454.eml, fairmont/it-3035360.eml, fairmont/it-81352990.eml";

    public $subjects = [
        '/Reservation Confirmation$/',
        '/Reservation Confirmation:\s*\d+\-\w+\-\d+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'checkIn'  => ['Arrival Date', 'Check-In'],
            'checkOut' => ['Departure Date', 'Check-Out'],
            'Adults'   => ['Adults', 'ADULTS', 'Adult'],
            'child'    => ['Child', 'CHILD'],
            'rate'     => ['Room Rate', 'Average Room Rate'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fairmont.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Fairmont')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation #'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Promotional Code'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]")->length > 0);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]", null, true, "/{$this->opt($this->t('Confirmation #'))}\s*(\d+)/"), 'Confirmation #')
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}][1]/preceding::text()[{$this->starts($this->t('Dear'))}][1]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)/"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancel Policy'))}]/following::text()[normalize-space()][1]"));

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Toll Free'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<hotel>.{3,})\n(?:(?<address>.{3,}|.+\n.+)\n{$this->opt($this->t('Toll Free'))}).+/", $hotelInfo, $m)) {
            $h->hotel()->name($m['hotel'])->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        if (preg_match("/\n{$this->opt($this->t('Toll Free'))}\s+({$patterns['phone']})$/m", $hotelInfo, $m)
            || preg_match("/\n{$this->opt($this->t('Tel'))}\s+({$patterns['phone']})$/m", $hotelInfo, $m)
        ) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/\n{$this->opt($this->t('Fax'))}\s+({$patterns['phone']})$/m", $hotelInfo, $m)) {
            $h->hotel()->fax($m[1]);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOut'))}]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}]/following::text()[normalize-space()][1]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Adults'))}/"))
            ->kids($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}]/following::text()[normalize-space()][1]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/"));

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/following::text()[normalize-space()][1]");
        $roomDescription = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Description'))}]/following::text()[normalize-space()][not(starts-with(normalize-space(), '<!--'))][1]");
        $rateDescription = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Description'))}]/following::text()[normalize-space()][not(starts-with(normalize-space(), '<!--'))][1]");
        $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('rate'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/");

        if (!empty($roomType) || !empty($roomDescription) || !empty($rateDescription) || !empty($rate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            if (!empty($rateDescription)) {
                $room->setRateType($rateDescription);
            }

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total with Taxes'))}]/following::text()[normalize-space()][1]", null, true, "/^\D?([\d\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total with Taxes'))}]/following::text()[normalize-space()][1]", null, true, "/^\D?[\d\.]+\s*([A-Z]{3})/");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Number'))}]/following::text()[normalize-space()][1]");

        if (!empty($account) && $account !== 'None') {
            $h->program()
                ->account($account, false);
        }

        $this->detectDeadLine($h);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fairmont\.com$/', $from) > 0;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})$#u", //05-Mar-2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^CXL BY (\d+)\/(\d+)\/(\d+)\s*(\d+A?P?M)$/ui", $cancellationText, $m)
        ) {
            //it-81352990
            if ($m[2] <= 12) {
                $h->booked()
                    ->deadline(strtotime($m[1] . '.' . $m[2] . '.20' . $m[3] . ', ' . $m[4]));
            } else {
                $h->booked()
                    ->deadline(strtotime($m[2] . '.' . $m[1] . '.20' . $m[3] . ', ' . $m[4]));
            }
        }

        if (preg_match("/^CXL BY (\d+)\-(\w+)\-(\d+)\s*(\d+A?P?M)$/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ', ' . $m[4]));
        }
    }
}
