<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It4248981 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "easyjet/it-12234513.eml, easyjet/it-4248981.eml, easyjet/it-4936504.eml, easyjet/it-4946963.eml";

    public $reFrom = '@email.easyjet.com';
    public $reSubject = [
        'en' => 'The departure time of your easyJet flight',
        'de' => 'Die Abflugzeit Ihres easyJet Flugs',
    ];
    public $reBody = 'easyJet';
    public $reBody2 = [
        'en' => ['YOUR OLD FLIGHT DETAILS', 'YOUR FLIGHT DETAILS'],
        'de' => ['IHRE ALTEN FLUGDATEN', 'IHRE FLUGDATEN'],
        'es' => ['DETALLES DE TU VUELO'],
        'it' => ['DETTAGLI DEL TUO NUOVO VOLO', 'NON VEDIAMO L\'ORA DI DARTI PRESTO IL BENVENUTO A BORDO'],
    ];

    public static $dictionary = [
        'en' => [],
        'de' => [
            'Your booking'            => 'Ihre Buchung',
            'BOOKING NUMBER:'         => 'BUCHUNGSNUMMER:',
            'YOUR NEW FLIGHT DETAILS' => 'IHRE NEUEN FLUGDATEN',
            'FLIGHT DETAILS'          => 'IHRE FLUGDATEN',
            'to'                      => 'nach',
            'Passenger('              => 'Passagier(',
        ],
        'es' => [
            'Your booking'            => 'Tu reserva',
            'YOUR NEW FLIGHT DETAILS' => 'DETALLES DE TU VUELO',
            'to'                      => 'hacia',
            'Passenger('              => 'Pasajero(',
        ],
        'it' => [
            'Your booking'            => 'La tua prenotazione',
            'YOUR NEW FLIGHT DETAILS' => 'DETTAGLI DEL TUO NUOVO VOLO',
            'to'                      => 'a',
            'Passenger('              => 'Passeggeri',
            'YOUR FLIGHT DETAILS'     => 'I DETTAGLI DEL TUO VOLO',
        ],
    ];

    public $lang = 'en';

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // RecordLocator
        $confNumber = $this->nextText($this->t('BOOKING NUMBER:'));

        if (!$confNumber) {
            if (preg_match('/' . $this->t('Your booking') . '\s+([A-Z\d]{6,7})/', $this->subject, $matches)) {
                $confNumber = $matches[1];
            }
        }

        $f->general()
            ->confirmation($confNumber);

        // Passengers
        $travellers = $this->http->FindNodes('//table[not(.//table) and starts-with(normalize-space(.),"' . $this->t('Passenger(') . '")]/following-sibling::table[1]//table[normalize-space(.)][(starts-with(normalize-space(.),"MR ") or starts-with(normalize-space(.),"MRS ") or not(contains(., "MANAGE BOOKING")) and not(contains(., "GESTIONAR RESERVES"))) and not(.//table)]');

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_filter($travellers));
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t('YOUR NEW FLIGHT DETAILS') . "' or normalize-space(.)='" . $this->t('FLIGHT DETAILS') . "' or normalize-space(.)='{$this->t('YOUR FLIGHT DETAILS')}']/following::table[1]//img[contains(@src,'airplane.png')]/ancestor::tr[2]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[normalize-space(.)='" . $this->t('YOUR NEW FLIGHT DETAILS') . "' or normalize-space(.)='" . $this->t('FLIGHT DETAILS') . "' or normalize-space(.)='{$this->t('YOUR FLIGHT DETAILS')}']/following::table[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->info("segments root not found: {$xpath}");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // FlightNumber
            $flightNumber = $this->http->FindSingleNode('./tr[4]', $root, true, '/^\w{3}(\d+)$/');

            if (empty($flightNumber)) {
                $flightNumber = $this->http->FindSingleNode("./descendant::tr[starts-with(normalize-space(), 'Departs')][1]/preceding::tr[normalize-space()][1]", $root, true, '/^\w{3}(\d+)$/');
            }
            $s->airline()
                ->number($flightNumber);

            // AirlineName
            $airlineName = $this->http->FindSingleNode('./tr[4]', $root, true, '/^(\w{3})\d+$/');

            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode("./descendant::tr[starts-with(normalize-space(), 'Departs')][1]/preceding::tr[normalize-space()][1]", $root, true, '/^(\w{3})\d+$/');
            }
            $s->airline()
                ->name($airlineName);

            // DepCode
            // DepName
            // DepDate
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode('./tr[2]', $root, true, '/(.*?)\s+' . $this->t('to') . '\s+/'))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode('.//tr[not(.//tr) and count(./td)>2 and contains(.,":") and contains(.,"-")][1]/td[4]', $root) . ', ' . $this->http->FindSingleNode('.//tr[not(.//tr) and count(./td)>2 and contains(.,":") and contains(.,"-")][1]/td[2]', $root))));

            // ArrCode
            // ArrDate
            $s->arrival()
                ->noCode()
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode('.//tr[not(.//tr) and count(./td)>2 and contains(.,":") and contains(.,"-")][2]/td[4]', $root) . ', ' . $this->http->FindSingleNode('.//tr[not(.//tr) and count(./td)>2 and contains(.,":") and contains(.,"-")][2]/td[2]', $root))));

            // ArrName
            $arrivalName = $this->http->FindSingleNode('./tr[2]', $root, true, '/.*?\s+' . $this->t('to') . '\s+(.+)\s+[A-Z]{3}/');

            if (empty($arrivalName)) {
                $arrivalName = $this->http->FindSingleNode('./tr[2]', $root, true, '/.*?\s+' . $this->t('to') . '\s+(.+)/');
            }

            $s->arrival()
                ->name($arrivalName);

            /* there is no saved example for this code
             * foreach ([
                         'DepartureTerminal' => $itsegment['DepName'],
                         'ArrivalTerminal' => $itsegment['ArrName']
                     ] as $key => $value) {
                if( stripos($value, 'Term') !== false && preg_match('/(.+)\s+(?:Terminal\s+|Term)([A-Z\d]{1,3})/', $value, $m) ){
                    $itsegment[substr($key,0,3).'Name'] = $m[1];
                    $itsegment[$key] = $m[2];
                } elseif( preg_match('/(.+)\s+\(([A-Z]{3})\)/', $value, $m) ){
                    $itsegment[substr($key,0,3).'Name'] = $m[1];
                    $itsegment[substr($key,0,3).'Code'] = $m[2];
                }
            }*/
        }

        return true;
    }

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
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $this->reBody . '")]')->length < 1) {
            return false;
        }

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $re) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getHeader('subject');
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang=> $phrases) {
            foreach ($phrases as $re) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $this->parseHtml($email);
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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $year = date('Y', $this->date);
        $in = [
            "#^(\d+)-(\d+)-(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
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
