<?php

namespace AwardWallet\Engine\cover\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "cover/it-657175875.eml, cover/it-657874114.eml";
    public $subjects = [
        'Booking confirmation at',
        'Prenotazione modificata presso',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['For any questions or changes, please contact us'],
        'it' => ['Per qualsiasi dubbio o modifica, contattaci'],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "it" => [
            'For any questions or changes, please contact us' => ['Per qualsiasi dubbio o modifica, contattaci'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@covermanager.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//img[contains(@src, 'covermanager.com')]")->length > 0
        || $this->http->XPath->query("//input[contains(@src, 'covermanager.com')]")->length > 0) {
            return $this->http->XPath->query("//img[contains(@alt, 'ios')]")->length > 0
                && $this->http->XPath->query("//img[contains(@alt, 'android')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]covermanager\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_RESTAURANT);

        $e->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(translate(., '0123456789', 'dddddddddd'), 'd') and contains(normalize-space(), 'person')]/preceding::text()[normalize-space()][2]"));

        $e->setGuestCount($this->http->FindSingleNode("//text()[starts-with(translate(., '0123456789', 'dddddddddd'), 'd') and contains(normalize-space(), 'person')]", null, true, "/^(\d+)\s*{$this->opt('person')}/"));

        $dateStart = $this->http->FindSingleNode("//text()[starts-with(translate(., '0123456789', 'dddddddddd'), 'd') and contains(normalize-space(), 'person')]/preceding::text()[normalize-space()][1]");
        $dateStart = preg_replace('/\s+\|\s+/', ', ', $dateStart);
        $dateStart = preg_replace('/h$/', '', $dateStart);
        $e->setStartDate($this->normalizeDate($dateStart))
            ->setNoEndDate(true);

        $e->setName($this->http->FindSingleNode("//text()[{$this->contains($this->t('For any questions or changes, please contact us'))}]/following::text()[string-length()>5][1]"));
        $e->setAddress($this->http->FindSingleNode("//text()[{$this->contains($this->t('For any questions or changes, please contact us'))}]/following::text()[string-length()>5][2]"));
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function assignLang()
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // mercredi, 8 mars 2023
            "/^\s*[-[:alpha:]]+\s*,\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
