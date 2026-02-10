<?php

namespace AwardWallet\Engine\flogix\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "flogix/it-6139940.eml, flogix/it-7646789.eml, flogix/it-7741861.eml, flogix/it-156499468-de.eml";

    public $lang = '';
    public static $dictionary = [
        'de' => [
            "Itinerary for Record Locator" => "Reiseplan für Buchungsnummer",
            "Record Locator"               => "Buchungsnummer",
            "Departing"                    => "Abflug",
            // "Document Number" => "",
            "Grand Total for all travelers" => "Gesamtbetrag für alle Reisenden",
            "Base"                          => "Basis",
            "Taxes"                         => "Steuern",
            "Total"                         => "Gesamt",
        ],
        'en' => [],
    ];

    private $detectFrom = "no-reply@farelogix.com";
    private $detectSubject = [
        "de" => "Reiseplan für Buchungsnummer",
        "en" => "Itinerary for Record Locator",
    ];
    private $detectBody = [
        "de" => ["Reiseplan für Buchungsnummer"],
        "en" => ["Itinerary for Record Locator"],
    ];
    private $subject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $this->lang = '';

        foreach ($this->detectBody as $lang=>$detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (!empty($this->lang)) {
            $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td)=9]";
            $nodes = $this->http->XPath->query($xpath);

            return $nodes->length > 0;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $confNo = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Itinerary for Record Locator")) . "]/following::text()[normalize-space()][1]");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Itinerary for Record Locator")) . "]", null, false, "/{$this->opt($this->t("Itinerary for Record Locator"))}[ ]+([A-Z\d]{5,6})\b/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindPreg("/{$this->opt($this->t("Itinerary for Record Locator"))}[ ]+([A-Z\d]{5,6})\b/", false, $this->subject);
        }
        // Travel Agency
        $email->ota()
            ->confirmation($confNo, $this->t("Record Locator"));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellers = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td)=9]/following-sibling::tr[1]//tr[not(.//tr)]/td[1]", null, "#^\s*(?:MR |MS |MRS )?(.+?)\s*(\(|$)#")));
        // remove 'Special Service:'
        $travellers = array_filter(preg_replace("/^[[:alpha:] ]+:$/", '', $travellers));
        $f->general()
            ->travellers($travellers);

        // Issued
        $tickets = $this->http->FindNodes("//text()[" . $this->eq($this->t("Document Number")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        $rls = [];

        foreach ($this->http->FindNodes("//text()[{$this->contains($this->t("Record Locator"))}]") as $str) {
            $airline = $this->re("/(.+?)\s+{$this->opt($this->t("Record Locator"))}/", $str);
            $rls[$airline] = $this->re("/{$this->opt($this->t("Record Locator"))}\s+(.+)/", $str);
        }

        $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td)=9]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->http->FindSingleNode("./td[1]", $root);
            $s->airline()
                ->name($this->http->FindSingleNode("./td[1]//img[contains(@src, '/Providers/Air/')]/@src", $root, true, "#/Providers/Air/([A-Z][A-Z\d]|[A-Z\d][A-Z])\.#") ?? $airline)
                ->number($this->http->FindSingleNode("./td[2]", $root))
            ;

            if (!empty($airline) && !empty($rls[$airline])) {
                $s->airline()->confirmation($rls[$airline]);
            }

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "#(.+?)(?:\s*Terminal:|$)#"))
                ->terminal($this->http->FindSingleNode("./td[3]", $root, true, "#.+?\s*Terminal:\s*(.+)#"), true, true)
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[5]", $root, true, "#(.+?)(?:\s*Terminal:|$)#"))
                ->terminal($this->http->FindSingleNode("./td[5]", $root, true, "#.+?\s*Terminal:\s*(.+)#"), true, true)
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[6]", $root)))
            ;

            // Extra
            $s->extra()
                ->bookingCode($this->http->FindSingleNode("./td[7]", $root), true, true)
                ->cabin($this->http->FindSingleNode("./td[8]", $root), true, true);

            $meal = $this->http->FindSingleNode("./td[9]", $root);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $seats = array_filter($this->http->FindNodes("following-sibling::tr[1]//tr[not(.//tr)]/td[3]", $root, "/^\s*(?:{$this->opt($this->t("Seat"))}\s*)?(\d{1,3}[A-Z])(?:\s*-\s*.+)?\s*$/"));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Grand Total for all travelers"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        if (!empty($total)) {
            $f->price()
                ->total($total)
                ->currency($this->http->FindSingleNode("//text()[{$this->eq($this->t("Grand Total for all travelers"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[3]"));
        }

        $xpathPrice = "//*[self::td or self::th][{$this->eq($this->t("Base"))} and {$this->eq($this->t("Taxes"), 'following-sibling::*[1]')}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][count(*)>3][not({$this->contains($this->t("Total"), '*[1]')})]";
        $nodes = $this->http->XPath->query($xpathPrice);
        $taxes = 0.0;
        $cost = 0.0;

        foreach ($nodes as $root) {
            $text = implode("\n", $this->http->FindNodes("td", $root));

            if (preg_match("#\n(?<base>\d[\d\,\. ]+)\n(?<taxes>\d[\d\,\. ]+)\n\d[\d\,\. ]+\n([A-Z]{3})$#", $text, $m)) {
                $taxes += $this->amount($m['taxes']);
                $cost += $this->amount($m['base']);

                if ($this->amount($m['base']) === null || $this->amount($m['taxes']) === null) {
                    $taxes = 0.0;
                    $cost = 0.0;

                    break;
                }
            }
        }

        if (!empty($taxes) || !empty($cost)) {
            $f->price()
                ->cost($cost)
                ->tax($taxes)
            ;
        }
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // WED 27MAR 06:00 AM
            '/^\s*([-[:alpha:]]+)\s+(\d{1,2})\s*([[:alpha:]]+)\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1, $2 $3 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>[-[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price): ?float
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
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
