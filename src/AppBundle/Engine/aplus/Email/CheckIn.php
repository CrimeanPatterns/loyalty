<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "aplus/it-111305240.eml, aplus/it-29223049.eml, aplus/it-59624702.eml";
    private $subjects = [
        'en' => ['Save yourself some time and check-in online now', "Your stay"],
        'pt' => ['Check-in online - Hospedagem de', 'Sua hospedagem no hotel'],
        'de' => ['Ihr Aufenthalt im'],
        'fr' => ['Votre séjour à l\'hôtel'],
        'nl' => ['Uw overnachting in het'],
        'pl' => ['Twój pobyt w hotelu'],
        'ja' => ['ホテルでの滞在'],
        'es' => ['Tu estancia en el hotel'],
    ];
    private $langDetectors = [
        'en' => ['Your booking:', 'Your reservation number:'],
        'pt' => ['Sua reserva:', "Seu número de reserva:", 'O seu número de reserva:'],
        'de' => ['Buchungsnummer:'],
        'fr' => ['Votre numéro de réservation :'],
        'nl' => ['Uw reserveringsnummer:'],
        'pl' => ['Twój numer rezerwacji:'],
        'ja' => ['お客さまのご予約番号：'],
        'es' => ['Tu número de reserva:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            "Your booking:"       => ["Your booking:", "Your reservation number:"],
            "from"                => ["from", "From"],
            " to "                => " to ",
            "Hello"               => ["Hello", "Dear"],
            'textBeforeHotelName' => 'At the',
            //            'textAfterHotelName' => '',
        ],
        'pt' => [
            "Your booking:"       => ["Sua reserva:", "Seu número de reserva:", 'O seu número de reserva:'],
            "from"                => ["de", "De", "Do"],
            " to "                => [" a ", " à ", " às "],
            "Hello"               => ["Olá", "Caro(a)", "Caro", 'Cara'],
            'textBeforeHotelName' => 'No',
            //            'textBeforeHotelName' => '',
            //            'textAfterHotelName' => '',
        ],
        'de' => [
            "Your booking:" => ["Buchungsnummer:"],
            "from"          => ["Vom"],
            " to "          => [" bis "],
            "Hello"         => ["Guten Tag"],
            //            'textBeforeHotelName' => '',
            //            'textAfterHotelName' => '',
        ],
        'fr' => [
            "Your booking:"       => ['Votre numéro de réservation :'],
            "from"                => ["De"],
            " to "                => [" à "],
            "Hello"               => ["Cher", 'Chère'],
            'textBeforeHotelName' => 'Au',
            //            'textAfterHotelName' => '',
        ],
        'nl' => [
            "Your booking:"       => ['Uw reserveringsnummer:'],
            "from"                => ["Van"],
            " to "                => [" tot "],
            "Hello"               => ["Beste Heer", 'Beste Mevr.', 'Beste'],
            'textBeforeHotelName' => 'In hotel',
            //            'textAfterHotelName' => '',
        ],
        'pl' => [
            "Your booking:" => ['Twój numer rezerwacji:'],
            "from"          => ["Od"],
            " to "          => [" do "],
            "Hello"         => ["Witaj"],
            //            'textBeforeHotelName' => '',
            //            'textAfterHotelName' => '',
        ],
        'ja' => [
            "Your booking:" => ['お客さまのご予約番号：'],
            // 月曜日2021年9月6日から火曜日2021年9月7日まで
            // need more examples to translate
            //            "from"          => [""],
            //            " to "          => [""],
            "Hello"         => ["さま、"],
            //            'textBeforeHotelName' => '',
            //            'textAfterHotelName' => '',
        ],
        'es' => [
            "Your booking:"       => ["Tu número de reserva:"],
            "from"                => ["Desde el"],
            " to "                => " al ",
            "Hello"               => ["Estimada"],
            'textBeforeHotelName' => 'En el',
            //            'textAfterHotelName' => '',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]accor-mail\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//t.reservation.accor-mail.com")]')->length === 0;
        $condition2 = self::detectEmailFromProvider($parser->getHeader('from')) !== true;
        $condition3 = $this->http->XPath->query('//a[contains(@href,"@reservation.accor-mail.com")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('CheckIn' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $xpathFragment0 = "{$this->starts($this->t('Your booking:'))}";

        // confirmation number
        $confNo = $this->http->FindSingleNode("//text()[{$xpathFragment0}]");

        if (preg_match("/^({$this->opt($this->t('Your booking:'))})\s*([A-Z\d]{7,})$/", $confNo, $m)) {
            $h->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        $xpathFragment1 = "{$this->starts($this->t('from'))} and {$this->contains($this->t(' to '))}";

        $hotelText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Your booking:'))}]/following::text()[normalize-space()][position() < 5][{$this->starts($this->t('from'))}]/ancestor::*[1][{$this->contains($this->t(' to '))}]//text()"));

        // hotelName
        $hotelName = $this->http->FindSingleNode("//text()[ ./preceding::text()[normalize-space(.)][1][{$xpathFragment0}] and ./following::text()[normalize-space(.)][1][{$xpathFragment1}] ]", null, true, '/^[^:]{3,}$/');

        if (empty($hotelName) && !empty($hotelText)
            && preg_match("/{$this->opt($this->t('Your booking:'))}\s*\w+\s*\n(.+)\n{$this->opt($this->t('from'))}\s+(?:(?:.+\n)?.*(?:\b\d{4}\b|\.\d{2}\b).*)" . preg_replace('/ /', '\s+', $this->opt($this->t(' to '))) . "/u", $hotelText, $m)
        ) {
            $hotelName = $m[1];
            $hotelName = preg_replace(['/^\s*' . $this->opt($this->t('textBeforeHotelName')) . ' /u', '/ ' . $this->opt($this->t('textAfterHotelName')) . '\s*$/u'], '', $hotelName);
        }
        $h->hotel()->name($hotelName);
        // checkInDate
        // checkOutDate
        $dates = $this->http->FindSingleNode("//text()[{$xpathFragment1}]");

        if (empty($dates)) {
            $dates = implode(" ", $this->http->FindNodes("//text()[{$this->starts($this->t('from'))}]/ancestor::*[1][{$this->contains($this->t(' to '))}]//text()"));
        }

        if (preg_match("/.*\b{$this->opt($this->t('from'))}\s*(?<date1>.{6,})\s*{$this->opt($this->t(' to '))}\s*(?<date2>.{6,})/", $dates, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['date1']))
                ->checkOut($this->normalizeDate($m['date2']))
            ;
        } elseif (!empty($hotelText)
            && preg_match("/\n{$this->opt($this->t('from'))}\s+((?:.+\n)?.*(?:\b\d{4}\b|\.\d{2}\b).*)" . preg_replace('/ /', '\s+', $this->opt($this->t(' to '))) . "((?:.+\n)?.*(?:\b\d{4}\b|\.\d{2}\b).*)\s*$/u", $hotelText, $m)
        ) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]))
            ;
        }

        // travellers
        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,.!]|$)/u");

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/ancestor::*[1]", null, true, "/^\s*{$this->opt($this->t('Hello'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,.!]|$)/u");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/following::text()[1]", null, true, "/([\w\s]+)/");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking:'))}]/preceding::*[{$this->starts($this->t('Hello'))}][1]", null, true, "/^\s*{$this->opt($this->t('Hello'))}\s+([\w\s]+),\s*$/");
        }
        $h->general()->traveller($guestName);

        // address
        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $h->hotel()->noAddress();
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate($date)
    {
//        $this->logger->debug('date = '.$date);
        $in = [
            // Sunday 18/11/2018
            '/^\s*[[:alpha:]\-]{2,}[,\s]+(\d{1,2})\/(\d{1,2})\/(\d{4})$/u',
            // Jan, 13 2020
            "/^([A-z]{3}), (\d{1,2}) (\d{4})$/",
            // Donnerstag, 24.12.20
            '/^\s*[[:alpha:]\-]{2,}[,\s]+(\d{1,2})\.(\d{1,2})\.(\d{2})$/u',
            // zondag  27-12-2020
            '/^\s*[[:alpha:]\-]{2,}[,\s]+(\d{1,2})\-(\d{1,2})\-(\d{4})$/u',
            // Friday Sep, 03 2021
            '/^\s*[[:alpha:]\-]{2,}[,\s]+([[:alpha:]]{2,})[,\s]+(\d{1,2})\s+(\d{4})\s*$/u',
        ];
        $out = [
            '$1.$2.$3',
            '$1 $2 $3',
            '$1.$2.20$3',
            '$1.$2.$3',
            '$2 $1 $3',
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date = '.$date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
