<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LifemilesAwardTicketRedemption extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-17466823.eml, aviancataca/it-1886439.eml, aviancataca/it-2065965.eml, aviancataca/it-43585685.eml";
    public static $dictionary = [
        'en' => [
            "Your reservation code" => ["Your reservation code", "Reservation Code:"],
            "Traveler(s)"           => ["Traveler(s)", "Passenger Name", "Passenger name"],
            "Total:"                => ["Total:", "Total :", "Total"],
            "Redeemed Miles:"       => ["Redeemed Miles:", "Redeemed miles:", "Miles redeemed:"],
            //			"Hello," => "",
            //			"Cabin" => "",
            //			"Departure:" => "",
            //			"Arrival:" => "",
            //			"Your LifeMiles Number:" => "",
            //			"Date" => "",
            //			"Destination" => "",
        ],
        'es' => [
            "Your reservation code"  => ["Tu código de reserva", "Código de reserva:", "CГіdigo de reserva:", "¡Tu código de reserva"],
            "Traveler(s)"            => ["Viajero(s)", "Pasajeros:"],
            "Total:"                 => "Total",
            "Redeemed Miles:"        => "Millas redimidas",
            "Hello,"                 => ["Hola,", "Hola "],
            "Cabin"                  => "Cabina",
            "Departure:"             => ["Salida:", "Origen:"],
            "Arrival:"               => ["Llegada:", "Destino:"],
            "Your LifeMiles Number:" => ["Tu Número LifeMiles:", "Tu NГєmero LifeMiles:"],
            "Date"                   => "Origen",
            "Destination"            => "Destino",
        ],
    ];

    private $detectFrom = ["lifemiles.com", "avianca.com"];
    private $detectSubject = [
        'Lifemiles award ticket redemption',
        'Felicidades! Has redimido LifeMiles:',
        'Congratulations! You have redeemed LifeMiles',
        'You’ve redeemed LifeMiles',
    ];

    private $detectBody = [
        'en' => [
            'We hope you’ll enjoy your ticket redeemed with LifeMiles',
            'We hope you enjoy your redeemed air ticket with LifeMiles',
            'Miles Purchase (LifeMiles+Money',
        ],
        'es' => [
            'Esperamos que disfrutes tu tiquete redimido en LifeMiles',
            'Millas compradas (LifeMiles+Money',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false || $this->http->XPath->query("//*[contains(normalize-space(), '" . $detect . "')]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false || $this->http->XPath->query("//*[contains(normalize-space(), '" . $detect . "')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
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
        return count(self::$dictionary) * 2; // blocks and tables
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Your reservation code")) . "][1])[1]", null, true, "#" . $this->preg_implode($this->t("Your reservation code")) . "[:\s]*([A-Z\d]{5,7})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Your reservation code")) . "]/following::text()[normalize-space(.)][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");
        }

        if (empty($conf)) {
            $confs = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Your reservation code")) . "][1]", null, "#" . $this->preg_implode($this->t("Your reservation code")) . "[:\s]*([A-Z\d]{5,7})\s*$#")));

            if (count($confs) == 1) {
                $conf = array_shift($confs);
            }
        }
        $f->general()
            ->confirmation($conf)
            ->travellers($this->http->FindNodes('//td[' . $this->starts($this->t("Traveler(s)")) . ' and not(.//td)]/following-sibling::td[string-length(normalize-space(.)) > 0]//text()[normalize-space()]'));

        // Price
        $total = $this->http->FindSingleNode("//td[{$this->starts($this->t("Total:"))}]/following-sibling::td[normalize-space()][1]");

        if (!empty($total)) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }
        $f->price()
            ->spentAwards($this->http->FindSingleNode('//text()[' . $this->starts($this->t("Redeemed Miles:")) . ']/ancestor::td[1]/following-sibling::td[normalize-space()][1]', null, true, '#^(.+?)\**$#'));

        // Program
        $account = $this->http->FindSingleNode('(//td[' . $this->starts($this->t("Hello,")) . ']/ancestor::td[1]/descendant::text()[normalize-space()][2])[1]', null, true, "#^\s*(\d{5,})\s*$#");

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your LifeMiles Number:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*(\d{5,})\s*$#");
        }

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status: LifeMiles'))}]/preceding::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#");
        }

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//text()[" . $this->starts($this->t("Departure:")) . "]/ancestor::tr[1]";
        //		$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);
        // Type 1 - blocks
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]/td[normalize-space()][last()]", $root, true, "#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}#"))
                ->number($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]/td[normalize-space()][last()]", $root, true, "#^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#"));

            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#(?<dName>.+?)\((?<dCode>[A-Z]{3})\)\s*[\W\S]\s*(?<aName>.+?)\((?<aCode>[A-Z]{3})\)\s+"
                    . $this->preg_implode($this->t("Departure:")) . "\s*(?<dDate>.+)\s+"
                    . $this->preg_implode($this->t("Arrival:")) . "\s*(?<aDate>.+)(?:\s+(?<cabin>.*" . $this->preg_implode($this->t("Cabin")) . ".*))?#", $node, $m)) {
                // Departure
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['dDate']));

                // Arrival
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['aDate']));

                // Extra
                if (!empty($m['cabin'])) {
                    $s->extra()
                        ->cabin(trim(preg_replace("#" . $this->preg_implode($this->t("Cabin")) . "#", '', $m['cabin'])));
                }
            }
        }

        if ($nodes->length > 0) {
            return $email;
        }
        $xpath = "//text()[" . $this->contains($this->t("Destination")) . "]/ancestor::tr[1][" . $this->contains($this->t("Date")) . "]";
        $nodes = $this->http->XPath->query($xpath);
        // Type 2 - tables
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::tr[normalize-space()][1]/td[normalize-space()][last()]", $root, true, "#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}#"))
                ->number($this->http->FindSingleNode("./preceding::tr[normalize-space()][1]/td[normalize-space()][last()]", $root, true, "#^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#"));

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][1]", $root)));

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][3]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][4]", $root)));
        }

        if ($nodes->length > 0) {
            return $email;
        }
        $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            // September, 29th, 2018/ 01:52
            "#^\s*([[:alpha:]]{2,}),\s*(\d{1,2})\s*(?:\w{2})?,\s*(\d{4})[\s\/]+(\d{1,2}:\d{2})\s*$#",
            // 10 de agosto de 2018/ 19:56
            "#^\s*(\d{1,2})\s+de\s+([-[:alpha:]]{2,})\s+de\s+(\d{4})[\s\/]+(\d{1,2}:\d{2})\s*$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
