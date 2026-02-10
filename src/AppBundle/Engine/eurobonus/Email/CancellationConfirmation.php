<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationConfirmation extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-56733853.eml, eurobonus/it-56778273.eml, eurobonus/it-56938971.eml, eurobonus/it-56941875.eml, eurobonus/it-57012386.eml";

    public $lang = '';
    public static $dictionary = [
        "en" => [
            //            "Booking reference:" => "",
            "cancelled" => ["Your booking has now been", "You have now"],
        ],
        "no" => [
            "Booking reference:" => "Bestillingsreferanse:",
            "cancelled"          => "Bestillingen din er nå",
        ],
        "sv" => [
            "Booking reference:" => "Bokningsreferens:",
            "cancelled"          => "Din bokning har",
        ],
        "da" => [
            "Booking reference:" => "Bookingreference:",
            "cancelled"          => "Din reservation er nu blevet",
        ],
        "de" => [
            "Booking reference:" => "Buchungsreferenz:",
            "cancelled"          => "Ihre Buchung wurde nun",
        ],
    ];
    private $detectFrom = ["@sas.", "flysas.com"];

    private $detectSubject = [
        "en" => "Cancellation confirmation",
        "Cancellation and refund confirmation",
        "no" => "Cancellation confirmation",
        "sv" => "Cancellation confirmation",
        "da" => "Cancellation confirmation",
        "de" => "Cancellation confirmation",
    ];
    private $detectCompany = "SAS";
    private $detectBody = [
        "en" => ["Your booking has now been cancelled", "You have now cancelled your trip."],
        "no" => ["Bestillingen din er nå avbestilt"],
        "sv" => ["Din bokning har avbokats", "Din bokning har nu avbokats"],
        "da" => ["Din reservation er nu blevet annulleret"],
        "de" => ["Ihre Buchung wurde nun storniert"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (!empty($this->http->FindSingleNode("//text()[".$this->starts(['Scandinavian Airlines', 'SAS Customer Service'])."]"))) {
            $f = $email->add()->flight();

            $descConfirmation = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Booking reference:")) . "])", null, true,
                "#^(\D+)[:].+?$#");
            $confirmation = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Booking reference:")) . "])", null, true,
                "#:\s*([A-Z\d]{5,7})\s*$#");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Booking reference:")) . "]/ancestor::tr[1])", null, true,
                    "#:\s*([a-z\d]{5,7})\s*$#");
            }
            $f->general()
                ->confirmation($confirmation, $descConfirmation);

            if (!empty($status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('cancelled'))}]", null, true, "/{$this->opt($this->t('cancelled'))}\s+(\w+)[.]?/"))) {
                $f->general()
                    ->cancelled()
                    ->status($status)
                    ->cancellationNumber($confirmation);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
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
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
