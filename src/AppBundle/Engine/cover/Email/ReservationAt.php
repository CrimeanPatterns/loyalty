<?php

namespace AwardWallet\Engine\cover\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationAt extends \TAccountChecker
{
    public $mailFiles = "cover/it-318535804.eml, cover/it-318871574.eml";
    public $subjects = [
        'Reconfirm your reservation at',
        //it
        'Prenotazione confermata presso',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Your reservation is"],
        "it" => ["La prenotazione per", "La sua prenotazione"],
    ];

    public static $dictionary = [
        "en" => [
            'startAddressText' => ['Thank you,'],
        ],
        "it" => [
            'startAddressText'       => ['Cordiali saluti'],
            'Your reservation is in' => ['La prenotazione', 'La sua prenotazione'],
            'the name of'            => 'a nome di',
            'people'                 => 'persone',
            'on'                     => 'è stata confermata per',
            'at'                     => 'alle',
            'for'                    => 'per',
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
        $this->detectLang();

        if ($this->detectEmailByHeaders($parser->getHeaders()) == true) {
            return $this->http->XPath->query("//p[{$this->contains($this->t('Your reservation is in'))} and {$this->contains($this->t('people'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@covermanager.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $text = str_replace(['&eacute;', 'nbsp;', '&aacute;', '&'], ' ', $parser->getBody());

        $e = $email->add()->event();

        $e->general()
            ->noConfirmation();

        $e->setEventType(Event::TYPE_RESTAURANT);

        $eventInfo = $this->http->FindSingleNode("//p[{$this->contains($this->t('Your reservation is in'))} and {$this->contains($this->t('people'))}]");
        $this->logger->debug("/^{$this->opt($this->t('Your reservation is in'))}\s*{$this->opt($this->t('for'))}\s*(?<guests>\d+)\s*{$this->opt($this->t('people'))}\s*{$this->opt($this->t('the name of'))}\s*(?<traveller>\D+)\s*{$this->opt($this->t('on'))}\s*(?<dateStart>[\d\/]+)\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+)\D?\.$/");
        $this->logger->debug($eventInfo);

        if (preg_match("/{$this->opt($this->t('Your reservation is in'))}\s*{$this->opt($this->t('the name of'))}\s*(?<traveller>\D+)\s*{$this->opt($this->t('on'))}\s*(?<dateStart>[\d\/]+)\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+)\s*{$this->opt($this->t('for'))}\s*(?<guests>\d+)\s*{$this->opt($this->t('people'))}\.$/", $eventInfo, $m)
         || preg_match("/{$this->opt($this->t('Your reservation is in'))}\s*{$this->opt($this->t('for'))}\s*(?<guests>\d+)\s*{$this->opt($this->t('people'))}\s*{$this->opt($this->t('the name of'))}\s*(?<traveller>\D+)\s*{$this->opt($this->t('on'))}\s*(?<dateStart>[\d\/]+)\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+)\D?\.$/", $eventInfo, $m)) {
            $e->general()
                ->traveller($m['traveller']);

            $e->booked()
                ->start(strtotime(str_replace('/', '.', $m['dateStart']) . ' ' . $m['timeStart']))
                ->noEnd()
                ->guests($m['guests']);
        }

        if (preg_match("/{$this->opt($this->t('startAddressText'))}\n+(?<eventName>.*)\n{2,}(?<address>(?:.*\n){1,2})\n/", $text, $m)) {
            $e->setName(str_replace(['Team'], '', $m['eventName']))
                ->setAddress(str_replace("\n", "", $m['address']));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectLang(): bool
    {
        foreach ($this->detectLang as $lang => $detect) {
            if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
