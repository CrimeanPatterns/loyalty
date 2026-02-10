<?php

namespace AwardWallet\Engine\esky\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "esky/it-16216709.eml, esky/it-18950623.eml, esky/it-20629471.eml, esky/it-8781348.eml, esky/it-8781369.eml, esky/it-8986362.eml";

    public static $dictionary = [
        "pl" => [
            "Numery rezerwacji:" => ["Numery rezerwacji:", "Numery rezerwacji", "Numer rezerwacji"],
            "Lot:"               => ["Lot:", "| Lot:"],
            //			"Numer biletu" => "",
            //			"Kwota całkowita:" => "",
            //			"Wylot:" => "",
            "Lot:"              => ["Lot:", "| Lot:"],
            "| Linia lotnicza:" => ["| Linia lotnicza:", "| Linia lotnicza"],
            //			"Terminal" => "",
            "| Linia obsługująca lot:" => ["| Linia obsługująca lot:", "| Linia obsługująca lot"],
            "| Klasa:"                 => ["| Klasa:", "| Klasa"],
            //			"| Czas lotu:" => "",
            //			"| Międzylądowanie:" => "",
            //			"W przypadku pyta lub wtpliwoci" => "",
        ],
        "pt" => [
            "Numery rezerwacji:" => ["N° da reserva"],
            "Numer biletu"       => "Código do bilhete",
            //			"Kwota całkowita:" => "",
            "Wylot:"                   => "Origem:",
            "Lot:"                     => ["N° do voo:", "| N° do voo:"],
            "| Linia lotnicza:"        => ["| Companhia aérea:", " | Companhia aérea"],
            "Terminal"                 => "Terminal",
            "| Linia obsługująca lot:" => ["| Operado por:"],
            "| Klasa:"                 => ["| Classe:", "| Classe"],
            "| Czas lotu:"             => ["| O tempo de voo:", "| Duração do voo:"],
            //			"| Międzylądowanie:" => "",
            "W przypadku pyta lub wtpliwoci" => "Em caso de dúvidas",
        ],
        "es" => [
            "Numery rezerwacji:" => ["Reserva:"],
            //			"Numer biletu" => "",
            "Kwota całkowita:"         => "Valor total",
            "Wylot:"                   => "Salida",
            "Lot:"                     => ["Vuelo nro.:", "| Vuelo nro.:"],
            "| Linia lotnicza:"        => ["| Aerolínea:", " | Aerolínea:"],
            "Terminal"                 => "Terminal", // ??
            "| Linia obsługująca lot:" => ["| Operado por:"],
            "| Klasa:"                 => ["| Clase:"],
            "| Czas lotu:"             => ["| Tiempo de vuelo:"],
            //			"| Międzylądowanie:" => "",
            "W przypadku pyta lub wtpliwoci" => "En caso de dudas, por favor llámanos",
        ],
        "ro" => [
            "Numery rezerwacji:" => ["Numerele de rezervare"],
            //			"Numer biletu" => "",
            "Kwota całkowita:"  => "Sumă:",
            "Wylot:"            => "Plecare:",
            "Lot:"              => ["Nr. zborului:", "| Nr. zborului:"],
            "| Linia lotnicza:" => ["| Compania aeriană:"],
            "Terminal"          => "Terminal", // ??
            //			"| Linia obsługująca lot:" => [""],
            "| Klasa:"     => ["| Clasa:"],
            "| Czas lotu:" => ["| Timpul de zbor:"],
            //			"| Międzylądowanie:" => "",
            "W przypadku pyta lub wtpliwoci" => "Serviciul Clienţi vă stă la dispoziţie la",
        ],
    ];

    private $detectFrom = [
        "esky"      => "@esky.",
        "edestinos" => "edestinos.",
        "lucky"     => "lucky2go.",
    ];
    private $detectSubject = [
        "pl"  => "Potwierdzenie rezerwacji lotu nr",
        "pt"  => "Seu bilhete eletrônico para a reserva n",
        "es"  => "Confirmación de reserva de vuelo N°",
        "es2" => "Tiquete electrónico de la reserva",
        "ro"  => "Pentru rezervarea cu nr.",
    ];
    private $detectCompany = [
        "esky"      => "eSky",
        "edestinos" => "eDestinos",
        "lucky"     => "lucky2go",
    ];
    private $detectBody = [
        "pl" => "Wylot:",
        "pt" => "Origem:",
        "es" => "Llegada",
        "ro" => "Plecare:",
    ];

    private $lang = "en";
    private $provider;

    public static function getEmailProviders()
    {
        return ["esky", "edestinos", "lucky"];
    }

    public function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->nextText($this->t("Numery rezerwacji:")));

        $nodes = $this->http->XPath->query("//span[contains(@class, 'qa-first-name')]");

        foreach ($nodes as $root) {
            $travellers[] = $this->http->FindSingleNode(".", $root) . ' ' . $this->http->FindSingleNode("./following-sibling::span[1]", $root);
            $tickets[] = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->contains($this->t("Numer biletu"))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\s*([\d\- ]{9,})\s*$#");
        }

        if (!empty($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        if (isset($tickets) && !empty(array_filter($tickets))) {
            $f->issued()->tickets(array_values(array_unique(array_filter($tickets))), false);
        }

        // Price
        $totalCharge = $this->amount($this->nextText($this->t("Kwota całkowita:")));
        $currency = $this->currency($this->nextText($this->t("Kwota całkowita:")));

        if (!empty($totalCharge) && !empty($currency)) {
            $email->price()->total($totalCharge);
            $email->price()->currency($currency);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Wylot:")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->nextText($this->t("Lot:"), $root);

            if (preg_match("#^\d+$#", $node)) {
                $s->airline()
                    ->name($this->nextText($this->t("| Linia lotnicza:"), $root))
                    ->number($node);
            } elseif (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $s->airline()->operator($this->nextText($this->t("| Linia obsługująca lot:"), $root), true, true);

            // Daparture
            $s->departure()
                ->code($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[normalize-space(.)!=''][3]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[normalize-space(.)!=''][3]", $root, true, "#(.*?),\s+\([A-Z]{3}\)#"))
                ->terminal($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[" . $this->contains("Terminal") . "]", $root, true, "#Terminal:? (.+)#"), true, true)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[normalize-space(.)!=''][2]", $root))));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)!=''][3]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)!=''][3]", $root, true, "#(.*?),\s+\([A-Z]{3}\)#"))
                ->terminal($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[" . $this->contains("Terminal") . "]", $root, true, "#Terminal:? (.+)#"), true, true)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)!=''][2]", $root))));

            // Extra
            $s->extra()
                ->aircraft($this->http->FindSingleNode("./td[5]", $root), true, true)
                ->cabin($this->nextText($this->t("| Klasa:"), $root))
                ->duration($this->nextText($this->t("| Czas lotu:"), $root))
                ->stops($this->nextText($this->t("| Międzylądowanie:"), $root), true, true);
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        // Provider
        if (!empty($this->provider)) {
            $codeProvider = $this->provider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        }
        $phone = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("W przypadku pyta lub wtpliwoci")) . "])[1]/following::text()[normalize-space(.)!=''][1]");

        if (strlen($phone) < 25 && strlen(preg_replace("#[^\d]+#", '', $phone)) > 7) {
            $email->ota()->phone($phone);
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                $this->provider = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
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
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $code => $dCompany) {
            if (strpos($body, $dCompany) !== false) {
                $finded = true;
                $this->provider = $code;

                break;
            }
        }

        if (!$finded) {
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

    private function getProvider()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectCompany as $code => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($body, $dCompany) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+:\d+)\s*,\s*(\d+ [^\s\d]+?)\.? (\d{4}) \(.*\)$#", //06:25 , 10 paź 2017 (wt.)
        ];
        $out = [
            "$2 $3, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
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
}
