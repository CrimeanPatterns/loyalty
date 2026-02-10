<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: easyjet/ImportantChanges(object)

class YourBookingFlight extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-66020760.eml, easyjet/it-66021573.eml";
    public $lang = '';
    public $year;

    public $subjects = [
        '/^Ihre Buchung [A-Z\d]{7}/',
        '/^Your booking [A-Z\d]{7}/',
    ];

    public $detectLang = [
        'en' => 'YOUR TRIP DETAILS',
        'de' => 'IHRE FLUGDATEN',
    ];

    public static $dictionary = [
        "en" => [
            //'The Countdown\'s on' => '',
            //'YOUR TRIP DETAILS' => '',
            //'YOUR FLIGHT' => '',
            //'YOUR SEATS' => '',
            //'YOUR BAGS' => '',
            //'Depart' => '',
            //'Your booking' => '',
        ],
        "de" => [
            'The Countdown\'s on' => 'Es geht schon bald los',
            'YOUR TRIP DETAILS'   => 'IHRE FLUGDATEN',
            'YOUR FLIGHT'         => 'HINFLUG',
            'YOUR SEATS'          => 'IHRE SITZPLÄTZE',
            'YOUR BAGS'           => 'IHR GEPÄCK',
            'Depart'              => 'Abflug',
            'Your booking'        => 'Ihre Buchung',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.easyjet.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'easyJet')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('The Countdown\'s on'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR TRIP DETAILS'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR FLIGHT'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR SEATS'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR BAGS'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.easyjet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if ($this->detectLang() == false) {
            $this->logger->warning('Language not defined!!!');

            return false;
        }

        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation();

        $xpath = "//text()[{$this->eq($this->t('Depart'))}]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            //Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^([A-Z]{3})\d{2,4}$/"))
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^[A-Z]{3}(\d{2,4})$/"));

            //Departure
            $timeDep = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]", $root);
            $dateDep = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[2]", $root);

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::table[4]", $root))
                ->date($this->normalizeDate($dateDep . ', ' . $timeDep));

            $terminalDep = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[2]/preceding::table[2]", $root);

            if (!empty($terminalDep) && $terminalDep !== $s->getDepName()) {
                $s->departure()->terminal($terminalDep);
            }

            //Arrival
            $timeArr = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][2]", $root);
            $dateArr = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[1]", $root);

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::table[6]", $root))
                ->date($this->normalizeDate($dateArr . ', ' . $timeArr));

            $terminalArr = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[2]/preceding::table[1]", $root);

            if (!empty($terminalArr) && $terminalArr !== $s->getArrName()) {
                $s->arrival()->terminal($terminalArr);
            }

            //Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::table[2]", $root));

            $seatsText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking'))}]/following::text()[normalize-space()][1]");

            if (preg_match_all("/(\d{1,2}[A-Z])/", $seatsText, $match)) {
                $s->extra()
                    ->seats($match[1]);
            }
        }

        return $email;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function normalizeDate($str)
    {
        $this->logger->warning($str);
        $year = date("Y", $this->date);

        $in = [
            "#^\w+\.?\s*(\d+)\s*(\w+)\.?\,\s*([\d\:]+)$#", // Thu 17 Sep, 21:30
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $word) {
                    if (stripos($body, $word) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
