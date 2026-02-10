<?php

namespace AwardWallet\Engine\bahn\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "bahn/it-38994941.eml, bahn/it-48122254.eml, bahn/it-61596485.eml";

    private $from = "@bahn";

    private $subject = [
        "Stornierungsbestätigung (Auftrag",
        "Confirmation of cancellation (order",
    ];

    private $body = 'Bahn';

    private static $detectors = [
        "de"=> ["Übersicht der Stornierungsdaten:", "hiermit bestätigen wir die Stornierung Ihres Online-Tickets bei bahn.de"],
        "en"=> ["Overview of cancellation data:", "We hereby confirm the cancellation of your online ticket on bahn.com."],
    ];

    private static $dictionary = [
        "de" => [
            "Order number:"                                                         => ["Auftragsnummer:"],
            "We hereby confirm"                                                     => ["hiermit bestätigen wir"],
            "Dear"                                                                  => ["Sehr geehrter"],
            "Value of cancelled services:"                                          => ["Wert der stornierten Leistungen:"],
            "We hereby confirm the cancellation of your online ticket on bahn.com."	=> ["hiermit bestätigen wir die Stornierung Ihres Online-Tickets bei bahn.de"],
        ],
        "en" => [
            "Order number:"                                                         => ["Order number:"],
            "We hereby confirm"                                                     => ["We hereby confirm"],
            "Dear"                                                                  => ["Dear"],
            "Value of cancelled services:"                                          => ["Value of cancelled services:"],
            "We hereby confirm the cancellation of your online ticket on bahn.com."	=> ["We hereby confirm the cancellation of your online ticket on bahn.com."],
        ],
    ];

    private $lang;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->train();

        //Confirmation
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Order number:')) . "]");

        if (!empty($conf)) {
            if (preg_match("#(.+):\s+(.+)#", $conf, $m)) {
                $r->general()
                    ->confirmation($m[2], $m[1], true);
            }
        }

        //Cancelled & status
        if ($this->http->XPath->query("//*[{$this->contains($this->t("We hereby confirm the cancellation of your online ticket on bahn.com."))}]")->length > 0) {
            $r->general()
                ->cancelled()
                ->status('cancelled');
        }

        // Passenger
        $passenger = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "]", null, true, "#" . $this->opt($this->t("Dear")) . "\s+\w+\.?\s+(.+),#");

        if (!empty($passenger)) {
            $r->general()->traveller($passenger, false);
        }

        // TotalCharge
        $totalCharge = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Value of cancelled services:")) . "]"));

        if (!empty($totalCharge)) {
            $r->price()
                    ->total($totalCharge);
        }

        // Currency
        $currency = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Value of cancelled services:")) . "]", null, true, "#\d[\s]([A-Z]{3})#");

        if (!empty($currency)) {
            $r->price()
                    ->currency($currency);
        }

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Order number:"], $words["We hereby confirm"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Order number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['We hereby confirm'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
