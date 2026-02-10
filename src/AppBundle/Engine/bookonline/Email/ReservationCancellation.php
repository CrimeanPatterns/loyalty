<?php

namespace AwardWallet\Engine\bookonline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "bookonline/it-695060707.eml";

    public $emailSubject;
    public $lang;
    public static $dictionary = [
        'en' => [
            'RESERVATION CANCELLATION' => 'RESERVATION CANCELLATION',
            'Arrival'                  => 'Arrival',
            'Departure'                => 'Departure',
            'Persons'                  => 'Persons',
            'Adults:'                  => 'Adults:',
            'Kids:'                    => 'Kids:',
            'Confirmation Number'      => 'Confirmation Number',
            'Rate'                     => 'Rate',
            'Room Type'                => 'Room Type',
            'Contact'                  => 'Contact',
            'Surname'                  => 'Surname',
            'Name'                     => 'Name',
            'Numero do cancelamento:'  => 'Numero do cancelamento:',
        ],
    ];

    private $detectFrom = "@book-onlinenow.net";
    private $detectSubject = [
        'Reservation Cancellation',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]book-onlinenow\.net$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.book-onlinenow.net'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@book-onlinenow.net'])}]")->length === 0
            && stripos($parser->getCleanFrom(), $this->detectFrom) === false
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['RESERVATION CANCELLATION']) && !empty($dict['Arrival'])
                && !empty($dict['Persons'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['RESERVATION CANCELLATION'])}]"
                    . "/following::text()[{$this->eq($dict['Arrival'])}]"
                    . "/following::text()[{$this->eq($dict['Persons'])}]"
                )->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["RESERVATION CANCELLATION"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['RESERVATION CANCELLATION'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t('Confirmation Number'), "/^\s*(RES\d+[\dA-Z]+)\s*$/"))
            ->traveller(implode(' ', [
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact'))}]/following::tr[not(.//tr)][position() < 5][count(*) = 2][*[1][{$this->eq($this->t('Name'))}]]/*[2]"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact'))}]/following::tr[not(.//tr)][position() < 5][count(*) = 2][*[1][{$this->eq($this->t('Surname'))}]]/*[2]"),
            ]), true);

        if ($this->http->XPath->query("//*[{$this->contains($this->t('RESERVATION CANCELLATION'))} or {$this->contains($this->t('Numero do cancelamento:'))}]")->length > 0) {
            $h->general()
                ->status('cancelled')
                ->cancelled();
        }
        // Hotel
        $hotelText = $this->http->FindSingleNode("//tr[{$this->eq($this->t('RESERVATION CANCELLATION'))}]/following::tr[not(.//tr)][normalize-space()][1]"
            . "[following::text()[normalize-space()][1][{$this->eq($this->t('Arrival'))}]]");

        if (preg_match("/^\s*(.+?) - (.+?)\s+(T:.+)/", $hotelText, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($m[2])
            ;

            if (preg_match("/^\s*T:\s*([\d\(\)\+\- ]{5,}?)(?:\s*\/|\s*F:)/", $m[3], $mat)) {
                $h->hotel()
                    ->phone($mat[1]);
            }

            if (preg_match("/\s+F:\s*([\d\(\)\+\- ]{5,}?)(?:\s*\/|\s*$)/", $m[3], $mat)) {
                $h->hotel()
                    ->fax($mat[1]);
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('Arrival'))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('Departure'))))
            ->guests($this->nextTd($this->t('Persons'), "/{$this->opt($this->t('Adults:'))}\s*(\d+)\b/"))
            ->kids($this->nextTd($this->t('Persons'), "/{$this->opt($this->t('Kids:'))}\s*(\d+)\b/"))
        ;
        $time = $this->nextTd($this->t('Arrival Time'), "/^\s*(\d{1,2}:\d{2})\s*$/");

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        // Rooms
        $h->addRoom()
            ->setType($this->nextTd($this->t('Room Type')))
            ->setRateType($this->nextTd($this->t('Rate')))
        ;

        return true;
    }

    private function nextTd($field, $regexp = null, $root = null)
    {
        return $this->http->FindSingleNode("//tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($field)}]]/*[2]", $root, true, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            // vi. 26 jul. 2024
            '/^\s*[-[:alpha:]]+[\.\s]\s*(\d+)\s*([[:alpha:]]+)[\.]?\s+(\d{4})\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
