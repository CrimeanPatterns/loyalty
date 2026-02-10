<?php

namespace AwardWallet\Engine\fnt\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightOrder extends \TAccountChecker
{
    public $mailFiles = "fnt/it-160107156.eml, fnt/it-162197744.eml, fnt/it-714181471.eml, fnt/it-715005619.eml, fnt/it-715066655.eml, fnt/it-717471445.eml";
    public $subjects = [
        'Travel document for order:',
        // sv
        'Resedokument för order:',
        // it
        "Documento di viaggio per l'ordine:",
        // ja
        '注文用の旅行文書：',
        // tr
        'Sipariş için yolculuk numarası:',
    ];

    public $lang = 'en';
    public $date;

    public $providerCode;

    public static $providers = [
        'fnt' => [
            'from' => '.flightnetwork.',
            'body' => ['.flightnetwork.'],
        ],
        'supersaver' => [
            'from' => '.supersavertravel.',
            'body' => ['.supersavertravel.'],
        ],
        'gotogate' => [
            'from' => '.gotogate.',
            'body' => ['.gotogate.'],
        ],
        'trip' => [
            'from' => '.mytrip.',
            'body' => ['.mytrip.'],
        ],
        'flybillet' => [
            'from' => '.flybillet.',
            'body' => ['.flybillet.'],
        ],
    ];

    public static $dictionary = [
        "en" => [
            'Order no:' => 'Order no:',
            // 'Issued:' => '',
            // 'Your airline check-in reference(s)' => '',
            'Your booking number' => 'Your booking number',
            // 'Passenger name' => '',
            // 'Class' => '',
            // 'e-Ticket receipt(s)' => '',
        ],
        "sv" => [
            'Order no:'                          => 'Ordernr:',
            'Issued:'                            => 'Utfärdad:',
            'Your airline check-in reference(s)' => 'Incheckningsnummer hos flygbolaget',
            'Your booking number'                => 'Ditt bokningsnummer',
            'Passenger name'                     => 'Passagerarnamn',
            'Class'                              => 'Klass',
            'e-Ticket receipt(s)'                => 'Biljettnummer',
        ],
        "it" => [
            'Order no:' => 'N. ordine:',
            'Issued:'   => 'Emesso:',
            // 'Your airline check-in reference(s)' => '',
            'Your booking number' => 'Numero di prenotazione',
            'Passenger name'      => 'Nome passeggero',
            'Class'               => 'Classe',
            // 'e-Ticket receipt(s)' => 'Biljettnummer',
        ],
        "nl" => [
            'Order no:'                          => 'Ordernr.:',
            'Issued:'                            => 'Uitgegeven:',
            'Your airline check-in reference(s)' => 'De incheckreferentie(s) van uw luchtvaartmaatschappij',
            'Your booking number'                => 'Uw boekingsnummer',
            'Passenger name'                     => 'Naam passagier',
            'Class'                              => 'Klasse',
            // 'e-Ticket receipt(s)' => 'Biljettnummer',
        ],
        "ja" => [
            'Order no:'                          => 'ご予約番号:',
            'Issued:'                            => '発行済み:',
            'Your airline check-in reference(s)' => '航空チェックイン参照',
            'Your booking number'                => '予約番号',
            'Passenger name'                     => '乗客名',
            'Class'                              => 'クラス',
            'e-Ticket receipt(s)'                => 'eチケットレシート',
        ],
        "tr" => [
            'Order no:'                          => 'Sipariş numarası:',
            'Issued:'                            => 'Verildi:',
            'Your airline check-in reference(s)' => 'Havayolu şirketi check-in referanslarınız',
            'Your booking number'                => 'Rezervasyon numaranız',
            'Passenger name'                     => 'Sınıfı',
            'Class'                              => 'Klasse',
            'e-Ticket receipt(s)'                => 'e-Bilet makbuzları',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $key => $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectProvider($from = null)
    {
        foreach (self::$providers as $code => $detect) {
            if (!empty($from) && !empty($detect['from']) && stripos($from, $detect['from']) !== false) {
                $this->providerCode = $code;

                return $code;
            }

            foreach ($detect['body'] as $dBody) {
                if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                    $this->providerCode = $code;

                    return $code;
                }
            }
        }

        return null;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (!$this->detectProvider()) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Order no:']) && !empty($dict['Your booking number'])
                && $this->http->XPath->query("//node()[{$this->eq($dict['Order no:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Your booking number'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flightnetwork\./', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $issuedDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Issued:'))}]/following::text()[normalize-space()][1]"));

        if (!empty($issuedDate)) {
            $this->date = $issuedDate;
        }

        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::table[2]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            unset($f);
            $conf = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Your booking number'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");

            foreach ($email->getItineraries() as $it) {
                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $f = $it;

                    break;
                }
            }

