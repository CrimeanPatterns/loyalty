<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers aeroplan/FlightCancelled (in favor of aeroplan/FlightCancelled)

class HasBeenCancelled extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-30518010.eml";
    public static $dictionary = [
        "en" => [
            //            'Booking Reference:' => '',
            //            'Your booking has been cancelled' => '',
            //            'Ticket Number' => '',
        ],
        "fr" => [
            'Booking Reference:'              => 'Numéro de réservation:',
            'Your booking has been cancelled' => 'Votre réservation a été annulée',
            'Ticket Number'                   => 'Numéro de billet:',
        ],
    ];

    private $detectFrom = "@aircanada.";
    private $detectSubject = [
        "en" => ["Your booking has been cancelled"],
        "fr" => ["Votre réservation a été annulée"],
    ];
    private $detectCompany = 'Air Canada';
    private $detectBody = [
        "en" => "Your booking has been cancelled",
        "fr" => "Votre réservation a été annulée",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) === false
                && stripos($headers['subject'], 'Costco Travel') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextText($this->t("Booking Reference:")), trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Reference:")) . "]"), ':'))
            ->travellers($this->http->FindNodes("//text()[" . $this->starts($this->t("Ticket Number")) . "]/preceding::text()[normalize-space()][1]"))
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Your booking has been cancelled")) . "])[1]"))) {
            $f->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Ticket Number")) . "]", null, "#" . $this->opt($this->t("Ticket Number")) . "\s*(\d{7,})\b#"));

        if (empty($tickets)) {
            $tickets = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket Number")) . "]/following::text()[normalize-space()][1]", null, "#^\s*(\d{7,})\b#"));
        }

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        return $email;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
