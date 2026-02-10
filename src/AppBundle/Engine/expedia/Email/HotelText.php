<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelText extends \TAccountChecker
{
    public $mailFiles = "expedia/it-237936730.eml, expedia/it-241880749.eml, expedia/it-625192631.eml";
    public $subjects = [
        '/Expedia travel confirmation/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@expediamail.com') !== false) {
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
        if ($this->http->XPath->query("//a")->length > 0) {
            return false;
        }

        $text = $parser->getBodyStr();

        if (stripos($text, 'Expedia') !== false
            && stripos($text, 'VIEW FULL ITINERARY') !== false
            && stripos($text, 'DOWNLOAD TO YOUR PHONE') !== false) {
            return true;
        }

        if (stripos($text, 'on Expedia TAAP') !== false
            && stripos($text, 'VIEW HOTEL DETAILS') !== false
            && stripos($text, 'IMPORTANT HOTEL INFORMATION') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]expediamail.com$/', $from) > 0;
    }

    public function HotelText(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->re("/Confirmation\s*[#]\n*\s*(\d{8,})/u", $text);

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        } else {
            $h->general()
                ->noConfirmation();
        }

        if (preg_match("/Reserved for\s*\n(?<traveller>\D+)\n\s*(?<guests>\d+)\s*adult/", $text, $m)) {
            $h->general()
                ->traveller($m['traveller']);

            $h->booked()
                ->guests($m['guests']);

            $kids = $this->re("/adult\s*\,\s*(\d+)\s*child/", $text);

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }
        } elseif (preg_match("/No. of guests:\s*Adults\,\s*(\d+)/u", $text, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        if (preg_match("/(Cancellations or changes made after.+\.)\n*\s*(?:Pricing|If you cancel or change your plans|\s*[*]\sPrices)/us", $text, $m)) {
            $h->general()
                ->cancellation(str_replace("\r\n", "", $m[1]));
        }

        if (preg_match("/\s*VIEW HOTEL DETAILS\n*\s*(?<address>.+)\n*\s*Tel\:\s*(?<tel>[+\d\s\(\)]+)(?:\,\s*Fax\:\s*(?<fax>[\d\s\(\)+]+))?\n/ui", $text, $m)) {
            $h->hotel()
                ->address($m['address'])
                ->phone($m['tel']);

            if (isset($m['fax']) && !empty($m['fax'])) {
                $h->hotel()
                    ->fax($m['fax']);
            }
        } elseif (preg_match("/(?<hotelName>.+)\n(?<address>.+)[\s\n]+Check\-in/", $text, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['address']);
        }

        if (preg_match("/Check\-in\s*\n(?<inDate>\w+\,\s*\w+\s*\d{2})\s*\n(?<inTime>.+A?P?M)\s*\n\s*Check-out\s*\n(?<outDate>\w+\,\s*\w+\s*\d{2})\s*\n(?<outTime>.+A?P?M)/", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate'] . ', ' . $m['inTime']))
                ->checkOut($this->normalizeDate($m['outDate'] . ', ' . $m['outTime']));
        } elseif (preg_match("/\s+(?<hotelName>.+)\n\s*(?<inDate>\d+\s*\w+\.\s*\d{4})[\s\-]+(?<outDate>\d+\s*\w+\.\s*\d{4})\s*\,\s*(?<room>\d+)\s*room\s*\|/u", $text, $m)) {
            $m['inTime'] = $this->re("/\s*[*]\s*Check\-in time starts at\s*([\d\:]+\s*A?P?M)/", $text);
            $m['outTime'] = $this->re("/\s*[*]\s*Check-in time ends at\s*(\w+)\s*\n/", $text);

            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate'] . ', ' . $m['inTime']))
                ->checkOut($this->normalizeDate($m['outDate'] . ', ' . $m['outTime']))
                ->rooms($m['room']);

            $h->hotel()
                ->name($m['hotelName']);
        }

        if (preg_match_all("/^Room\s*\d+\s*\n(?<description>.+)\s+\n/mu", $text, $m)) {
            if (count($m[1]) > 0) {
                foreach ($m[1] as $roomType) {
                    $h->addRoom()->setType($roomType);
                }
            }
        } elseif (preg_match("/Accommodation Details\s*\n*(.+)/", $text, $m)) {
            $h->addRoom()->setType($m[1]);
        } elseif (preg_match("/^\s*Room\s*\n*\s*\n\s*(?<description>.+)\s+\n/mu", $text, $m)) {
            $room = $h->addRoom();
            $room->setDescription($m['description']);

            $rate = $this->re("/\s*\d+\s*nights\n*\s*(\D{3}\s*[\d\.\,]*\s*\/night)/u", $text);

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }
        $this->detectDeadLine($h);

        if (preg_match("/{$this->opt($this->t('Total'))}\s*\n\s*(?<currency>\D{1,4})\s*(?<total>[\d\.\,]+)\s*\n/", $text, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->re("/Subtotal\s*\n\s*\D+([\d\.\,]+)\s*\n/", $text);

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->re("/Taxes\s*\n\s*\D+([\d\.\,]+)\s*\n/", $text);

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $spentPoint = $this->re("/([\d\,]+)\s*Expedia\s*Rewards\s*points\s*used/", $text);

            if (!empty($spentPoint)) {
                $h->price()
                    ->spentAwards($spentPoint);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (preg_match("/{$this->opt($this->t('Itinerary #'))}\s*([A-Z\d]{5,})/", $parser->getSubject(), $m)) {
            $email->ota()
                ->confirmation($m[1]);
        }

        $this->HotelText($email, $parser->getBodyStr());

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug($str);
        $year = date("Y", $this->date);

        $in = [
            //Mon, Aug 12, Check-in time starts at 3 PM
            "#^(\w+)\,\s*(\w+)\s*(\d+).+\s(\d+\s*A?P?M)$#u",
            //9 Jan. 2024, midnight
            "#^(\d+)\s*(\w+)\.\s*(\d+).+\s(midnight)$#u",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
            "$1 $2 $3, 12:00AM",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+\s*a?p?m).+on\s+(?<date>\w+\s*\d+\,\s*\d{4})/su", $cancellationText, $m)
        || preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+\s*a?p?m).+on\s*(?<date>\d+\s*\w+\.\s*\d{4})/isu", $cancellationText, $m)) {
            $this->logger->error('YES');
            $h->booked()
                ->deadline(strtotime($m['date'] . ' ' . $m['time']));
        }
    }

    private function currency($s)
    {
        $sym = [
            'MXN$'   => 'MXN',
            'SG$'    => 'SGD',
            'HK$'    => 'HKD',
            'AU$'    => 'AUD',
            '$ CA'   => 'CAD',
            'R$'     => 'BRL',
            'C$'     => 'CAD',
            'kr'     => 'NOK',
            'RM'     => 'MYR',
            '€'      => 'EUR',
            '£'      => 'GBP',
            '฿'      => 'THB',
            '$'      => 'USD',
            'US$'    => 'USD',
        ];

        foreach ($sym as $f=>$r) {
            $s = preg_replace("/(?:^|\s|\d)" . preg_quote($f, '/') . "(?:\d|\s|$)/", " " . $r . " ", $s);
        }

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        return null;
    }
}
