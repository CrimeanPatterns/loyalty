<?php

namespace AwardWallet\Engine\alitalia\Email;

class BlueBizHtml2015It extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-5495781.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();
        $this->parseReservation();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'info@service.airfrance.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Il suo biglietto premio BlueBiz Air France KLM Alitalia') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Ha appena effettuato l\'acquisto di un biglietto premio BlueBiz e la ringraziamo') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@service.airfrance.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    protected function parseReservation()
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Codice del dossier di prenotazione:")]', null, false, '/:\s*([A-Z\d]+)/');
        $this->result['Status'] = $this->http->FindSingleNode('(//*[contains(text(), "Stato")]/ancestor::td[1])[1]', null, false, '/:\s*(.+)/');
        $this->result['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('//*[contains(text(), "Il suo dossier è stato creato")]', null, false, '/\d+ \w+ \d{4}/')));
        $this->result['Passengers'] = $this->http->FindNodes('//*[contains(text(), "N° di biglietto")]/ancestor::table[2]//table[1]', null, '/[[:alpha:]\s]+/');
        $this->result['SpentAwards'] = $this->http->FindSingleNode('//*[contains(text(), "Costo della prenotazione")]/ancestor::tr[1]', null, false, '/\d+\s*BlueCredits/');
        $this->parseSegments();
    }

    protected function parseSegments()
    {
        foreach ($this->http->XPath->query('//*[contains(text(), "Operato da")]/ancestor::table[1]') as $current) {
            foreach ($this->http->XPath->query('preceding-sibling::table[1]', $current) as $preceding) {
                $date = $this->http->FindSingleNode('preceding-sibling::p[1]/span[last()]', $preceding, false, '/\d+ \w+ \d{4}/');
                $this->result['TripSegments'][] = $this->parseSegment($date, $preceding, $current);
            }
        }
    }

    protected function parseSegment($date, $preceding, $current)
    {
        // AF1727 - Economy
        if ($i = $this->match($this->http->FindSingleNode('.//tr[1]/td[1]', $preceding), '/([A-Z\d]{2})\s*(\d+) - (\w+)/', true)) {
            $segment['AirlineName'] = $i[0];
            $segment['FlightNumber'] = $i[1];
            $segment['Cabin'] = $i[2];
        }

        $segment['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $this->match($this->http->FindSingleNode('.//tr[1]/td[2]', $preceding), '/\d+:\d+/')));

        if ($dep = $this->match($this->http->FindSingleNode('.//tr[1]/td[3]', $preceding), '/(.+?)\s+\(([A-Z]{3})\)/u', true)) {
            $segment['DepName'] = $dep[0];
            $segment['DepCode'] = $dep[1];
        }

        $segment['ArrDate'] = strtotime($this->normalizeDate($date . ', ' . $this->match($this->http->FindSingleNode('.//tr[3]/td[2]', $preceding), '/\d+:\d+/')));

        if ($arr = $this->match($this->http->FindSingleNode('.//tr[3]/td[3]', $preceding), '/(.+?)\s+\(([A-Z]{3})\)/u', true)) {
            $segment['ArrName'] = $arr[0];
            $segment['ArrCode'] = $arr[1];
        }

        $segment['Operator'] = $this->http->FindSingleNode('.//*[contains(text(), "Operato da")]/ancestor::td[1]', $current, false, '/:\s*(.+)/');
        $segment['BookingClass'] = $this->http->FindSingleNode('.//*[contains(text(), "Classe")]/ancestor::td[1]', $current, false, '/:\s*([A-Z])/');
        $segment['Aircraft'] = $this->http->FindSingleNode('.//*[contains(text(), "Aeromobile")]/ancestor::td[1]', $current, false, '/:\s*(.+)/');
        $segment['Duration'] = $this->http->FindSingleNode('.//*[contains(text(), "Durata del volo")]/ancestor::td[1]', $current, false, '/:\s*(\d+h\d+)/');

        return $segment;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function match($text, $pattern, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string));
    }

    protected function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        // false if odd
        if (count($array) % 2 !== 0) {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function normalizeDate($string)
    {
        $months['it'] = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

        foreach ($months as $value) {
            $date = str_ireplace($value, ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'], $string);

            if ($date !== $string) {
                return $date;
            }
        }

        return $string;
    }
}
