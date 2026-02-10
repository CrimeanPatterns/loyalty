<?php

namespace AwardWallet\Engine\astana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = 'astana/it-5769685.eml';

    private $detectSubject = [
        // en
        'Confirmation for reservation',
        // ru
        'Подтверждение для заказа',
    ];

    private $detectBody = [
        'en' => [
            'Thank you for choosing Air Astana and we wish you a pleasant flight',
        ],
        'ru' => [
            'Спасибо, что воспользовались услугами авиакомпании «Эйр Астана»',
        ],
    ];

    private $lang = "en";
    private static $dictionary = [
        "en" => [
//            "Booking reference:" => "",
//            "Ticket number:" => "",
//            "First name" => "",
//            "Last name" => "",

//            "Date:" => "",
//            "Departure:" => "",
//            "Arrival:" => "",
//            "Airline:" => "",
//            "Flight number:" => "",
//            "Aircraft type:" => "",
//            "Class:" => "",

//            "Total for all passengers" => "",
//            "points" => "",
        ],
        "ru" => [
            "Booking reference:" => "Номер брони:",
            "Ticket number:" => "Номер билета:",
            "First name" => "Имя ",
            "Last name" => "Фамилия",

            "Date:" => "Дата:",
            "Departure:" => "Вылет:",
            "Arrival:" => "Прибытие:",
            "Airline:" => "Авиакомпания:",
            "Flight number:" => "Номер рейса:",
            "Aircraft type:" => "Тип ВС:",
            "Class:" => "Класс:",

            "Total for all passengers" => "Итого:",
            "points" => "баллов",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@airastana.com') === false) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airastana.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.airastana.com")]')->length === 0) {
            return false;
        }
        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query('//*['.$this->contains($dBody).']')->length > 0) {
                return true;
            }
        }
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query("//*[" . $this->contains($dict['Booking reference:']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'FlightReservation',
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode('//*['.$this->eq($this->t("Booking reference:")).']/following-sibling::*[normalize-space(.)!=""][1]',
                null, true, '/^\s*([A-Z\d]{5,7})\s*$/'))
        ;
        $firstNames = $this->http->XPath->query('//*['.$this->starts($this->t("First name")).' and count(.//text()[normalize-space(.)!=""])>1]');
        foreach ($firstNames as $root) {
            $f->general()
                ->traveller($this->http->FindSingleNode('(.//text()[normalize-space(.)!=""])[2]', $root) . ' '
                    . $this->http->FindSingleNode('(./following-sibling::*['.$this->starts($this->t("Last name")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root), true);
        }

        // Issued
        $f->issued()
            ->tickets($this->http->FindNodes('//*['.$this->eq($this->t("Ticket number:")).']/following-sibling::*[normalize-space(.)!=""][1]//text()[normalize-space(.)!=""]', null, '/([-\d]+)/'), false);

        // Segments
        $segments = $this->http->XPath->query('//*['.$this->starts($this->t("Departure:")).' and count(.//text()[normalize-space(.)!=""])>1]');
        foreach ($segments as $root) {

            $s = $f->addSegment();

            $date = $this->http->FindSingleNode('(./preceding-sibling::*['.$this->starts($this->t("Date:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root);

            // Departure
            $departure = $this->http->FindSingleNode('.//*[normalize-space(.)!="" and (name(.)="b" or name(.)="strong")]/ancestor::*[1]', $root);
            if (preg_match('/(\d{1,2}:\d{2})\s+(.+?)\s+\(([A-Z]{3})\)/', $departure, $m)) {
                $s->departure()
                    ->name($m[2])
                    ->code($m[3])
                    ->date((!empty($date))? strtotime($date . ' ' . $m[1]) : null);
            }

            // Arrival
            $arrival = $this->http->FindSingleNode('./following-sibling::*['.$this->starts($this->t("Arrival:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//*[normalize-space(.)!="" and (name(.)="b" or name(.)="strong")]/ancestor::*[1]', $root);
            if (preg_match('/(\d{1,2}:\d{2})\s+(.+?)\s+\(([A-Z]{3})\)/', $arrival, $m)) {
                $s->arrival()
                    ->name($m[2])
                    ->code($m[3])
                    ->date((!empty($date))? strtotime($date . ' ' . $m[1]) : null);
            }

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode('(./following::*['.$this->starts($this->t("Airline:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root, true, "/\(([A-Z\d]{2})\)/"))
                ->number($this->http->FindSingleNode('(./following::*['.$this->starts($this->t("Flight number:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root, true, '/^\s*(\d+)/'))
            ;

            // Extra
            $s->extra()
                ->aircraft($this->http->FindSingleNode('(./following::*['.$this->starts($this->t("Aircraft type:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root))
                ->cabin($this->http->FindSingleNode('(./following::*['.$this->starts($this->t("Class:")).' and count(.//text()[normalize-space(.)!=""])>1][1]//text()[normalize-space(.)!=""])[2]', $root))
            ;
        }

        $spentAwards = $this->http->FindSingleNode('(//*['.$this->starts($this->t("Total for all passengers")).' and (name(.)="b" or name(.)="strong")]/ancestor::*[count(*)=3][1]/*[2]//text()[normalize-space(.)!=""])[last()]',
            null, true, "/^\s*\d[\d,.]*\s*".$this->preg_implode($this->t("points"))."\s*$/");
        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }
        $total = $this->http->FindSingleNode('(//*['.$this->starts($this->t("Total for all passengers")).' and (name(.)="b" or name(.)="strong")]/ancestor::*[count(*)=3][1]/*[3]//text()[normalize-space(.)!=""])[last()]');
        if (preg_match('/^(\d+)\s+([^\d\s]+)$/', $total, $m)) {
            $f->price()
                ->total($m[1])
                ->currency($m[2]);
        }

        return $email;
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 18 de fevereiro de 2020, 12:00
            "#^\s*(\d{1,2}) de ([^\s\d]+) de (\d{4})\s*, \s*(\d+:\d+)\s*$#iu",
            // samedi 10 juillet 2021, 14:00
            // Sonntag, 3. Juli 2022, 11:00

            "#^\s*[[:alpha:]]+,?\s+(\d{1,2}).? ([^\s\d]+) (\d{4})\s*, \s*(\d+:\d+)\s*$#iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return (!empty($str)) ? strtotime($str) : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

}
