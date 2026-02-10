<?php

namespace AwardWallet\Engine\check\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "check/it-29626009.eml, check/it-30325720.eml, check/it-30841488.eml, check/it-669453142.eml";

    public static $dictionary = [
        "de" => [
            'Anreisedatum'     => ['Anreisedatum', 'Anreise'],
            'Abreisedatum'     => ['Abreisedatum', 'Abreise'],
            "Anzahl der Gäste" => ["Anzahl der Gäste", "Gäste"],
            'statusPhrases'    => 'Ihre Buchung',
            'statusVariants'   => 'bestätigt',
            "Gesamtpreis"      => ["Gesamtpreis", "Buchungspreis"],
            "Hallo"            => ["Hallo", "Sehr geehrter Herr"],
        ],
    ];

    private $detectFrom = "check24.de";

    private $detectSubject = [
        "de"  => "Buchungsbestätigung Ihrer Unterkunft",
        "de2" => "Eingangsbestätigung Ihrer Buchung in der Unterkunft",
    ];
    private $detectCompany = "CHECK24";
    private $detectBody = [
        "de" => "Ihre Buchungsdetails im Überblick", "Buchungsinformationen",
    ];

    private $lang = "de";

    public function parseEmail(Email $email): void
    {
        $patterns = [
            'date'          => '\b(?:\d{1,2}[-,.\s]+[[:alpha:]]+[-,.\s]+\d{2,4}|\d{1,2}\.\d{1,2}\.\d{2,4})\b', // 26. Juni 2024    |    30.08.2018
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        // Price
        $total = $this->nextText($this->t("Gesamtpreis"));

        if (!empty($total)) {
            $email->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $email->ota()->confirmation($this->nextText($this->t("Buchungsnummer"), null, "/^([-A-Z\d]{5,})(?:\s*\(|$)/"), "Buchungsnummer");

        $h = $email->add()->hotel();

        // General
        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Vielen Dank,')][1]", null, true, "/.+,\s*(?:Herr|Frau)?\s*({$patterns['travellerName']}) *!/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', anbei erhalten Sie Ihre Buchungsunterlagen')]", null, true, "/(.+)\s*{$this->opt($this->t(', anbei erhalten Sie Ihre Buchungsunterlagen'))}/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hallo'))}]", null, true, "/{$this->opt($this->t('Hallo'))}\s*(.+)/");
        }

        $h->general()->traveller($traveller);

        $reservationNumbers = $this->http->FindNodes("//node()[{$this->eq($this->t('Reservierungsnummer:'), 'translate(.," ","")')}]/following-sibling::*[normalize-space()][1]/descendant::li[normalize-space()]", null, "/^[-A-Z\d]{5,}$/");

        if (count($reservationNumbers) > 0) {
            foreach ($reservationNumbers as $resNumber) {
                $h->general()->confirmation($resNumber);
            }
        } elseif ($this->http->XPath->query("//node()[{$this->eq($this->t('Reservierungsnummer:'), 'translate(.," ","")')}]")->length === 0) {
            $h->general()->noConfirmation();
        }

        // Hotel
        $xpathHotel = "//tr[count(*)=2 and *[1][string-length(normalize-space())<2]/descendant::img and *[2]/descendant::text()[normalize-space()][1]/ancestor::h1][not(preceding::text()[{$this->eq(['Buchung in Apple Wallet speichern »', 'Wegbeschreibung anzeigen »'])}])]/*[2]";
        $hotelName = $this->http->FindSingleNode($xpathHotel . "/descendant::h1[not(contains(normalize-space(), 'Cashback'))]");
        $address = implode("\n", $this->http->FindNodes($xpathHotel . "/descendant::h1/following-sibling::p[normalize-space()][1]/descendant::text()[normalize-space()]"));
        $phone = null;

        if (preg_match("/^(?<address>[\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('Tel.'))}[:\s]+(?<phone>{$patterns['phone']})/", $address, $m)) {
            $address = $m['address'];
            $phone = $m['phone'];
        }

        if (!$phone) {
            $phoneValues = array_filter($this->http->FindNodes($xpathHotel . "/descendant::h1/following-sibling::p[normalize-space()]", null, "/^{$patterns['phone']}$/"));

            if (count(array_unique($phoneValues)) === 1) {
                $phone = array_shift($phoneValues);
            }
        }
        $h->hotel()->name($hotelName)->address(preg_replace(['/^([\s\S]{3,}?)\n+.+@.+/', '/[ ]*\n+[ ]*/'], ['$1', ', '], $address))->phone($phone, false, true);

        // Booked
        $checkInValue = $this->nextText($this->t("Anreisedatum"));

        if (preg_match("/^(?<date>.{6,}?)\s*\([^)(]*?(?<time>{$patterns['time']})/", $checkInValue, $m)) {
            $dateCheckIn = strtotime($this->normalizeDate($m['date']));
            $h->booked()->checkIn(strtotime($m['time'], $dateCheckIn));
        } elseif (preg_match("/^[^)(]*\d[^)(]*$/", $checkInValue)) {
            $h->booked()->checkIn(strtotime($this->normalizeDate($checkInValue)));
        }

        $checkOutValue = $this->nextText($this->t("Abreisedatum"));

        if (preg_match("/^(?<date>.{6,}?)\s*\([^)(]*\b(?<time>{$patterns['time']})/", $checkOutValue, $m)) {
            $dateCheckOut = strtotime($this->normalizeDate($m['date']));
            $h->booked()->checkOut(strtotime($m['time'], $dateCheckOut));
        } elseif (preg_match("/^[^)(]*\d[^)(]*$/", $checkOutValue)) {
            $h->booked()->checkOut(strtotime($this->normalizeDate($checkOutValue)));
        }

        $h->booked()
            ->guests($this->nextText($this->t("Anzahl der Gäste"), null, "/\b(\d{1,3})\s*Erwachsene/i"))
            ->kids($this->nextText($this->t("Anzahl der Gäste"), null, "/\b(\d{1,3})\s*Kind/i"), false, true)
        ;

        $rooms = $this->nextText($this->t("Anzahl der Zimmer"));

        if (preg_match("/(?:^|\(\s*)(?<num>\d{1,3})\s+(?<type>.{2,}?)(?:\s*\)|$)/", $rooms, $m)) {
            // 1 Standard Doppelzimmer
            $h->booked()->rooms($m['num']);

            for ($i = 1; $i <= $m['num']; $i++) {
                $h->addRoom()->setType($m['type']);
            }
        }

        $status = null;
        $statusValues = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}.*?\s+{$this->opt($this->t('ist'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/u"));

        if (count(array_unique($statusValues)) === 1) {
            $status = array_shift($statusValues);
        }

        if (!empty($status)) {
            $h->general()->status($status);
        }

        $cancellation = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Stornierungsbedingungen'))}]/ancestor::table[1]/descendant::text()[normalize-space()][position()>1][not({$this->eq($this->t('Buchung stornieren »'))})]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Dieses Angebot ist bis zum')][1]");
        }

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Diese Buchung ist nicht kostenfrei stornierbar')])[1]"))) {
            $h->booked()->nonRefundable();
        } elseif (preg_match("/Stornierungskosten\s*\(\s*in Hotel-Ortszeit\s*\)\s*:\s*Bis\s+(?<date>{$patterns['date']})[,\s]+(?<time>{$patterns['time']})\s*Uhr\s*:\s*0,00\s*[A-Z]{3}/", $cancellation, $m) // de
            || preg_match("/zum\s+(?<date>{$patterns['date']})\s+um\s+(?<time>{$patterns['time']})\s*Uhr\b/", $cancellation, $m) // de
        ) {
            $dateDeadline = strtotime($this->normalizeDate($m['date']));
            $h->booked()->deadline(strtotime($m['time'], $dateDeadline));
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
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            // 30.08.2018
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b(\d{1,2})[-,.\s]+([[:alpha:]]+)[-,.\s]+(\d{4})$/u', $text, $m)) {
            // Mo. 27. Mai 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
