<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: easyjet/YourBookingFlight(object)

class ImportantChanges extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-128028995.eml, easyjet/it-128488807.eml, easyjet/it-63347465.eml, easyjet/it-64739539.eml, easyjet/it-94171829.eml, easyjet/it-195678289.eml, easyjet/it-222437029-es.eml, easyjet/it-222849919-pt.eml";
    public $reFrom = '@info.easyjet.com';

    public $reSubject = [
        // de
        ', es ist bald an der Zeit nach',
        // fr
        ': l’heure de votre vol easyJet',
        // es
        ', se acerca el momento de volar a',
        // pt
        ', está quase na hora de viajar para',
        //nl
        ', je kunt nu online inchecken voor je',
        // en
        'Important changes to your upcoming easyJet flight',
        ': the time of your easyJet flight',
    ];

    public $date;

    public $lang = '';

    public static $dictionary = [
        "fr" => [
            'BOOKING REFERENCE:'              => 'NUMÉRO DE RÉSERVATION :',
            'HI '                             => ['BONJOUR ', 'Bonjour ', 'Cher/chère '],
            'segHeader'                       => ['Votre nouvel itinéraire de vol', 'VOTRE VOL', 'Nouvel itinéraire', 'Votre nouveau vol'],
            'Depart'                          => 'Départ',
            'Arrive'                          => 'Arrivée',
            'Seats'                           => 'Sièges',
            'Your booking'                    => 'Votre réservation',
        ],
        "it" => [
            'BOOKING REFERENCE:'              => 'NUMERO DI RIFERIMENTO PRENOTAZIONE:',
            'HI '                             => ['Ciao ', 'CIAO '],
            'segHeader'                       => ['IL TUO VOLO', 'Nuovo itinerario'],
            'Depart'                          => 'Partenza',
            'Arrive'                          => 'Arrivo',
            'Seats'                           => 'Posti',
            'Your booking'                    => 'La tua prenotazione',
        ],
        "de" => [
            'BOOKING REFERENCE:'              => 'BUCHUNGSNUMMER:',
            'HI '                             => ['Hallo ', 'HALLO '],
            'segHeader'                       => ['IHR FLUG', 'Neuer Reiseplan'],
            'Depart'                          => 'Abflug',
            'Arrive'                          => 'Ankunft',
            'Seats'                           => 'Sitzplätze',
            'Your booking'                    => 'Ihre Buchung',
        ],
        "es" => [
            // 'BOOKING REFERENCE:' => '',
            'HI '                             => ['Hola ', 'Hola ', 'HOLA ', 'HOLA '],
            'segHeader'                       => ['Tu vuelo', 'Tu Vuelo', 'TU VUELO'],
            'Depart'                          => 'Salida',
            'Arrive'                          => 'Llegada',
            // 'Seats' => '',
            'Your booking'                    => 'Tu reserva',
        ],
        "pt" => [
            // 'BOOKING REFERENCE:' => '',
            'HI '                             => ['Olá ', 'Olá ', 'OLÁ ', 'OLÁ '],
            'segHeader'                       => ['O SEU VOO', 'O Seu Voo', 'O SEU VOO', 'Informações sobre o voo'],
            // 'Depart' => '',
            // 'Arrive' => '',
            // 'Seats' => '',
            'Your booking'                    => 'A sua reserva',
        ],
        "en" => [ // always last!
            'BOOKING REFERENCE:'         => 'BOOKING REFERENCE:',
            'HI '                        => ['HI ', 'Hi ', 'Dear '],
            'segHeader'                  => ['New itinerary', 'Your new flight itinerary', 'YOUR FLIGHT', 'Your new flight'],
            'Depart'                     => 'Depart',
            'Arrive'                     => 'Arrive',
            // 'Seats' => '',
            // 'Your booking' => '',
        ],
        "nl" => [
            // 'BOOKING REFERENCE:' => '',
            //'HI '                             => [''],
            'segHeader'                       => ['HET IS TIJD OM IN TE CHECKEN'],
            // 'Depart' => '',
            // 'Arrive' => '',
            // 'Seats' => '',
            'Your booking'                    => 'Je boeking',
        ],
    ];

    private $xpath = [
        'time' => 'translate(normalize-space(),"0123456789：.Hh","dddddddddd::::")="dd:dd"',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return stripos($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.easyjet.com')]")->length === 0) {
            return false;
        }

        $this->assignLang();

        return $this->findAllSegments() !== null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $allSegments = $this->findAllSegments();

        $this->date = strtotime($parser->getHeader('date'));

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING REFERENCE:'))}]", null, true, "/\:\s*([\dA-Z]{5,})\s*$/u");

        if (empty($confirmation) && $this->lang == 'en') {
            $confirmation = $this->re("/Your booking ([A-Z\d]{5,7}): /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang == 'fr') {
            $confirmation = $this->re("/Votre réservation ([A-Z\d]{5,7}): /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang == 'it') {
            $confirmation = $this->re("/(?:La tua prenotazione|Prenotazione) ([A-Z\d]{5,7}): /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang == 'de') {
            $confirmation = $this->re("/Ihre Buchung\s+([A-Z\d]{5,7}): /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang === 'es') {
            $confirmation = $this->re("/Tu reserva\s+([A-Z\d]{5,7})[ ]*: /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang === 'pt') {
            $confirmation = $this->re("/A (?:s|t)ua reserva\s+([A-Z\d]{5,7})[ ]*: /", $parser->getSubject());
        }

        if (empty($confirmation) && $this->lang === 'nl') {
            $confirmation = $this->re("/Je boeking\s+([A-Z\d]{5,7})[ ]*: /", $parser->getSubject());
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('HI '))}]", null, true,
            "/{$this->opt($this->t('HI '))}\s*([[:alpha:] \-]+)\,\s*$/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq(array_map('trim', $this->t('HI ')))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][starts-with(normalize-space(), ',')]]", null, true, "/^\s*([[:alpha:] \-]+)\s*$/u");
        }

        if (empty($traveller) && $this->lang == 'de') {
            $traveller = $this->re("/Ihre Buchung\s+[A-Z\d]{5,7}: ([[:alpha:]\-]+( [[:alpha:]\-]+)?), es ist bald an der Zeit /", $parser->getSubject());
        }

        if (empty($traveller) && $this->lang == 'fr') {
            $traveller = $this->re("/Votre réservation\s+[A-Z\d]{5,7}: ([[:alpha:]\-]+( [[:alpha:]\-]+)?), le départ pour /u", $parser->getSubject());
        }

        if (empty($traveller) && $this->lang === 'pt') {
            $traveller = $this->re("/A sua reserva\s+[A-Z\d]{5,7}\s*:\s*([-[:alpha:]]+( [-[:alpha:]]+)?)\s*, está quase na hora de viajar para /u", $parser->getSubject());
        }

        if ($traveller !== null) {
            $f->general()->traveller($traveller, false);
        }

        $f->general()->confirmation($confirmation);

        // Segments
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('segHeader'))}]/following::tr[ *[1][{$this->eq($this->t('Depart'))}] and *[3][{$this->eq($this->t('Arrive'))}] ]");

        if ($segments->length === 0) {
            // it-222849919-pt.eml
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('segHeader'))}]/following::tr[ *[1][{$this->xpath['time']}] and *[3][{$this->xpath['time']}] ]/preceding-sibling::tr[normalize-space()][position()<5][last()]");
        }

        if ($segments->length === 0 && $allSegments !== null && $allSegments->length === 1) {
            // it-195678289.eml
            $segments = $allSegments;
        }

        if ($segments->length === 0) {
            $this->logger->debug('Flight segments not found!');
        }

        $root = $segments->item(0);

        $s = $f->addSegment();

        // Airline
        $airline = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][1]", $root, true, "/.*\d.*/");

        if (!empty($airline)) {
            $s->airline()
                ->name($this->re("/^\s*((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[A-Z]?) ?\d{1,5}\s*$/", $airline))
                ->number($this->re("/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[A-Z]? ?(\d{1,5})\s*$/", $airline));
        } else {
            $s->airline()
                ->noName()
                ->noNumber();
        }

        $patterns['term-1'] = "/^[(\s]*T(?:erm)?[ ]*(?<term>\w+?)(?:\s*\(|[\s)]*$)/";
        $patterns['term-2'] = "/^[(\s]*Terminal[ ]*(?<term>[^)(]+?)(?:\s*\(|[\s)]*$)/";
        $patterns['term-3'] = "/^\s*(?<name>\S.*?)\s*\(\s*(?:Terminal|Term) *(?<term>.+?)\s*\)\s*$/";

        // Departure
        $name = $this->http->FindSingleNode("following-sibling::tr[1]/td[1]", $root);
        $name2 = $this->http->FindSingleNode("following-sibling::tr[2]/td[1]", $root);
        $terminal = null;

        if (preg_match($patterns['term-1'], $name2, $m)
            || preg_match($patterns['term-2'], $name2, $m)
            || preg_match($patterns['term-3'], $name2, $m)
        ) {
            $terminal = $m['term'];

            if (!empty($m['name'])) {
                $name .= ', ' . $m['name'];
            }
        } elseif (!empty($name2)) {
            $name .= ', ' . $name2;
        }
        $s->departure()
            ->noCode()
            ->name($name)
            ->terminal($terminal, false, true)
        ;

        // qua 16 nov de
        $patterns['date'] = "[-[:alpha:]]+[\s.]+\d{1,2}\s+[[:alpha:]]+[\s.]*(?:\s+de)?";

        // 10:25 AM
        $patterns['time'] = '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';

        $depDate = $this->http->FindSingleNode("following-sibling::tr[3]/td[1]", $root, true, "/^{$patterns['date']}$/iu");
        $depTime = $this->http->FindSingleNode("following-sibling::tr[4]/td[1]", $root, true, "/^{$patterns['time']}$/u");

        $s->departure()
            ->date($this->normalizeDate($depDate . ', ' . $depTime));

        // Arrival
        $name = $this->http->FindSingleNode("following-sibling::tr[1]/td[3]", $root);
        $name2 = $this->http->FindSingleNode("following-sibling::tr[2]/td[3]", $root);
        $terminal = null;

        if (preg_match($patterns['term-1'], $name2, $m)
            || preg_match($patterns['term-2'], $name2, $m)
            || preg_match($patterns['term-3'], $name2, $m)
        ) {
            $terminal = $m['term'];

            if (!empty($m['name'])) {
                $name .= ', ' . $m['name'];
            }
        } elseif (!empty($name2)) {
            $name .= ', ' . $name2;
        }
        $s->arrival()
            ->noCode()
            ->terminal($terminal, false, true);

        if (!empty($name)) {
            $s->arrival()
                ->name($name);
        }

        $arrDate = $this->http->FindSingleNode("following-sibling::tr[3]/td[3]", $root, true, "/^{$patterns['date']}$/iu");
        $arrTime = $this->http->FindSingleNode("following-sibling::tr[4]/td[3]", $root, true, "/^{$patterns['time']}$/u");

        $s->arrival()
            ->date($this->normalizeDate($arrDate . ', ' . $arrTime));

        // Extra
        $duration = $this->http->FindSingleNode(".//img[contains(@src, 'time')]/following::text()[normalize-space()][1]", $root);

        if (!empty($duration)) {
            $s->extra()
                ->duration($duration);
        }

        $seats = $this->http->FindSingleNode("following::text()[" . $this->contains($this->t('Seats')) . "]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Seats'))}\s+(\d{1,3}[A-Z].*)$/");

        if (empty($seats)) {
            $seats = $this->http->FindSingleNode("following::text()[" . $this->eq($this->t('Your booking')) . "]/following::text()[normalize-space()][1]",
                $root, true, "/^\s*(\d{1,3}[A-Z]\b[\/A-Z\d\s]*)\s*$/");
        }

        if (!empty($seats)) {
            $s->extra()
                ->seats(explode(" / ", $seats));
        }

        $email->setType('ImportantChanges' . ucfirst($this->lang));

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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "/^\s*([-[:alpha:]]+)[.\s]+(\d{1,2})\s+([[:alpha:]]+)[.\s]*(?:\s+de)?,\s+([\d:]{3,})\s*$/iu", //MON 07 SEP, 15:50
        ];
        $out = [
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function findAllSegments(): ?\DOMNodeList
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return null;
        }

        $nodes = null;

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Depart']) || empty($phrases['Arrive'])) {
                continue;
            }
            $nodes = $this->http->XPath->query("//tr[ *[1][{$this->eq($phrases['Depart'])}] and *[3][{$this->eq($phrases['Arrive'])}] ]");

            if ($nodes->length > 0) {
                if (empty($this->lang)) {
                    $this->lang = $lang;
                }

                return $nodes;
            }
        }

        if ($nodes === null || $nodes->length === 0) {
            $nodes = $this->http->XPath->query("//tr[ *[1][{$this->xpath['time']}] and *[3][{$this->xpath['time']}] ]/preceding-sibling::tr[normalize-space()][position()<5][last()]");

            if ($nodes->length > 0) {
                return $nodes;
            }
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['segHeader']) && ($this->http->XPath->query("//tr[{$this->eq($phrases['segHeader'])}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($phrases['segHeader'])}]")->length > 0)
                || !empty($phrases['HI ']) && $this->http->XPath->query("//text()[{$this->starts($phrases['HI '])}] | //text()[{$this->eq(array_map('trim', $phrases['HI ']))}]/following::text()[normalize-space()][2][starts-with(normalize-space(),',')]")->length > 0
            ) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
