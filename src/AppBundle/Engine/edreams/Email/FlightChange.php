<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "edreams/it-39167100.eml, edreams/it-39206643.eml";

    private $lang = 'en';

    private $detects = [
        'We have been notified by your airline of a schedule change to your flights',
    ];

    private $from = '/[@\.]edreams\.com/';

    private $prov = 'edreams';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        if ($conf = $this->http->FindSingleNode("//tr[normalize-space(.)='Airline reservation code']/following-sibling::tr[1]/td[2]")) {
            $f->general()
                ->confirmation($conf);
        }

        $paxs = $this->http->XPath->query("//tr[starts-with(normalize-space(.), 'Surname/first name')]/following-sibling::tr/td[1]");

        foreach ($paxs as $pax) {
            $f->addTraveller($pax->nodeValue);

            if ($tn = $this->http->FindSingleNode('following-sibling::td[1]', $pax, true, '/(\d+)/')) {
                $f->addTicketNumber($tn, false);
            }
        }

        $xpath = "//tr[starts-with(normalize-space(.), 'Flight №') and not(.//tr)]/following-sibling::tr[1]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//tr[(starts-with(normalize-space(.), 'Departure') or starts-with(normalize-space(.), 'Return')) and not(.//tr)]/following-sibling::tr[1]";
        }
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (($table = $this->http->FindNodes('td', $root)) && 6 === count($table)) {
                $re = '/\w+ (\d{1,2} \w+ \d{2,4}, \d{1,2}:\d{2})\s*(.+)\s*Airport[ ]*:[ ]*(.+)/i';

                if (preg_match($re, $table[0], $m)) {
                    $name = explode('Terminal', $m[2] . ', ' . $m[3]);

                    if (is_array($name) && 2 === count($name)) {
                        $term = $name[1];
                        $name = $name[0];
                    } else {
                        $name = $m[2] . ', ' . $m[3];
                    }
                    $s->departure()
                        ->date($this->normalizeDate($m[1], 'fr'))
                        ->name($name)
                        ->noCode()
                    ;

                    if (!empty($term)) {
                        $s->departure()
                            ->terminal($term);
                    }
                }

                if (preg_match($re, $table[1], $m)) {
                    $name = explode('Terminal', $m[2] . ', ' . $m[3]);

                    if (is_array($name) && 2 === count($name)) {
                        $term2 = $name[1];
                        $name = $name[0];
                    } else {
                        $name = $m[2] . ', ' . $m[3];
                    }
                    $s->arrival()
                        ->date($this->normalizeDate($m[1], 'fr'))
                        ->name($name)
                        ->noCode()
                    ;

                    if (!empty($term2)) {
                        $s->arrival()
                            ->terminal($term2);
                    }
                }

                if (preg_match('/([A-Z\d]{2})[ ]*(\d+)/', $table[3], $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                    ;
                }

                $s->extra()
                    ->cabin($table[4])
                    ->duration($table[5])
                ;
            }
        }
    }

    private function normalizeDate($date, $lang)
    {
//        $this->logger->debug($date);
        $in = [
            // Vendredi 3 Mai 2019, 10:30
            '#^(\d{1,2} \w+ \d{2,4}, \d{1,2}:\d{2})$#u',
        ];
        $out = [
            '$1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date), $lang));

        return $str;
    }

    private function dateStringToEnglish($date, $lang)
    {
        $lang = $lang ?? $this->lang;

        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
