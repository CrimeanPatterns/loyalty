<?php

namespace AwardWallet\Engine\check\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingRequest extends \TAccountChecker
{
    public $mailFiles = "check/it-30376597.eml, check/it-30376600.eml, check/it-703983167.eml";

    public static $dictionary = [
        "de" => [
            "Reiseteilnehmer" => ["Reiseteilnehmer", "Unterbringung"],
        ],
    ];

    private $detectFrom = "check24.de";

    private $detectSubject = [
        'de' => 'Buchungsauftrag für Ihre Side', 'Ihre Reiseunterlagen des Reiseveranstalters für Ihre', 'Reisebestätigung und Rechnung für Ihre',
    ];
    private $detectCompany = "CHECK24";
    private $detectBody = [
        "de" => "Ihre Reisedaten",
    ];

    private $lang = "de";

    public function parseEmail(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $totalPrice = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Reisepreis'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 2.193,00 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains(['Buchungsnummer', 'Ihre Buchungsnummer lautet'])}][1]");

        if (preg_match("/({$this->opt(['Buchungsnummer', 'Ihre Buchungsnummer lautet'])})\s*([A-Z\d]{5,35})$/", $otaConfirmation, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        } elseif ($otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains(['Buchungsnummer', 'Ihre Buchungsnummer lautet'])}][1]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,35}$/")) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->contains(['Buchungsnummer', 'Ihre Buchungsnummer lautet'])}][1]", null, true, "/{$this->opt(['Buchungsnummer', 'Ihre Buchungsnummer lautet'])}/");
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        } elseif (empty($this->http->FindSingleNode("//text()[contains(normalize-space(),'Buchungsnummer')]"))) {
            $email->obtainTravelAgency();
        }

        $travellers = array_values(array_filter($this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Reiseteilnehmer"))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", null, "/^(?:Frau|Herr|Kind|Baby)?\s*({$patterns['travellerName']})\s*,\s*\d/u")));

        /*
         * FLIGHTS
         */
        if (!empty($this->http->FindSingleNode("(//text()[normalize-space()='Hinflug' or normalize-space()='Rückflug'])[1]"))) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation()
                ->travellers($travellers, true);

            // Segments
            $xpath = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Hinflug' or normalize-space()='Rückflug'] ]/*[normalize-space()][2]//tr/*[not(.//tr[normalize-space()])]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $text = implode(" ", $this->http->FindNodes("descendant::text()[normalize-space()]", $root));

                // Di, 15.04.2025 von Hamburg nach Lanzarote, 08:05 Uhr bis 11:55 Uhr mit Condor (DE6438)
                $pattern = "/(?<date>.+?)\s+von\s+(?<dep>.{2,}?)\s+nach\s+(?<arr>.{2,}?)\s*,\s*(?<dtime>{$patterns['time']})(?:\s*Uhr)?\s+bis\s+(?<atime>{$patterns['time']})(?:\s*Uhr)?\s+mit\s+(?<al>.+)/u";

                if (preg_match($pattern, $text, $m)) {
                    // Airline
                    if (preg_match("/(?:^[(\s]*|.+\(\s*)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<number>\d+)[\s)]*$/", $m['al'], $matches)) {
                        // Condor (DE6428)    |    (DE6428)    |    DE6428
                        $icao2iata = [
                            'SEK' => '4R',
                        ];
                        $s->airline()->name(array_key_exists($matches['name'], $icao2iata) ? $icao2iata[$matches['name']] : $matches['name'])->number($matches['number']);
                    } else {
                        $s->airline()->name($m['al'])->noNumber();
                    }

                    // Departure
                    $s->departure()
                        ->noCode()
                        ->name($m['dep'])
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['dtime']));

                    // Arrival
                    $s->arrival()
                        ->noCode()
                        ->name($m['arr'])
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['atime']));
                } elseif (preg_match("/^\s*([^()→]+?)\s*\(\s*([A-Z]{3})\s*\)\s+→\s+([^()→]+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $text, $m)) {
                    // Karlsruhe (FKB) → Antalya (AYT)

                    // Airline
                    $s->airline()
                        ->noName()
                        ->noNumber();

                    // Departure
                    $s->departure()
                        ->code($m[2])
                        ->name($m[1])
                        ->noDate()
                    ;

                    // Arrival
                    $s->arrival()
                        ->code($m[4])
                        ->name($m[3])
                        ->noDate()
                    ;

                    if (!empty($this->http->FindSingleNode("./ancestor::table/preceding-sibling::table[1][normalize-space()='Hinflug']", $root))) {
                        $s->departure()->day($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Hinreisedatum']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]")));
                        $s->arrival()->day($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Hinreisedatum']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]")));
                    } elseif (!empty($this->http->FindSingleNode("./ancestor::table/preceding-sibling::table[1][normalize-space()='Rückflug']", $root))) {
                        $s->departure()->day($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Rückreisedatum']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]")));
                        $s->arrival()->day($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Rückreisedatum']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]")));
                    }
                }

                // no example for one way flight with stopovers
                if ($this->http->XPath->query("ancestor::*[ preceding-sibling::*[normalize-space()][1][normalize-space()='Hinflug'] ]", $root)->length > 0
                    && $this->http->XPath->query("preceding-sibling::*[normalize-space()]", $root)->length === 0
                ) {
                    if (!empty($s->getArrDate())) {
                        $dateHotelBegin = strtotime("00:00", $s->getArrDate());
                    } elseif (!empty($s->getArrDay())) {
                        $dateHotelBegin = $s->getArrDay();
                    }
                }

                if ($this->http->XPath->query("ancestor::*[ preceding-sibling::*[normalize-space()][1][normalize-space()='Rückflug'] ]", $root)->length > 0
                    && $this->http->XPath->query("following-sibling::*[normalize-space()]", $root)->length === 0
                ) {
                    if (!empty($s->getDepDate())) {
                        $dateHotelEnd = strtotime("00:00", $s->getDepDate());
                    } elseif (!empty($s->getDepDay())) {
                        $dateHotelEnd = $s->getDepDay();
                    }
                }
            }
        }

        /*
         * HOTEL
         */
        if ($this->http->XPath->query("//img[contains(@alt,'Hotel Image') or contains(@title,'Hotel Image')]")->length > 0) {
            $nodes = $this->http->XPath->query("//*[ count(*)=2 and *[1]/descendant::img[contains(@alt,'Hotel Image') or contains(@title,'Hotel Image')] ]/*[2][normalize-space()]");

            foreach ($nodes as $root) {
                $h = $email->add()->hotel();

                // General
                $h->general()
                    ->noConfirmation()
                    ->travellers($travellers, true);

                // Hotel
                $h->hotel()
                    ->name($this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][1]", $root))
                    ->address($this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][2]", $root));

                // Booked
                if (!empty($dates = $this->http->FindSingleNode("./following::tr[normalize-space()][position()<3]//text()[normalize-space(.)='Reisezeitraum']/ancestor::table[1]/following-sibling::table[1]", $root))) {
                    if (preg_match("#(.+) bis (.+)#", $dates, $m)) {
                        $h->booked()
                            ->checkIn($this->normalizeDate($m[1]))
                            ->checkOut($this->normalizeDate($m[2]))
                        ;
                    }
                } elseif (!empty($dateHotelBegin) && !empty($dateHotelEnd)) {
                    $h->booked()
                        ->checkIn($dateHotelBegin)
                        ->checkOut($dateHotelEnd)
                    ;
                } else {
                    $h->booked()
                        ->checkIn($this->http->FindSingleNode("./following::tr[normalize-space()][position()<3]//text()[normalize-space(.)='Reisezeitraum']/ancestor::table[1]/following-sibling::table[1]", $root))
                        ->checkOut($this->http->FindSingleNode("./following::tr[normalize-space()][position()<3]//text()[normalize-space(.)='Reisezeitraum']/ancestor::table[1]/following-sibling::table[1]", $root))
                    ;
                }

                $rooms = $this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][3]", $root);

                if (preg_match("#^\s*(?<num>\d+)x\s+(?<type>.+?)\s*(?:,\s*(?<desc>.+))?$#", $rooms, $m)) {
                    $h->booked()->rooms($m['num']);

                    for ($i = 1; $i <= $m['num']; $i++) {
                        $h->addRoom()
                            ->setType($m['type'])
                            ->setDescription($m['desc'] ?? null, true, true)
                        ;
                    }
                }

                if (empty($rooms)) {
                    $h->addRoom()
                        ->setType($this->http->FindSingleNode("//text()[normalize-space()='Zimmer']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]"));
                }

                $guest = $this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][4]", $root, true, "/\b(\d{1,3})\s*Person/i")
                    ?? $this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][3]", $root, true, "/\b(\d{1,3})\s*Person/i")
                    ?? $this->http->FindSingleNode("//text()[normalize-space()='Leistung']/ancestor::table[1]/following-sibling::table[1]//td[not(.//td)]", null, true, "/\b(\d{1,3})\s*Person/i")
                ;
                $h->booked()->guests($guest, false, true);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
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
        $in = [
            "#^\s*[^\s\d]+[,\s]+(\d{1,2}\.\d{1,2}\.\d{4})\s+(\d+:\d+)\s*$#", //Sa, 29.09.2018 11:30
            "#^\s*[^\s\d]+[,\s]+(\d{1,2}\.\d{1,2}\.\d{4})\s*$#", //Sa, 29.09.2018
        ];
        $out = [
            "$1 $2",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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
