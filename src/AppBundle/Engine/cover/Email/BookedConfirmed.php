<?php

namespace AwardWallet\Engine\cover\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookedConfirmed extends \TAccountChecker
{
    public $mailFiles = "cover/it-333149171.eml, cover/it-388682271.eml, cover/it-657335417.eml, cover/it-657456043.eml";
    public $subjects = [
        'Booking Confirmed at ',
        'Reserva modificada en',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["You can cancel your reservation at any time clicking this link", "We are pleased to confirm your reservation:", "It is our pleasure to inform you that your table is booked."],
        "es" => ["Hora de la reserva"],
    ];

    public static $dictionary = [
        "en" => [
            '- Number of people:' => ['- Number of people:', '- Number of guests:'],
        ],
        "es" => [
            '- Date:'             => 'Día de la reserva:',
            '- Time:'             => 'Hora de la reserva:',
            '- Number of people:' => 'Nº de personas:',
            'informs:'            => 'le informa:',
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
            return $this->http->XPath->query("//p[{$this->contains($this->t('- Number of people:'))}]")->length > 0
                && $this->http->XPath->query("//p[{$this->contains($this->t('- Date:'))}]")->length > 0
                && $this->http->XPath->query("//p[{$this->contains($this->t('- Time:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@covermanager.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $address = '';

        $this->detectLang();

        $e = $email->add()->event();

        $e->general()
            ->noConfirmation();

        $e->setEventType(Event::TYPE_RESTAURANT);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)/");

        if (empty($traveller)) {
            $traveller = implode(" ", $this->http->FindNodes("//text()[{$this->contains($this->t('Name:'))} or {$this->contains($this->t('Last Name:'))}]", null, "/(?:{$this->opt($this->t('Name\:'))}|{$this->opt($this->t('Last Name\:'))})\s*(.+)/"));
        }

        if (empty($traveller)) {
            $traveller = implode(" ", $this->http->FindNodes("//text()[{$this->contains($this->t('los cambios para su reserva:'))}]/ancestor::p[1]", null, "/^([\.[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]),/"));
        }

        if (!empty($traveller)) {
            $e->general()
                ->traveller(preg_replace("#(?:Mr./Mrs.|Mr\.|Mrs\.|Ms\.)#", "", trim($traveller, ',')));
        }

        $e->general()
            ->noConfirmation();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/preceding::text()[string-length()>2]", null, true, "/\s([A-z\s\D\d]{3,})/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We confirm your booking:'))}]/preceding::text()[{$this->starts($this->t('Restaurant'))}]", null, true, "/{$this->opt($this->t('Restaurant'))}\s*(.+)/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you very much for making your reservation at'))}]/ancestor::p[1]", null, true, "/\s*{$this->opt($this->t('Thank you very much for making your reservation at'))}\s*(.+)\s*(\-)/u");
        }

        if (empty($name)) {
            $name = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('informs:'))}]/ancestor::p[1]", null, true, "/(.+)\s*{$this->opt($this->t('informs:'))}/u"));
            $address = $this->http->FindSingleNode("//text()[{$this->contains($name . ' -')}]", null, true, "/{$this->opt($name)}[\s\-]*(.+)/");
        }

        if (empty($name)) {
            $name = trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Best regards,'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Restaurant'))}\s*(.+)/u"));
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Please find below the restaurant address:'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Akelarre'))}]/following::text()[normalize-space()][1]");
        }

        $e->setName($name);

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($e->getName())}]/following::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('- Location:'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('- Location:'))}\s*(.+)/");
        }
        $e->setAddress($address);

        $date = str_replace('/', '.', $this->http->FindSingleNode("//text()[{$this->eq($this->t('- Date:'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\/]+)/"));

        if (empty($date)) {
            $date = str_replace('/', '.', $this->http->FindSingleNode("//text()[{$this->starts($this->t('- Date:'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('- Date:'))}\s*([\d\/]+)/"));
        }

        if (empty($date)) {
            $date = str_replace('/', '.', $this->http->FindSingleNode("//text()[{$this->starts($this->t('- Date:'))}]", null, true, "/{$this->opt($this->t('- Date:'))}\s*([\d\/]+)/"));
        }

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('- Time:'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\:]+)/");

        if (empty($time)) {
            $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('- Time:'))}]", null, true, "/{$this->opt($this->t('- Time:'))}\s*([\d\:]+)/");
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('- Number of people:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('- Number of people:'))}]", null, true, "/{$this->opt($this->t('- Number of people:'))}\s*(\d+)/u");
        }

        $e->booked()
            ->guests($guests)
            ->start(strtotime($date . ', ' . $time))
            ->noEnd();

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
