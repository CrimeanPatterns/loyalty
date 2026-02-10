<?php

namespace AwardWallet\Engine\check\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingRental extends \TAccountChecker
{
    public $mailFiles = "check/it-175434654.eml, check/it-31131530.eml";

    public static $dictionary = [
        "de" => [
            'Ihre Buchungsnr.:' => ['Ihre Buchungsnr.:', 'Ihre Buchungsnummer:'],
            'Sehr geehrter '    => ['Sehr geehrter ', 'Herr ', 'Frau '],
            "Vermieter"         => ["Vermieter", "Vermieter vor Ort"],
        ],
    ];

    private $detectFrom = "check24.de";

    private $detectSubject = [
        "de" => "Eingangsbestätigung Ihrer Mietwagenbuchung Nr.",
    ];
    private $detectCompany = "CHECK24";
    private $detectBody = [
        "de" => ["Eingangsbestätigung Ihrer Mietwagenbuchung", "Ihre Buchungsdaten im Überblick", "Ihre Buchung wurde bestätigt"],
    ];

    private $lang = "";

    public function parseEmail(Email $email)
    {
        // Price
        $total = $this->nextText($this->t("Gesamtpreis"));

        if (!empty($total)) {
            $email->price()
                ->total(PriceHelper::parse($this->re("/([\d\,\.]+)/", $total), $this->currency($total)))
                ->currency($this->currency($total));
        }

        $email->ota()
            ->confirmation($this->nextText($this->t("Ihre Buchungsnr.:")));

        $r = $email->add()->rental();

        // General
        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Sehr geehrter '))}])[1]", null, true, "#{$this->opt($this->t('Sehr geehrter '))}(?:Herr|Frau)?\s*(.+?),#"), true);

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Abholung'))}]")->length > 0) {
            // Pick Up
            $location = implode("\n", $this->http->FindNodes("(//text()[" . $this->eq($this->t('Abholung')) . "])[1]/following::tr[normalize-space()][2]//text()[normalize-space()]"));
            $location = str_replace("\n", ' ', preg_replace("#(^\s*.*Flughafen.*|[\s\S]*?\(\s*Flugnummer:[^\)]+\)\s*)#", '', $location));

            if (!empty($location)) {
                $r->pickup()
                    ->date($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Abholung')) . "])[1]/following::tr[normalize-space()][1]")))
                    ->location($location);
            }

            // Drop Off
            $location = implode("\n", $this->http->FindNodes("(//text()[" . $this->eq($this->t('Rückgabe')) . "])[1]/following::tr[normalize-space()][2]//text()[normalize-space()]"));
            $location = str_replace("\n", ' ', preg_replace("#(^\s*.*Flughafen.*|[\s\S]*?\(\s*Flugnummer:[^\)]+\)\s*)#", '', $location));

            if (!empty($location)) {
                $r->dropoff()
                    ->date($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Rückgabe')) . "])[1]/following::tr[normalize-space()][1]")))
                    ->location($location);
            }

            // Car
            $r->car()
                ->model($this->nextText($this->t("Fahrzeugkategorie")))
                ->type($this->nextText($this->t("Fahrzeugeigenschaften")));
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Abholung und Rückgabe'))}]")->length > 0) {
            $r->pickup()
                ->location($this->http->FindSingleNode("//text()[normalize-space()='Abholung und Rückgabe']/following::text()[string-length()>3][1]/ancestor::tr[1]"));
            $r->dropoff()
                ->same();

            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Abhol-/Rückgabedaten']/following::text()[string-length()>3][1]",
                    null, true, "/^\s*(.+?)(?: bis .+)?$/")));

            $date = $this->http->FindSingleNode("//text()[normalize-space()='Abhol-/Rückgabedaten']/following::text()[string-length()>3][1][contains(., ' bis ')]",
                null, true, "/^\s*.+? bis (.+)/");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("//text()[normalize-space()='Abhol-/Rückgabedaten']/following::text()[string-length()>3][2]");
            }
            $r->dropoff()
                ->date($this->normalizeDate($date));

            // Car
            $r->car()
                ->model($this->http->FindSingleNode("//text()[normalize-space()='Fahrzeugdetails']/following::text()[string-length()>3][1]"))
                ->type($this->http->FindSingleNode("//text()[normalize-space()='Fahrzeugdetails']/following::text()[string-length()>3][2]"))
            ;
        }

        // Extra
        $r->extra()
            ->company($this->nextText($this->t("Vermieter")));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            foreach ($dBody as $word) {
                if (stripos($body, $word) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        /*if (empty($this->lang)) {
        	// TODO: don't know how yet
//            $body = mb_convert_encoding($body, 'CP1251');
//            $this->http->SetEmailBody($body);
            foreach ($this->detectBody as $lang => $dBody){
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;
                    break;
                }
            }
        }*/

        if (empty($this->lang)) {
            $this->lang = "de";
        }

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
            foreach ($detectBody as $word) {
                if (strpos($body, $word) !== false) {
                    return true;
                }
            }
        }

        /*$body = html_entity_decode($this->http->Response["body"]);
        $body = mb_convert_encoding($body, 'CP1251');
        foreach($this->detectBody as $detectBody){
            foreach ($detectBody as $word) {
                if (strpos($body, $word) !== false)
                    return true;
            }
        }*/

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

    private function normalizeDate($str)
    {
        $this->http->log($str);
        $in = [
            "#^\s*[^\d\s]+,\s*(\d{1,2})[\.\s]+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)\s*$#", //Sa., 29. Dezember 2018 10:30
            "#^\D+\s([\d\.]+)\D+([\d\:]+)\D+$#", //Mi., 13.07.2022 um 21:30 Uhr bis
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
