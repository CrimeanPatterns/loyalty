<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingAt extends \TAccountChecker
{
    public $mailFiles = "booking/it-43685090.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation number:', 'Confirmation number :'],
            'checkIn'    => ['Check-in', 'Check-in:'],
        ],
        'nl' => [
            'Hi '              => ['Hallo '],
            'Your booking at'  => 'Uw boeking bij',
            'confNumber'       => ['Bevestigingsnummer:'],
            'checkIn'          => ['Inchecken'],
            'Check-out'        => 'Uitchecken',
            'or call them at:' => 'of bel ze op',
        ],
        'it' => [
            'Hi '              => ['Ciao '],
            'Your booking at'  => 'La tua prenotazione per',
            'confNumber'       => ['Numero di conferma:'],
            'checkIn'          => ['Check-in'],
            'Check-out'        => 'Check-out',
            'or call them at:' => 'o telefonicamente al numero',
        ],
        'pt' => [
            'Hi '              => ['Olá,'],
            'Your booking at'  => 'Sua reserva em',
            'confNumber'       => ['Número de confirmação:'],
            'checkIn'          => ['Check-in'],
            'Check-out'        => 'Check-out',
            'or call them at:' => 'ou ligue para:',
        ],
        'pl' => [
            'Hi '              => ['Witaj,'],
            'Your booking at'  => 'Twoja rezerwacja w obiekcie',
            'confNumber'       => ['Numer potwierdzenia rezerwacji:'],
            'checkIn'          => ['Zameldowanie'],
            'Check-out'        => 'Wymeldowanie',
            'or call them at:' => 'lub zadzwoń pod numer:',
        ],
        'es' => [
            'Hi '              => ['Hola,'],
            'Your booking at'  => 'Tu reserva en',
            'confNumber'       => ['Número de confirmación:'],
            'checkIn'          => ['Check-in'],
            'Check-out'        => 'Check-out',
            'or call them at:' => 'en contacto con el alojamiento llamando al',
        ],
    ];

    private $detectors = [
        'en' => ['Your booking at'],
        'nl' => ['Uw boeking bij'],
        'it' => ['La tua prenotazione per'],
        'pt' => ['Sua reserva em'],
        'pl' => ['Twoja rezerwacja w obiekcie '],
        'es' => ['Tu reserva en '],
    ];

    private $detectSubject = [
        'en' => [
            ['Tell ', 'when you\'ll arrive'],
        ],
        'it' => [
            ['Fai sapere a ', 'quando arriverai'],
        ],
        'pt' => [
            ['Diga a ', 'quando você vai chegar'],
        ],
        'pl' => [
            ['Poinformuj obiekt', 'o godzinie przyjazdu'],
        ],
        'es' => [
            ['Dile a', 'cuándo vas a llegar'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (isset($dSubject[0], $dSubject[1])
                    && stripos($headers['subject'], $dSubject[0]) !== false
                    && stripos($headers['subject'], $dSubject[1]) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.booking.com") or contains(@href,".booking.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Booking.com. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourBookingAt' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true, "/{$this->opt($this->t('Hi '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,!?:]+|$)/u");
        $h->general()->traveller($guestName, false);

        $xpathBooking = "//text()[{$this->starts($this->t('Your booking at'))}]";

        $hotelName_temp = $this->http->FindSingleNode($xpathBooking, null, true, "/{$this->opt($this->t('Your booking at'))}\s*(.{3,})/");

        if ($hotelName_temp && $this->http->XPath->query("//node()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $h->hotel()
                ->name($hotelName_temp)
                ->noAddress();
        }

        $confirmation = $this->http->FindSingleNode($xpathBooking . "/following::text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathBooking . "/following::text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $checkInHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]");
        $checkIn = $this->htmlToText($checkInHtml);
        $checkInRows = explode("\n", trim($checkIn));

        if (count($checkInRows) > 2 && preg_match("/({$patterns['time']})/", $checkInRows[2], $m)) {
            // 14:00 - 21:00    |    from 16:00
            $h->booked()->checkIn2($this->normalizeDate($checkInRows[1]) . ' ' . $m[1]);
        } elseif (count($checkInRows) == 2) {
            $h->booked()->checkIn2($this->normalizeDate($checkInRows[1]));
        }

        $checkOutHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]");
        $checkOut = $this->htmlToText($checkOutHtml);
        $checkOutRows = explode("\n", trim($checkOut));

        if (count($checkOutRows) > 2 && preg_match("/({$patterns['time']})\s*$/", $checkOutRows[2], $m)) {
            // 07:00 - 11:00    |    until 12:00
            $h->booked()->checkOut2($this->normalizeDate($checkOutRows[1]) . ' ' . $m[1]);
        } elseif (count($checkOutRows) == 2) {
            $h->booked()->checkOut2($this->normalizeDate($checkOutRows[1]));
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('or call them at:'))}]", null, true,
            "/{$this->opt($this->t('or call them at:'))}\s*([+(\d][-. \d)(]{5,}[\d)])(?:[.!?]+| o enviado un|$)/");
        $h->hotel()->phone($phone);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
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

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date in = '.print_r( $date,true));
        $in = [
            // woensdag, 26 mei 2021
            '#^\D*[\s,.](\d+)\s+(\w+)[.,]?\s+(\d{4})[\.\s]*$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
//        $this->logger->debug('$date = '.print_r( preg_replace($in, $out, $date),true));
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
//        $this->logger->debug('$date out = '.print_r( $str,true));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
