<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlyingBlue extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-56355544.eml, airfrance/it-56398674.eml";
    public $reFrom = ["@airfrance.com", '@service-flyingblue.com'];
    public $reSubject = [
        "en"=> "Flying Blue booking confirmation email",
        "Flying Blue booking confirmation",
        // fr
        "Confirmation de réservation Flying Blue",
        // nl
        "Bevestiging Flying Blue-boeking",
        // de
        "Flying Blue-Buchungsbestätigung",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en" => ["Flying Blue booking confirmation"],
        "fr" => ["Confirmation de réservation Flying Blue"],
        "nl" => ["Bevestiging Flying Blue-boeking"],
        "de" => ["Flying Blue-Buchungsbestätigung"],
    ];

    private static $dictionary = [
        'en' => [
            // 'Flying Blue booking confirmation' => '',
            // 'Your reservation reference number' => '',
            // 'Operated by' => '',
            // 'Aircraft type:' => '',
            // 'Passenger details' => '',
            // 'Blue number' => '',
            // 'Payment' => '',
            // 'Taxes and surcharges' => '',
            // 'Total amount paid in Miles' => '',
            // 'Total amount paid online' => '',
        ],
        'fr' => [
            'Flying Blue booking confirmation'  => 'Confirmation de réservation Flying Blue',
            'Your reservation reference number' => 'Votre numéro de référence de réservation',
            'Operated by'                       => 'Opéré par',
            'Aircraft type:'                    => 'Type d’appareil :',
            'Passenger details'                 => 'Détails passager',
            'Blue number'                       => 'Numéro Flying Blue',
            'Payment'                           => 'Paiement',
            'Taxes and surcharges'              => 'Taxes et surcharges',
            'Total amount paid in Miles'        => 'Montant total payé en Miles',
            'Total amount paid online'          => 'Montant total payé en ligne',
        ],
        'nl' => [
            'Flying Blue booking confirmation'  => 'Bevestiging Flying Blue-boeking',
            'Your reservation reference number' => 'De referentiecode van uw boeking is',
            'Operated by'                       => 'Uitgevoerd door',
            'Aircraft type:'                    => 'Type toestel:',
            'Passenger details'                 => 'Passagiersgegevens',
            'Blue number'                       => 'Flying Blue-nummer',
            'Payment'                           => 'Betaling',
            'Taxes and surcharges'              => 'Belasting en toeslagen',
            'Total amount paid in Miles'        => 'Totaalbedrag betaald met Miles',
            'Total amount paid online'          => 'Totaalbedrag betaald met online betaling',
        ],
        'de' => [
            'Flying Blue booking confirmation'  => 'Flying Blue-Buchungsbestätigung',
            'Your reservation reference number' => 'Ihr Buchungscode:',
            'Operated by'                       => 'durchgeführt von',
            'Aircraft type:'                    => 'Flugzeugtyp:',
            'Passenger details'                 => 'Passagierdaten',
            'Blue number'                       => 'Flying Blue-Nummer',
            'Payment'                           => 'Zahlung',
            'Taxes and surcharges'              => 'Steuern, Gebühren und Zuschläge',
            'Total amount paid in Miles'        => 'Mit Meilen beglichener Gesamtbetrag',
            'Total amount paid online'          => 'Online bezahlter Gesamtbetrag',
        ],
    ];
    private $lang = 'en';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query('//*[' . $this->contains($re) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

        $flight = $email->add()->flight();

        $flight->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation reference number'))}]", null, true, '/:\s*([A-Z\d]{5,7})\s*$/'));

        $travellersXpath = "//text()[{$this->eq($this->t('Passenger details'))}]/following::text()[normalize-space(.)][1]/ancestor::*[not({$this->contains($this->t('Passenger details'))})][last()]//text()[contains(., '•')]/ancestor::tr[1]";
        $flight->general()
            ->travellers($this->http->FindNodes($travellersXpath, null, "/^[\s\W]*(\D+)\s*(?:[-].*{$this->preg_implode($this->t('Blue number'))}|$)/"), true);

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes($travellersXpath, null, "/{$this->preg_implode($this->t('Blue number'))}\s*:?\s*(\d{10,15})$/")));

        if ($accounts) {
            $flight->program()
                ->accounts($accounts, false);
        } else {
            $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flying Blue booking confirmation'))}]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{10,15})\s*$/");

            if ($account) {
                $flight->program()
                    ->account($account, false);
            }
        }

        // Price
        $flight->price()
            ->total($this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment'))}]/following::text()[{$this->starts($this->t('Total amount paid online'))}]/following::text()[normalize-space()][1]", null, true, '/([\d\.]+)\s*[A-Z]{3}/'))
            ->tax($this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment'))}]/following::text()[{$this->starts($this->t('Taxes and surcharges'))}]/following::text()[normalize-space()][1]", null, true, '/([\d\.]+)\s*[A-Z]{3}/'))
            ->currency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment'))}]/following::text()[{$this->starts($this->t('Total amount paid online'))}]/following::text()[normalize-space()][1]", null, true, '/[\d\.]+\s*([A-Z]{3})/'));

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment'))}]/following::text()[{$this->starts($this->t('Total amount paid in Miles'))}]/following::text()[normalize-space()][1]", null, true, '/(\d+)/');

        if (!empty($spentAwards)) {
            $flight->price()
                ->spentAwards($spentAwards);
        }

        $xpath = "//text()[{$this->contains($this->t('Operated by'))}]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $segment = $flight->addSegment();

            $dateText = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[normalize-space()][3]", $root);
            $depDate = null;
            $arrDate = null;

            if (preg_match("/^(?<date>.+?)[:]\s*(?<dTime>[\dh:]+)\s*-\s*(?<aTime>[\dh:]+)\s*(?:\(\s*D(?<overnight>[-+]\d)\))?/", $dateText, $m)) {
                $depDate = $this->normalizeDate($m['date'] . ', ' . str_replace('h', ':', $m['dTime']));
                $arrDate = $this->normalizeDate($m['date'] . ', ' . str_replace('h', ':', $m['aTime']));

                if (!empty($m['overnight'])) {
                    $arrDate = strtotime($m['overnight'] . ' days', $arrDate);
                }
            }

            $segment->departure()
                ->date($depDate)
                ->name($this->http->FindSingleNode("./ancestor::table[1]/preceding::table[contains(normalize-space(), ', (')][2]", $root))
                ->noCode();

            $segment->arrival()
                ->date($arrDate)
                ->name($this->http->FindSingleNode("./ancestor::table[1]/preceding::table[contains(normalize-space(), ', (')][1]", $root))
                ->noCode();

            $segment->airline()
                ->name($this->http->FindSingleNode("./.", $root, true, "/^([A-Z]{2})\d+/"))
                ->number($this->http->FindSingleNode("./.", $root, true, "/^[A-Z]{2}(\d{2,4})/"))
                ->operator($this->http->FindSingleNode("./.", $root, true, "/{$this->preg_implode($this->t('Operated by'))} ?[:]\s+(\D+)\s+[-]/"));

            $aircraft = $this->http->FindSingleNode("./.", $root, true, "/{$this->preg_implode($this->t('Aircraft type:'))}\s+(.+)$/");

            if (!empty($aircraft)) {
                $segment->extra()
                    ->aircraft($aircraft);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers["from"]) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query('//*[' . $this->contains($re) . ']')->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // Thursday, March 19, 20:20
            '/^([-[:alpha:]]{2,}),\s+([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d+:\d+)\s*$/u',
            // Saturday 27 November 2021, 06:00
            // Dienstag 31. Oktober 2023, 18:40
            '/^[-[:alpha:]]{2,}\s+(\d{1,2})\.?\s+([[:alpha:]]{3,})\s+(\d{4})\s*,\s*(\d+:\d+)\s*$/u',
            //  Jeudi 30 septembre, 11:40
            '/^([-[:alpha:]]{2,})\s+(\d{1,2})\s+([[:alpha:]]{3,})\s*,\s*(\d+:\d+)\s*$/u',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
            '$1 $2 $3, $4',
            '$1, $2 $3 ' . $year . ', $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)(?:\s+\d{4}|$)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
