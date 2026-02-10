<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "amoma/it-33708955.eml, amoma/it-33396462.eml, amoma/it-32977946.eml";

    public $lang = '';

    public static $dictionary = [
        'it' => [
            'langDetectors' => ['CONCLUDI LA TUA PRENOTAZIONE'],
            'checkIn'       => ['Check-in'],
            'checkOut'      => ['Check-out'],
            'adult'         => ['Adulto', 'adulto'],
            'child'         => ['Bambino', 'bambino'],
            'night'         => ['Notti', 'notti', 'NOTTI'],
        ],
        'es' => [
            'langDetectors' => ['FINALIZA TU RESERVA'],
            'checkIn'       => ['Entrada'],
            'checkOut'      => ['Salida'],
            'adult'         => ['Adulto', 'adulto'],
            'child'         => ['Niño', 'niño'],
            'night'         => ['Noches', 'noches', 'NOCHES'],
        ],
        'en' => [
            'langDetectors' => ['FINISH YOUR BOOKING'],
            'checkIn'       => ['Check-in'],
            'checkOut'      => ['Check-out'],
            'adult'         => ['Adult', 'adult'],
            'child'         => ['Child', 'child'],
            'night'         => ['Night', 'night', 'NIGHT'],
        ],
    ];

    private $subjects = [
        'it' => ['Fai in fretta!'],
        'es' => ['¡Date prisa!'],
        'en' => ['Act fast!'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]amomainfo\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectProvider()
            && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->assignLang($this->http->Response['body']);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);
        $email->setType('Booking' . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        $xpathFragmentCell = '(self::td or self::th)';
        $xpathFragmentOrange = "({$this->contains(['#ff8b00', '#FF8B00'], '@style')})";

        $h = $email->add()->hotel();

        $xpathFragmentDates = "descendant::tr[ *[1][ descendant::text()[{$this->eq($this->t('checkIn'))}] ] and *[2][ descendant::text()[{$this->eq($this->t('checkOut'))}] ] ]";

        $xpathFragmentHotelName = "//table[$xpathFragmentDates]/preceding-sibling::table[string-length(normalize-space())>2][1]/descendant::text()[normalize-space()][1][ ancestor::*[$xpathFragmentOrange] ]";

        $hotelName = $this->http->FindSingleNode($xpathFragmentHotelName);
        $address = $this->http->FindSingleNode($xpathFragmentHotelName . "/ancestor::*[$xpathFragmentCell][1]/descendant::text()[normalize-space()][2][not(ancestor::*[$xpathFragmentOrange])]");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
        ;

        $checkInTexts = $this->http->FindNodes($xpathFragmentDates . "/*[1]/descendant::td[ not(.//td) and descendant::text()[{$this->eq($this->t('checkIn'))}] ]/descendant::text()[normalize-space()]");
        $checkInText = implode(' ', $checkInTexts);

        if (preg_match("/{$this->opt($this->t('checkIn'))}\s*(.{6,})/", $checkInText, $m)) {
            $checkInNormal = $this->normalizeDate($m[1]);
            $h->booked()->checkIn2($checkInNormal);
        }

        $checkOutTexts = $this->http->FindNodes($xpathFragmentDates . "/*[2]/descendant::td[ not(.//td) and descendant::text()[{$this->eq($this->t('checkOut'))}] ]/descendant::text()[normalize-space()]");
        $checkOutText = implode(' ', $checkOutTexts);

        if (preg_match("/{$this->opt($this->t('checkOut'))}\s*(.{6,})/", $checkOutText, $m)) {
            $checkOutNormal = $this->normalizeDate($m[1]);
            $h->booked()->checkOut2($checkOutNormal);
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode($xpathFragmentDates . "/*[1]/descendant::td[not(.//td) and normalize-space() and not(descendant::text()[{$this->eq($this->t('checkIn'))}])]/descendant::text()[normalize-space()][1]");
        $room->setType($roomType);

        $guestsTexts = $this->http->FindNodes($xpathFragmentDates . "/*[2]/descendant::td[not(.//td) and normalize-space() and not(descendant::text()[{$this->eq($this->t('checkOut'))}]) and ({$this->contains($this->t('adult'))} or {$this->contains($this->t('child'))})]/descendant::text()[normalize-space()]");
        $guestsText = implode("\n", $guestsTexts);

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $guestsText, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/i", $guestsText, $m)) {
            $h->booked()->kids($m[1]);
        }

        $paymentTexts = $this->http->FindNodes("//*[ not(.//tr[normalize-space()]) and *[normalize-space()][2] and *[normalize-space()][position()>1][{$this->contains($this->t('night'))}] ]/descendant::text()[normalize-space()]");
        $paymentText = implode(' ', $paymentTexts);

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $paymentText, $matches)) {
            // 483 EUR 2 night(s)
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;
        }

        $h->general()->noConfirmation();
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})\s+(\d{4})\s+[^\d\W]{2,}$/u', $string, $matches)) {
            // 18 March 2019 Monday
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function detectProvider(): bool
    {
        return $this->http->XPath->query('//a[contains(@href,"//booking.amomainfo.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"@booking.amomainfo.com")]')->length > 0;
    }

    private function assignLang(string $text = ''): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['langDetectors']) || empty($phrases['checkIn'])) {
                continue;
            }

            if (empty($text)
                && $this->http->XPath->query("//node()[{$this->contains($phrases['langDetectors'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            } elseif (!empty($text)
                && preg_match("/{$this->opt($phrases['langDetectors'])}/", $text) > 0
                && preg_match("/{$this->opt($phrases['checkIn'])}/", $text) > 0
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
}
