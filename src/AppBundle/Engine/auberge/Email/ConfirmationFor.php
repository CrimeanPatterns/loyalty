<?php

namespace AwardWallet\Engine\auberge\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "auberge/it-84231422.eml";
    public $subjects = [
        '/^\D+Confirmation for \D+ \- Arrival Date\:/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'RELAXED CANCELLATION POLICY' => ['RELAXED CANCELLATION POLICY', 'EXTENDED A RELAXED CANCELLATION POLICY', 'CANCELLATION POLICY'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e-destinations.us	') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Auberge')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR PERSONAL DOSSIER'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('PLEASE REVIEW YOUR RESERVATION DETAILS BELOW'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\-destinations\.us$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest Name:')]", null, true, "/{$this->opt($this->t('Guest Name:'))}\s*(\D+)$/");

        if (!empty($traveller) && stripos($traveller, 'and') !== false) {
            $travellers = explode('and', $traveller);
            $h->general()
                ->travellers($travellers, true);
        } elseif (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]+)$/"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('RELAXED CANCELLATION POLICY'))}]/following::text()[normalize-space()][1]/ancestor::td[1]"));

        $hotelInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for choosing')]/ancestor::tr[1]");

        if (preg_match("/Thank you for choosing(?<name>.+)for your upcoming getaway to(?<address>.+)\.\s*We look.+contact us directly at (?<phone>[\d\.]+)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $inDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date:')]", null, true, "/{$this->opt($this->t('Arrival Date:'))}\s*(.+)$/");
        $inTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In:')]", null, true, "/{$this->opt($this->t('Check-In:'))}\s*(.+)$/");

        $outDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date:')]", null, true, "/{$this->opt($this->t('Departure Date:'))}\s*(.+)$/");
        $outTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out:')]", null, true, "/{$this->opt($this->t('Check-Out:'))}\s*(.+)$/");

        $h->booked()
            ->checkIn($this->normalizeDate($inDate . ', ' . $inTime))
            ->checkOut($this->normalizeDate($outDate . ', ' . $outTime));

        $type = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Accommodations:')]", null, true, "/{$this->opt($this->t('Accommodations:'))}\s*(.+)$/");
        $rate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Nightly Rate:')]", null, true, "/{$this->opt($this->t('Nightly Rate:'))}\s*(.+)$/");

        if (!empty($rate) || !empty($type)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate($rate);
            }

            if (!empty($type)) {
                $room->setType($type);
            }
        }

        $this->detectDeadLine($h, $h->getCancellation());

        $priceInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Room and Tax:')]", null, true, "/{$this->opt($this->t('Total Room and Tax:'))}\s*(.+)$/");

        if (!empty($priceInfo)) {
            $h->price()
                ->currency($this->normalizeCurrency($this->re("/^(\S{1})[\d\.\,]+$/", $priceInfo)))
                ->total(cost($this->re("/^\S{1}([\d\.\,]+)$/", $priceInfo)));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->error($date);
        $in = [
            '#^(\w+\,\s*\w+\s*\d+\,\s*\d{4}\,\s*\d+\s*)Noon$#', //April 05 (Mon), 2021
        ];
        $out = [
            '$1 PM',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h, $cancellationText)
    {
        if (preg_match("#Revisions or cancellations must be made (\d+\s*days?) prior to the scheduled arrival date in order to receive a full refund#i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            '$'   => ['$'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
