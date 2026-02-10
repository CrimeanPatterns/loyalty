<?php

namespace AwardWallet\Engine\bahn\Email;

use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class ActualInformation extends \TAccountChecker
{
    public $mailFiles = "bahn/it-47561500.eml";

    private static $detectors = [
        'de' => ['für Ihre gebuchte Verbindung haben sich Abweichungen ergeben.'],
    ];
    private static $dictionary = [
        'de' => [
            'Booked connection'                                => ['Gebuchte Verbindung'],
            'Station/stop'                                     => ['Bahnhof/Haltestelle'],
            'Dear'                                             => ['Sehr geehrter', 'Sehr geehrte'],
            'Current information about your connection (order' => ['Aktuelle Informationen zu Ihrer Verbindung (Auftrag'],
        ],
    ];

    private $from = "@bahn";
    private $body = "Bahn";
    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        if (!self::detectBody()) {
            return false;
        }

        $r = $email->add()->train();

        // Passenger
        $passenger = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "]", null, true, "/" . $this->opt($this->t("Dear")) . "\s+(.+),/");

        if (!empty($passenger)) {
            $r->general()
                ->traveller($passenger, false);
        }

        //Confirmation
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Current information about your connection (order')) . "]");

        if (!empty($conf)) {
            if (preg_match("/\((.+)\s+(.+)\)/", $conf, $m)) {
                $r->general()
                    ->confirmation($m[2], $m[1], true);
            }
        }

        $this->parseSegment($r);

        return $email;
    }

    private function parseSegment(Train $r)
    {
        $s = $r->addSegment();

        $xPath = "//tr[//text()[(starts-with(normalize-space(.),'Bahnhof/Haltestelle'))]]";

        $re = "/(.*)\s(\d{1,2}\.\d{1,2}\.\d{4}).+\s(\d{1,2}:\d{1,2})/";

        $dep = $this->http->FindSingleNode($xPath . "[2]");

        if (!empty($dep)) {
            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->date(strtotime($m[2] . ' ' . $m[3]));
            }
        }

        $arr = $this->http->FindSingleNode($xPath . "[3]");

        if (!empty($arr)) {
            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->date(strtotime($m[2] . ' ' . $m[3]));
            }
        }

        $s->extra()
            ->noNumber();
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
            if (isset($words["Booked connection"], $words["Station/stop"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booked connection'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Station/stop'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
