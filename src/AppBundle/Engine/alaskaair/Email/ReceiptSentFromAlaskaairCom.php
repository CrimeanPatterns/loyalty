<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReceiptSentFromAlaskaairCom extends \TAccountChecker
{
    /*
     * Format Flight table:
     *      [Flight, Departs, Arrives] -> this parser
     *      [Flight, Departs, Arrives, Details] -> go to parser Itinerary1
     * */
    public $mailFiles = "alaskaair/it-2.eml, alaskaair/it-2353766.eml, alaskaair/it-2411402.eml, alaskaair/it-2503389.eml, alaskaair/it-2509428.eml, alaskaair/it-2812217.eml, alaskaair/it-3.eml, alaskaair/it-3085741.eml, alaskaair/it-3477468.eml, alaskaair/it-3893770.eml, alaskaair/it-4306628.eml, alaskaair/it-7.eml, alaskaair/it-8.eml, alaskaair/it-9.eml";

    private $detectProvider = "alaskaair.com";

    private $detectSubject = [
        'Receipt sent from alaskaair.com',
        'Itinerary sent from alaskaair.com',
    ];

    private $emailDate = null;
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = strtotime($parser->getDate());

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject'])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'alaskaair.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//*[" . $this->contains(['Traveler Documentation']) . "]")->length > 0
            && $this->http->XPath->query("//a[" . $this->eq(['Details']) . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq(["Alaska Airlines Confirmation Code:", "Flight Confirmation Code:", "Flight confirmation code:"]) . "]/following::text()[normalize-space()][1]",
            null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } else {
            $f->general()
                ->noConfirmation();
        }
        $travellerXpath = "//tr[*[1][" . $this->eq(["Traveler"]) . "] and *[2][" . $this->starts(["Seats"]) . "] ]/following::tr[normalize-space()][1]/ancestor::*[1]/*";

        $travellers = $this->http->FindNodes($travellerXpath . "//text()[" . $this->eq('Name:') . "]/following::text()[normalize-space()][1]", null, "#^\s*(.+)\s*$#");

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[normalize-space(.)='Traveler']/following::text()[starts-with(normalize-space(.),'Name:')]/following::text()[normalize-space()][1]");
        }
        $f->general()
            ->travellers($travellers, true)
        ;

        // Program
        $accouns = $this->http->FindNodes($travellerXpath . "//text()[" . $this->eq('MP#') . "]/following::text()[normalize-space() and normalize-space()!=':'][1][" . $this->starts('Alaska') . "]", null, "#^\s*Alaska\D*\s+(\d{5,})(?:\s*\-.*)?#");

        if (!empty(array_filter($accouns))) {
            $f->program()
                ->accounts($accouns, false);
        }
        // Issued
        $tickets = array_filter($this->http->FindNodes($travellerXpath . "//text()[" . $this->eq('E-Ticket:') . "]/following::text()[normalize-space()][1]", null, "#^\s*(\d{13})\s*$#"));

        if (count($tickets) > 0) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Price
        $totalStr = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight Total for')]");

        if (preg_match("#for (\d+) (?:Travelers?|passengers?):\s*(.+)#", $totalStr, $m)) {
            $trCount = $m[1];

            if (preg_match("#^\s*(\d[\d,. ]*Miles)(?: \+)?\s*(.*)#i", $m[2], $mat)) {
                $f->price()->spentAwards($mat[1]);
                $m[2] = $mat[2];
            }

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $m[2], $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $m[2], $mat)) {
                $f->price()
                    ->total($this->amount($mat['amount']))
                    ->currency($this->currency($mat['curr']))
                ;
            }

            $baseFareStr = $this->http->FindSingleNode("//text()[" . $this->eq(["Base fare", "Base Fare"]) . "]/ancestor::div[1]/following-sibling::*[1][not(contains(., 'Miles'))]");

            if (preg_match("#^\s*(\d[\d,. ]*Miles)(?: \+)?\s*(.*)#i", $baseFareStr, $mat)) {
                $baseFareStr = $mat[2];
            }

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $baseFareStr, $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $baseFareStr, $mat)) {
                $f->price()
                    ->cost($trCount * $mat['amount']);
            }

            $taxes = [];

            $taxesFull = null;
            $taxesStr = $this->http->FindSingleNode("//text()[" . $this->eq(["Taxes and Fees", "Taxes and fees"]) . "]/ancestor::div[1]/following-sibling::*[1]");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxesStr, $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $taxesStr, $mat)) {
                $taxesFull = $this->amount($mat['amount']);
            }
            $taxesXpath = "//text()[" . $this->eq(["Taxes and Fees", "Taxes and fees"]) . "]/ancestor::div[2]/following-sibling::div/div";
            $nodesT = $this->http->XPath->query($taxesXpath);

            foreach ($nodesT as $rootT) {
                $name = $this->http->FindSingleNode("./div[1]", $rootT);
                $value = $this->http->FindSingleNode("./*[2]", $rootT);

                if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $mat)
                        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $value, $mat)) {
                    $amount = $this->amount($mat['amount']);
                    $taxes[] = ['name' => $name, 'amount' => $amount];
                }
            }

            if (round($taxesFull - array_sum(array_column($taxes, 'amount')), 5) == 0) {
                foreach ($taxes as $tax) {
                    $f->price()
                        ->fee($tax['name'], $trCount * $tax['amount']);
                }
            }
        }

        // Segments

        $xpath = '//*[self::th or self::td][' . $this->eq("Departs") . ']/ancestor::table[1]//tr[contains(.//a, "Details")]';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->count() == 0) {
            $xpath = "//text()[contains(normalize-space(), 'Details')]/ancestor::tr";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $seats = [];
        $seatsTexts = $this->http->FindNodes($travellerXpath . "/td[2]");

        if (count($seatsTexts) == 0) {
            $seatsTexts = array_filter($this->http->FindNodes("//text()[normalize-space(.)='Traveler']/following::text()[starts-with(normalize-space(.),'Enter required documentation')]/ancestor::tr[1]", null, "/\d{13}\s+([\dA-Z\,\s]+)\s+\w+/u"));
        }

        foreach ($seatsTexts as $text) {
            $s = array_map('trim', preg_split('#\s*,\s*#', trim($text)));

            if (count($s) === $nodes->length) {
                foreach ($s as $i => $v) {
                    $seats[$i][] = (preg_match("#^\s*\d{1,3}[A-Z]\s*$#", $v)) ? $v : null;
                }
            } else {
                $seats = [];

                break;
            }
        }

        /*if (!isset($seats[$i]) && empty(array_filter($seats[$i]))) {
            $seatsText = $this->http->FindNodes("//text()[normalize-space(.)='Traveler']/following::text()[starts-with(normalize-space(.),'Enter required documentation')]/ancestor::tr[1]", null, "/\d{13}\s+([\dA-Z\,\s]+)\s+\w+/u");

            foreach ($seatsText as $text)
            if (preg_match_all("/(\d{2}[A-Z]{1})/", $text, $m))
            {
                $seats = $m[1];
            }
        }*/

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $col1 = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()][not(ancestor::*[contains(@style, 'display: none') or contains(@style, 'display:none')])]", $root));
            // Airline
            if (preg_match("#^\s*(?:Flight \d+ of \d+\s+)?(?<al>.+)\s(?<fn>\d{1,5})\s*(?:\((.+)\s(\d{1,5})\))?\n#", $col1, $m)) {
                $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                    ;
                $conf = implode("\n", $this->http->FindNodes("//text()[" . $this->contains("Confirmation Code:") . "]/ancestor::div[1][" . $this->contains([$m['al'] . " Airlines", $m['al'] . " Air Lines", $m['al']]) . "]//text()[normalize-space()]"));

                if (preg_match("#(?:^|\n)\s*" . $m['al'] . "(?:\s*Air ?lines)?\s+Confirmation Code:\s*([A-Z\d]{5,7})\b#i", $conf, $mat)) {
                    $s->airline()
                        ->confirmation($mat[1]);
                }
            }

            if (preg_match("#Operated by\s+(.+?)( as .+)?\n#", $col1, $mat)) {
                $operator = str_replace('\u003c!-- mp_trans_disable_start --\u003e', '', $mat[1]);
                $this->logger->error($operator);
                $operator = preg_replace("/\Su003c\!-- mp_trans_disable_end.+/", '', $operator);
                $s->airline()
                    ->operator($operator);
            }

            if (preg_match("#Aircraft\:\s*(.+)\n#", $col1, $mat)) {
                $s->extra()->aircraft($mat[1]);
            }

            // Departure
            $col2 = implode(" ", $this->http->FindNodes("./td[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<name>.*?)\((?<code>[A-Z]{3})\)[\s+]*(?<date>.*\d+:\d+.*)$#", $col2, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Arrival
            $col3 = implode(" ", $this->http->FindNodes("./td[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<name>.*?)\((?<code>[A-Z]{3})\)[\s+]*(?<date>.*\d+:\d+.*)$#", $col3, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            // Extra
            if (isset($seats[$i]) && !empty(array_filter($seats[$i]))) {
                $s->extra()->seats(array_filter($seats[$i]));
            }

            $col1Details = implode("\n",
                $this->http->FindNodes("./td[1]/div", $root));
            $col1DetailsHide = implode("\n",
                $this->http->FindNodes("./td[1]//*[(self::div or self::li) and not(.//li)][ancestor::*[contains(@style, 'display: none') or contains(@style, 'display:none')]]", $root));

            if (preg_match("#(?:\||\n)Aircraft: (.+?)\s*(?:\||$|\n)#s", $col1Details, $m)
                || preg_match("#\n\s*Aircraft: (.+?)\s*(?:\||$|\n)#", $col1DetailsHide, $m)) {
                $s->extra()
                    ->aircraft($m[1]);
            }

            if (preg_match("#(?:\||\n)\s*Duration: (.+?)\s*(?:\||$|\n)#s", $col1Details, $m)
                || preg_match("#\n\s*Duration: (.+?)\s*(?:\||$|\n)#", $col1DetailsHide, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("#(?:\||\n)Meal: (.+?)\s*(?:\||$|\n)#s", $col1Details, $m)
                || preg_match("#\n\s*Meal: (.+?)\s*(?:\||$|\n)#", $col1DetailsHide, $m)) {
                $s->extra()
                    ->meal($m[1]);
            }

            if (!empty($s->getFlightNumber()) && preg_match("#(?:\||\n| " . $s->getFlightNumber() . " )Distance: (.+?)\s*(?:\||$|\n)#s", $col1Details, $m)) {
                $s->extra()
                    ->miles($m[1]);
            }

            $details = $this->http->FindSingleNode("(./td[1]//text()[contains(., '|')])[1]/ancestor-or-self::*[not(normalize-space() = '|')][1]",
                $root);
//            $this->logger->debug('$details = ' . print_r($details, true));
            if (preg_match("#(.+) ?\| ?([^\|]*)stop#", $details, $m)) {
                if (preg_match("#(.+)\(([A-Z]{1,2})\)\s*$#", $m[1], $mat)) {
                    $s->extra()
                        ->cabin(trim($mat[1]))
                        ->bookingCode($mat[2]);
                } else {
                    $s->extra()
                        ->cabin(trim($m[1]));
                }

                if (preg_match("#(\d+)#", $m[2], $mat)) {
                    $s->extra()
                        ->stops($mat[1]);
                } else {
                    $s->extra()
                        ->stops(0);
                }
            }

            $detail2 = $this->http->FindSingleNode("./following-sibling::tr[1][contains(., 'Distance') or contains(., 'Operated by')][not(contains(.//a, 'Details'))]", $root);
//            $this->logger->debug('$detail2 = ' . print_r($detail2, true));
            if (preg_match("#Distance: (.+?)[ ]*(?:\||$)#", $detail2, $m)) {
                $s->extra()
                    ->miles($m[1]);
            }

            if (empty($s->getDuration()) && preg_match("#Duration: (.+?)\s*(?:\||$)#", $detail2, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("#Operated by\s+(.+?)( as .+)?$#", $detail2, $m)) {
                $s->airline()->operator($m[1]);
            }
        }

        return $email;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$dateIN '.$date);
        $year = date("Y", $this->emailDate);
        $in = [
            // 7:25 pm Thu , Oct 15
            "#^\s*\+?\s*(\d{1,2}:\d{2}\s*[ap]m)\s+(\w+)[,\s]+(\w+)\s+(\d+)\s*$#",
            // Fri , Sep 11 8:45 am
            "#^\s*(\w+)[,\s]+(\w+)\s+(\d+)\s*(\d{1,2}:\d{2}\s*[ap]m)\s*$#",
        ];
        $out = [
            "$2, $4 $3 $year, $1",
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }
        //$this->logger->debug('$dateOUT '.$date);
        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
