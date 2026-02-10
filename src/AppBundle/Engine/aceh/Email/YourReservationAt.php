<?php

namespace AwardWallet\Engine\aceh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "aceh/it-213728221.eml";
    public $subjects = [
        'Your reservation at Ace',
    ];

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
            'Cancellation Policy'   => ['Cancellation Policy', 'Cancel Policy', 'Guarantee and Cancel Policy'],
            'BOOK A TABLE AT ALDER' => ['BOOK A TABLE AT ALDER', 'BOOK A TABLE AT LOAM'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@acehotel.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Ace Hotel')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('MANAGE YOUR RESERVATION'))}] | //img[{$this->contains($this->t('MANAGE YOUR RESERVATION'), '@alt')}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('JOIN THE A-LIST'))}] | //img[{$this->contains($this->t('BOOK A TABLE AT ALDER'), '@alt')}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acehotel\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='CONFIRMATION NUMBER']/ancestor::td[1]", null, true, "/{$this->opt($this->t('CONFIRMATION NUMBER'))}\s*([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='GUEST NAME']/ancestor::td[1]", null, true, "/{$this->opt($this->t('GUEST NAME'))}\s*(\D+)/"))
            ->cancellation(trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]"), ':'));

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL COST INCLUDING TAXES']/ancestor::td[1]");

        if (preg_match("/{$this->opt($this->t('TOTAL COST INCLUDING TAXES'))}\s*(?<currency>[A-Z]{3})\D(?<total>[\d\.\,]+)$/", $priceText, $m)
        || preg_match("/{$this->opt($this->t('TOTAL COST INCLUDING TAXES'))}\s*(?<currency>\D{2})\s*(?<total>[\d\,\.]+)$/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-IN']/ancestor::td[1]", null, true, "/{$this->opt($this->t('CHECK-IN'))}\s*(.+)/");
        $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-OUT']/ancestor::td[1]", null, true, "/{$this->opt($this->t('CHECK-OUT'))}\s*(.+)/");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE']/ancestor::td[1]", null, true, "/{$this->opt($this->t('ROOM TYPE'))}\s*(.+)/");
        $rateType = $this->http->FindSingleNode("//text()[normalize-space()='RATE TYPE']/ancestor::td[1]", null, true, "/{$this->opt($this->t('RATE TYPE'))}\s*(.+)/");

        if (!empty($roomType) || !empty($rateType)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ace Hotel')]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/(?<hotelName>.+)\n(?<address>.+)\n(?<phone>[\d\.\s\+]{8,})/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['address'])
                ->phone($m['phone']);
        } else {
            $this->logger->debug($this->subject);
            $h->hotel()
                ->name($this->re("/Your reservation at\s*(.+)\s*\–/u", $this->subject))
                ->noAddress();
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains({$text}, \"{$s}\")";
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
        $this->logger->debug('$date = ' . print_r($date, true));

        $in = [
            // October 25, 2022 Time: 3:00 PM
            "/^(\w+)\s*(\d+)\,\s*(\d{4})\s*Time\:\s*([\d\:]+\s*A?P?M)$/",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel by (?<hours>[\d\:]+a?p?m)\s*\w+\,\s*(?<prior>\d+\s*days?) prior to arrival, to avoid being charged first night's room and tax/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'], $m['hours']);
        }
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
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
