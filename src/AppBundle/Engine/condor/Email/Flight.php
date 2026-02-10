<?php

namespace AwardWallet\Engine\condor\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "condor/it-157916318.eml, condor/it-33892509.eml, condor/it-34058272.eml, condor/it-388080689.eml"; // +1 bcdtravel(html)[en]
    // + emails from ConfirmationPDF

    public $lang = '';

    public static $dictionary = [
        'it' => [
            "You're booked"     => 'Prenotazione effettuata',
            'Booking reference' => 'Numero di prenotazione',
            'Total price'       => 'Prezzo totale',
            'Seat'              => 'Posto',
            'None'              => 'Non prenotato',
        ],
        'de' => [
            "You're booked"     => 'Flug gebucht',
            'Booking reference' => 'Buchungsnummer',
            'Total price'       => 'Gesamtpreis',
            'Seat'              => 'Sitzplatz',
            'None'              => 'Nicht gebucht',
            'DBA'               => 'Durchgefuehrt von',
        ],
        'es' => [
            "You're booked"     => 'Ha reservado',
            'Booking reference' => 'Numero de la reserva',
            'Total price'       => 'Precio total',
            'Seat'              => 'Asiento',
            'None'              => 'No reservado',
        ],
        'nl' => [
            "You're booked"     => 'Geboekt',
            'Booking reference' => 'Boekingsnummer',
            'Total price'       => 'Totale prijs',
            'Seat'              => 'Stoel',
            // 'None'              => 'No reservado',
        ],
        'fr' => [
            "You're booked"     => 'Vous avez réservé',
            'Booking reference' => 'Numéro de réservation',
            'Total price'       => 'Prix total',
            'Seat'              => 'Siège',
            // 'None'              => 'Pas réservé',
        ],
        'en' => [
            "You're booked"     => "You're booked",
            'Booking reference' => ['Booking reference', 'Booking reference-version'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, 'condor')) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]condor\.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        if ($pax = $this->http->FindNodes("//img[contains(@src, 'ec-avatar-icon')]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]")) {
            $pax = array_filter(array_unique($pax));

            $pax = preg_replace("/^\s*(Herr|Mevrouw)\s+/", '', $pax);

            $f->general()
                ->travellers($pax);
        }

        if ($conf = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Booking reference'))}]/following-sibling::node()[string-length(normalize-space())>2][1]", null, true, '/^([A-Z\d]{5,})\b/')) {
            $f->general()
                ->confirmation($conf);
        }

        if (preg_match('/(\D+)[ ]*([\d\.]+)/', $this->http->FindSingleNode("//node()[{$this->eq($this->t('Total price'))}]/following-sibling::node()[string-length(normalize-space())>2][1]"), $m)) {
            $f->price()
                ->total($m[2])
                ->currency($this->currency($m[1]));
        }

        $xpath = "//img[contains(@src, 'ec-connector-top')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments were not found by xpath: {$xpath}");
        }

        $date = 0;
        /** @var \DOMNode $root */
        foreach ($roots as $root) {
            $s = $f->addSegment();

            if ($d = $this->http->FindSingleNode('ancestor::table[preceding-sibling::table][1]/preceding-sibling::table[1]', $root, true, '/\w+ (\d{1,2} \w+ \d{2,4})/')) {
                $date = $this->normalizeDate($d);
            } elseif ($d = $this->http->FindSingleNode('preceding::tr[not(.//tr)][normalize-space()][1]', $root, true, '/\w+ (\d{1,2} \w+ \d{2,4})/')) {
                $date = $this->normalizeDate($d);
            }

            $re = '/(\d{1,2}:\d{2})[ ]+(.+)/';

            if (preg_match($re, $root->nodeValue, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2])
                    ->noCode();
            }

            if (preg_match($re, $this->http->FindSingleNode('following-sibling::tr[string-length(normalize-space(.))>2][1]', $root), $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2])
                    ->noCode();
            }

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})[ ]*(\d+)\s*(.*)\n(.+)$/', implode("\n", $this->http->FindNodes('ancestor::*[name()="th" or name()="td"][following-sibling::*][1]/following-sibling::*[string-length(normalize-space(.))>2][last()]/descendant::text()[normalize-space()!=""]', $root)), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3]) && preg_match("/\b{$this->t('DBA')}\s+(.+)/", $m[3], $v)) {
                    $s->airline()->operator($v[1]);
                }
                $s->extra()
                    ->cabin($m[4]);
            }

            $seatNodes = $this->http->XPath->query("ancestor::table[following-sibling::table[1][.//text()[{$this->eq($this->t('Seat'))}]]][1]/following-sibling::table", $root);

            foreach ($seatNodes as $seatRoot) {
                if ($this->http->XPath->query(".//text()[{$this->eq($this->t('Seat'))}]", $seatRoot)->length > 0) {
                    $seat = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Seat'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[not({$this->contains($this->t('None'))})][1]",
                        $seatRoot, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                } else {
                    break;
                }
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'US$'=> 'USD',
            'S$' => 'SGD',
            'HK$'=> 'HKD',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'Rp '=> 'IDR',
            'zł' => 'PLN',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            '/(\d{1,2}) (\w+) (\d{2,4})/',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match('#([[:alpha:]]+)#iu', $str, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return strtotime(preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str));
            }
        }

        return strtotime($str);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases["You're booked"]) || empty($phrases['Booking reference'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases["You're booked"])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Booking reference'])}]")->length > 0
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
