<?php

namespace AwardWallet\Engine\acerentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1969940 extends \TAccountCheckerExtended
{
    public $mailFiles = "acerentacar/it-1969932.eml, acerentacar/it-1969940.eml, acerentacar/it-1970102.eml, acerentacar/it-1970359.eml, acerentacar/it-1980956.eml, acerentacar/it-91997392.eml";

    public $subjects = [
        '/ACE Rent A Car - Reservation Confirmation/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Office Hours:'    => ['Office Hours:', 'OFFICE HOURS'],
            'Estimated Total:' => ['Estimated Total:', 'ESTIMATED TOTAL PRICE:'],
            'Total Discount:'  => ['Total Discount:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@acerentacar.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ACE Rent A Car')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation #:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Return Location:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acerentacar\.com$/', $from) > 0;
    }

    public function ParseEmailCar(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation #:']/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Reserved For:']/following::text()[normalize-space()][1]"));

        $pickDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Date:')]/following::text()[normalize-space()][1]");
        $pickTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Time:')]/following::text()[normalize-space()][1]");

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Location:')]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($pickDate . ', ' . $pickTime));

        $dropDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Date:')]/following::text()[normalize-space()][1]");
        $dropTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Time:')]/following::text()[normalize-space()][1]");

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Location:')]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($dropDate . ', ' . $dropTime));

        $r->car()
            ->model($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Make/Model:')]/following::text()[normalize-space()][1]"));

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Reservation Summary:']/following::text()[normalize-space()='Estimated Total:'][1]/following::text()[normalize-space()][1]");

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'United States Dollars')]")->length > 0) {
            $currency = 'USD';
        }

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total($total)
                ->currency($currency);

            $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Discount:'))}]", null, true, "/{$this->opt($this->t('Total Discount:'))}\s*\D([\d\.\,]+)$/");

            if (!empty($discount)) {
                $r->price()
                    ->discount($discount);
            }

            $feeNodes = $this->http->FindNodes("//text()[normalize-space()='Daily Rate']/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'Fee') or contains(normalize-space(), 'Tax')][not(contains(normalize-space(), 'Taxes'))]");

            foreach ($feeNodes as $feeRow) {
                $feeSumm = $this->re("/[$]([\d\.\,]+)/u", $feeRow);
                $feeName = $this->re("/^(.+)\s*[$]/u", $feeRow);

                $r->price()
                    ->fee($feeName, $feeSumm);
            }
        }

        $hoursText = $this->http->FindSingleNode("//text()[normalize-space()='Office Hours:']/following::text()[normalize-space()][1]/ancestor::tr[1]");
        $hours = $this->re("/{$this->opt($this->t('Office Hours:'))}[\=\s]+(.+){$this->opt($this->t('Late Arrival Policy:'))}/s", $hoursText);

        if (!empty($hours)) {
            $r->pickup()
                ->openingHours($hours);

            $r->dropoff()
                ->openingHours($hours);
        }
    }

    public function ParseEmailCar2(Email $email, $text)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation #:'))}\s*([A-Z\d]+)/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Reserved For:'))}\s*(.+)/", $text));

        if (!empty($this->re("/(pleasure to confirm your reservation)/", $text))) {
            $r->general()
                ->status('confirmed');
        }

        if (!empty($this->re("/(is holding the following reservation)/", $text))) {
            $r->general()
                ->status('on hold');
        }

        $pickDate = $this->re("/{$this->opt($this->t('Pickup Date:'))}\s*(.+)/", $text);
        $pickTime = $this->re("/{$this->opt($this->t('Pickup Time:'))}\s*(.+)/", $text);

        $hours = $this->re("/{$this->opt($this->t('Office Hours:'))}[\=\s]+(.+){$this->opt($this->t('Late Arrival Policy:'))}/s", $text);

        if (!empty($hours)) {
            $r->pickup()
                ->openingHours(str_replace("\n", ", ", trim($hours)));

            $r->dropoff()
                ->openingHours(str_replace("\n", ", ", trim($hours)));
        }

        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('Pickup Location:'))}\s*(.+)/", $text))
            ->date($this->normalizeDate($pickDate . ', ' . $pickTime));

        $dropDate = $this->re("/{$this->opt($this->t('Return Date:'))}\s*(.+)/", $text);
        $dropTime = $this->re("/{$this->opt($this->t('Return Time:'))}\s*(.+)/", $text);

        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('Return Location:'))}\s*(.+)/", $text))
            ->date($this->normalizeDate($dropDate . ', ' . $dropTime));

        $r->car()
            ->model($this->re("#{$this->opt($this->t('Make/Model:'))}\s*(.+)#", $text));

        $total = $this->re("/{$this->opt($this->t('Estimated Total:'))}\s*([\d\,\.]+)/", $text);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('United States Dollars'))}]")->length > 0) {
            $currency = 'USD';
        }

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total($total)
                ->currency($currency);

            $this->logger->error("#{$this->opt($this->t('Total Discount:'))}\s*(\D{1})?([\d\.\,]+)#u");
            $this->logger->error($text);
            $discount = $this->re("#{$this->opt($this->t('Total Discount:'))}\s*(\D{1})?([\d\.\,]+)#u", $text);

            if (!empty($discount)) {
                $r->price()
                    ->discount($discount);
            }

            $feeText = $this->re("/{$this->opt($this->t('Extra Mileage Charge:'))}\s*None\n(.+){$this->opt($this->t('Estimated Total:'))}/s", $text);
            $this->logger->error($feeText);
            $feeRows = explode("\n", $feeText);

            foreach ($feeRows as $feeRow) {
                if (preg_match("/^(.+)\:\s*([\d\.\,]+)/", $feeRow, $m)) {
                    $r->price()
                        ->fee($m[1], $m[2]);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation #:'))}]/ancestor::tr[1]")->length > 0) {
            $this->ParseEmailCar($email);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation #:'))}]")->length > 0) {
            $text = $parser->getPlainBody();
            $this->ParseEmailCar2($email, $text);
        }

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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#", //July 04, 2021, 05:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
