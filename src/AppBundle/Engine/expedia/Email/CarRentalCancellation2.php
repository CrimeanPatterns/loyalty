<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CarRentalCancellation2 extends \TAccountChecker
{
    public $mailFiles = "expedia/it-58649678.eml";
    public $reSubject = ['/(Cancellation\s+of\s+Travel\s[-]\s\w+\s+\d+\s+[-]\s+Itinerary\s+[#]\d{12,})/'];
    public $reFrom = 'expediamail.com';
    public $langDetectors = [
        'en' => ['Your car reservation has been cancelled'],
        'pt' => ['Sua reserva de carro foi cancelada'],
    ];

    public static $dictionary = [
        "en" => [
            'Cancelled' => 'Your car reservation has been cancelled',
        ],
        "pt" => [
            'Cancelled'    => 'Sua reserva de carro foi cancelada',
            'Reserved for' => 'Reservado para',
            'Pick up'      => 'Retirada',
            'Drop off'     => 'Entrega',
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        if (strpos($from, $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            if (!empty($this->re($phrases, $headers['subject']))) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Chase') or contains(normalize-space(), 'Expedia')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Cancelled'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Pick up'))}]")->count() > 0;
        }

        return false;
    }

    public function parseHtmlCar(Email $email)
    {
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Chase Travel Itinerary Number:'))}]/following::text()[normalize-space()][1]");
        $confDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Chase Travel Itinerary Number:'))}]");

        if (!empty($confirmationNumber)) {
            $email->ota()
                ->confirmation($confirmationNumber, $confDescription);
        }

        $r = $email->add()->rental();

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancelled'))}]"))) {
            $r->general()
                ->status('cancelled')
                ->cancelled()
                ->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reserved for'))}]/following::text()[normalize-space()][1]");

        if (!empty($traveller)) {
            $r->general()->traveller($traveller, true);
        }

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reserved for'))}]/preceding::text()[normalize-space()][2]"))
            ->model($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reserved for'))}]/preceding::text()[normalize-space()][1]"));

        $pickupText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick up'))}]/ancestor::table[1]");

        if (preg_match("/^{$this->opt($this->t('Pick up'))}([\d\:]+a?p?m\w+\s+\d+[,]\s+\d{4})(\D+)$/", $pickupText, $m)
            || preg_match("/^{$this->opt($this->t('Pick up'))}(\d+[h]\d+\s+\w+\,\s+\d{4})(\D+)$/", $pickupText, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m[1]))
                ->location($m[2]);
        }
        $dropOffText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Drop off'))}]/ancestor::table[1]");

        if (preg_match("/^{$this->opt($this->t('Drop off'))}([\d\:]+a?p?m\w+\s+\d+[,]\s+\d{4})(\D+)$/", $dropOffText, $m)
            || preg_match("/^{$this->opt($this->t('Drop off'))}(\d+[h]\d+\s+\w+\,\s+\d{4})(\D+)$/", $dropOffText, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m[1]))
                ->location($m[2]);
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancelled'))}]"))) {
            $this->parseHtmlCar($email);
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

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->error($str);
        $in = [
            "#^([\d\:]+a?p?m)(\w+)\s(\d+)[,]\s+(\d{4})$#", //9:00am May 21, 2020
            "#^(\d+)h(\d{2})(\d+)\s+(\w+)\,\s+(\d{4})$#", //11h0027 dez, 2020
        ];
        $out = [
            "$3 $2 $4, $1",
            "$3 $4 $5, $1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
}
