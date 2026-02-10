<?php

namespace AwardWallet\Engine\auberge\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmationText extends \TAccountChecker
{
    public $mailFiles = "auberge/it-197045315.eml";
    public $subjects = [
        '/^Reservation Confirmation$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aubergeresorts.com') !== false) {
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
        $body = $parser->getBody();

        if (stripos($body, 'Auberge Resorts Collection') !== false && stripos($body, 'Number of guests:') !== false && stripos($body, 'Arrival Date:') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aubergeresorts\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/Your confirmation number is\:\s*([A-Z\d]{5,})/", $text))
            ->cancellation($this->re("/Policies\:\s*(.+)/", $text));

        $h->hotel()
            ->name($this->re("/Thank you for making a reservation at\s*(.+)/u", $text))
            ->noAddress()
            ->phone($this->re("/reservation department\s*at\s*([\d\-]+)/", $text));

        $h->booked()
            ->checkIn(strtotime($this->re("/Arrival Date\:\s*(.+)/", $text)))
            ->checkOut(strtotime($this->re("/Departure Date\:\s*(.+)/", $text)))
            ->guests($this->re("/Number of guests\:\s*(\d+)/", $text));

        $rate = $this->re("/Room Rate:\s*(.+)/", $text);

        $type = $this->re("/Room Type\:\s*(.+)/", $text);

        if (!empty($rate) || !empty($type)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate($rate);
            }

            if (!empty($type)) {
                $room->setType($type);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getBody();

        $this->ParseHotel($email, $body);

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
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

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