            if (!isset($f)) {
                $f = $email->add()->flight();

                $confs = [$conf => [$this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Your booking number'))}][1]", $root)]];
                $confsStr = explode(',', $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Your airline check-in reference(s)'))}][1]/following::text()[normalize-space()][1]", $root));

                foreach ($confsStr as $str) {
                    if (preg_match("/^\s*([A-Z\d]{5,7})\s*\(([A-Z\d]{2})\)\s*$/", $str, $m)) {
                        $confs[$m[1]][] = $m[2];
                    }
                }

                foreach ($confs as $num => $desc) {
                    $f->general()
                        ->confirmation($num, implode(', ', $desc));
                }
            }

            $f->general()
                ->date($issuedDate);

            $colTicket = count($this->http->FindNodes("(./following::text()[{$this->eq($this->t('Passenger name'))}])[1]/ancestor::tr[1]/*[{$this->eq($this->t('e-Ticket receipt(s)'))}]/preceding-sibling::*", $root));

            if ($colTicket > 0) {
                $colTicket++;
            }
            $xpath = "(./following::text()[{$this->eq($this->t('Passenger name'))}])[1]/ancestor::tr[1]/following-sibling::tr";
            $pNodes = $this->http->XPath->query($xpath, $root);

            foreach ($pNodes as $pRoot) {
                $name = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1", preg_replace("/\s+(?:Mrs|Mr|Ms|Mstr|Miss)\s*$/i", '',
                    $this->http->FindSingleNode("*[1]", $pRoot)));

                if (!in_array($name, array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($name, true);
                }

                if ($colTicket > 0) {
                    $ticket = $this->http->FindSingleNode("*[{$colTicket}]", $pRoot, null, "/^\s*(\d+[\d\-]+)\s*$/");

                    if (!empty($ticket) && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                        $f->issued()
                            ->ticket($ticket, false, $name);
                    }
                }
            }

            $s = $f->addSegment();

            // Airline
            $flightInfo = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Flight'))}]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Flight'))}\s*([A-Z\d]{2})\s*(\d{1,4})\b.+{$this->opt($this->t('Operated by:'))}\s*(.+)$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($m[3]);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Departure'))}]", $root, true, "/{$this->opt($this->t('Departure'))}\s*([A-Z]{3})\b/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Departure'))}]/descendant::table[1]", $root)));

            $depTerminal = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Departure'))}]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(.+)/u");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Arrival'))}]", $root, true, "/{$this->opt($this->t('Arrival'))}\s*([A-Z]{3})\b/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Arrival'))}]/descendant::table[1]", $root)));

            $arrTerminal = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Arrival'))}]", $root, true, "/{$this->opt($this->t('Terminal:'))}\s*(.+)/u");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $cabin = array_unique(array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('Passenger name'))}][1]/ancestor::tr[1]/following-sibling::tr/td[2]", $root, "/^([A-Z])$/")));

            if (count($cabin) == 1) {
                $s->extra()
                    ->bookingCode($cabin[0]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Order no:']) && !empty($dict['Your booking number'])
                && $this->http->XPath->query("//node()[{$this->eq($dict['Order no:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Your booking number'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order no:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})\s*$/");

        if (!empty($confNumber)) {
            $email->ota()
                ->confirmation($confNumber);
        }

        $this->ParseFlight($email);

        if (empty($this->providerCode)) {
            $this->detectProvider($parser->getCleanFrom());
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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
        $year = date("Y", $this->date);
        $in = [
            // 2024年08月13日
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*$/ui",
            // 2024年09月14日(Sat) 08:40
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\([[:alpha:]]+\)\s*(\d{1,2}:\d{2})\s*$/ui",
            //Fri 30 Dec 01:30
            "/^\s*([[:alpha:]]+)\s+(\d+)\s*([[:alpha:]]+)\s*(\d{1,2}:\d{2})\s*$/u",
        ];
        $out = [
            "$1-$2-$3",
            "$1-$2-$3, $4",
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if ($weeknum === null) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
            }
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
