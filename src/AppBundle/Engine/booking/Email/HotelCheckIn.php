<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCheckIn extends \TAccountChecker
{
    public $mailFiles = "booking/it-120932425.eml";


    private $detectFrom = 'noreply@booking.com';
    private $detectSubject = [
        // contains all phrases
        // en
        // Confirmation number 3962.565.659: Don’t forget to check-in online for your stay
        ["Confirmation number", "Don’t forget to check-in online for your stay"],
        ["Confirmation number", "Check in for your stay at"],
        // de
        ["Bestätigungsnummer", "Vergessen Sie nicht, für Ihren"],
        ["Bestätigungsnummer", "Check-in in der Unterkunft"],
        // pt
        ["Número de confirmação:", "Faça o check-in para sua estadia em"],
    ];

    private $detectBody = [
        'en' => ['Check-in for your upcoming reservation', 'It\'s time to check in to'],
        'de' => ['Einchecken für Ihren bevorstehenden', "Checken Sie in der Unterkunft"],
        'pt' => ['É hora de fazer o check-in em'],
    ];
    private $lang = 'en';

    private static $dictionary = [
        "en" => [
//            'Confirmation number' => '',
//            'Property details' => '',
//            'From' => '',
//            'Until' => '',
        ],
        "de" => [
            'Confirmation number' => 'Bestätigungsnummer',
            'Property details' => 'Unterkunftsinformationen',
            'From' => 'Von',
            'Until' => 'Bis',
        ],
        "pt" => [
            'Confirmation number' => 'Número de confirmação',
            'Property details' => 'Informações da acomodação',
            'From' => 'De',
            'Until' => 'Até',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (is_array($detectSubject)) {
                $contains = true;
                foreach ($detectSubject as $dSubjectPart) {
                    if (stripos($headers["subject"], $dSubjectPart) === false) {
                        $contains = false;
                        break;
                    }
                }
                if ($contains === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"secure.booking.com/checkin")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Confirmation number")) . "]/following::td[not(.//td)][normalize-space()][1]", null, true,
                "/^\s*([\d\.]{10,})\s*$/"))
        ;

        // Hotel
        $hotelInfo = $this->http->FindNodes("//td[not(.//td)][" . $this->eq($this->t("Property details")) . "]/following::td[not(.//td)][normalize-space()][1]//text()[normalize-space()]");
        if (count($hotelInfo) == 3) {
            $h->hotel()
                ->name($hotelInfo[0])
                ->address($hotelInfo[1])
                ->phone($hotelInfo[2])
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("From")) . "]/following::td[not(.//td)][normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Until")) . "]/following::td[not(.//td)][normalize-space()][1]")))
        ;

        return $email;
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Friday 29 October 2021 (15:00); Dienstag, 26 Oktober 2021 (17:00 Uhr)
            "/^\s*[^\d\s]+\s+(\d+)\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*\(\s*(\d{1,2}:\d{2})\s*(?:Uhr)?\s*\)\s*$/",
            // Friday, November 5, 2021 (15:00)
            "/^\s*[^\d\s]+[\s,]+([[:alpha:]]+)\s+(\d+)[\s,]+(\d{4})\s*\(\s*(\d{1,2}:\d{2})\s*(?:Uhr)?\s*\)\s*$/",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
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
}
