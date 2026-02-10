<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class CancelledInformation extends \TAccountChecker
{
    public $mailFiles = "british/it-56617245.eml, british/it-56656728.eml, british/it-58460052.eml, british/it-58547866.eml, british/it-85833125.eml, british/it-96699512.eml";
    private $lang = '';
    private $reFrom = [
        '@messages.ba.com',
    ];
    private $reProvider = ['British Airways'];
    private $detectLang = [
        'en' => [
            'has been cancelled. We apologise for the inconvenience this has caused.',
            'have been cancelled. We apologise for the inconvenience this has caused',
            'has changed. We apologise for the inconvenience this has caused',
        ],
        'de' => [
            'Leider müssen wir Ihnen mitteilen, dass die folgenden Flüge in Ihrer Buchung storniert wurden',
            'geändert haben. Wir bitten die Unannehmlichkeiten zu entschuldigen',
        ],
        'pt' => [
            'foi cancelado. Pedimos desculpa pelo inconveniente causado',
        ],
        'es' => [
            'Lamentamos informarle de que se ha cancelado su vuelo',
        ],
    ];
    private $reSubject = [
        ' has been cancelled',
        ' have changed',
        'Wichtige Informationen zu Ihrer Buchung',
        // pt
        ' foi cancelado',
        // es
        'Se ha cancelado su vuelo',
    ];
    private static $dictionary = [
        'en' => [
            'cancelledDetect' => [
                'has been cancelled. We apologise for the inconvenience this has caused.',
                'have been cancelled. We apologise for the inconvenience this has caused.',
                'has been cancelled. We apologise for the inconvenience this has caused.',
            ],
            //            'Booking Reference:' => '',
            //            'Passengers' => '',
            //            'Your old flight details' => '',
            //            'Your new flight details' => '',
            //            'DEPARTS' => '',
            //            'ARRIVES' => '',
        ],
        'de' => [
            'cancelledDetect' => [
                'Buchung storniert wurden. Wir bitten die Unannehmlichkeiten zu entschuldigen.',
            ],
            'Booking Reference:'      => 'Buchungsreferenz:',
            'Passengers'              => 'Fluggäste',
            'Your old flight details' => 'Ihre alten Flugdaten',
            'Your new flight details' => 'Ihre neuen Flugdaten',
            'DEPARTS'                 => 'Abflug',
            'ARRIVES'                 => 'Ankunft',
        ],
        'pt' => [
            'cancelledDetect' => [
                'foi cancelado. Pedimos desculpa pelo inconveniente causado.',
            ],
            'Booking Reference:'      => 'Referência da reserva:',
            'Passengers'              => 'Passageiros',
            'Your old flight details' => 'Detalhes do voo anterior',
//            'Your new flight details' => '',
            'DEPARTS'                 => 'Partida',
            'ARRIVES'                 => 'Chegada',
        ],
        'es' => [
            'cancelledDetect' => [
                'Lamentamos informarle de que se ha cancelado su vuelo ',
            ],
            'Booking Reference:'      => 'Referencia de reserva:',
            'Passengers'              => 'Pasajeros',
            'Your old flight details' => 'Detalles de su antiguo vuelo',
//            'Your new flight details' => '',
            'DEPARTS'                 => 'Salida',
            'ARRIVES'                 => 'Llegada',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $f = $email->add()->flight();
        $f->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]", null, false,
                "/[:\s]+([A-Z\d]{6})$/"),
            $this->t('Booking Reference:')
        );


        $f->general()->travellers( preg_replace("/^\s*(MR|MRS|MS|MISS|DR|MSTR|FRAU|HERR|SRTA|SR|SRT) /", '',
            $this->http->FindNodes("//tr[{$this->eq($this->t('Passengers'))}]/following-sibling::tr/td[1]")), true);

        $xpathOld = "//text()[{$this->contains($this->t('Your old flight details'))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t('DEPARTS'))}]/ancestor::table[2]";
        $xpathNew = "//text()[{$this->contains($this->t('Your new flight details'))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t('DEPARTS'))}]/ancestor::table[2]";

        $countXpathOld = $this->http->XPath->query($xpathOld)->length;
        $countXpathNew = $this->http->XPath->query($xpathNew)->length;

        if ($countXpathOld > 0 && $countXpathNew === 0) {
            $xpath = $xpathOld;
        }

        if ($countXpathOld > 0 && $countXpathNew > 0) {
            $xpath = $xpathNew;
        }

        if ($countXpathOld === 0) {
            $this->logger->error("Check layout");

            return $email;
        }

        $this->logger->debug("[XPATH]: {$xpath}");

        foreach ($this->http->XPath->query($xpathOld) as $node) {
            $this->parseSegment($f, $node, true);
        }

        foreach ($this->http->XPath->query($xpathNew) as $node) {
            $this->parseSegment($f, $node);
        }

        $f->general()->status($this->t('changed'));

//        if (
//            $this->http->XPath->query("//text()[{$this->contains($this->t('cancelledDetect'))}]")->length > 0
//            && $this->http->XPath->query("//tr[{$this->contains($this->t('Your new flight details'))}]")->length === 0
//            && $this->http->XPath->query("//p[{$this->contains($this->t('DEPARTS'))}]/ancestor::td[1]")->length >= 1) {
//            $f->general()->status($this->t('cancelled'));
//            $f->general()->cancelled();
//        }
//
//        if (
//            $this->http->XPath->query("//tr[{$this->contains($this->t('Your old flight details'))}]")->length >= 1
//            && $this->http->XPath->query("//tr[{$this->contains($this->t('Your new flight details'))}]")->length >= 1
//            && $this->http->XPath->query("//p[{$this->contains($this->t('DEPARTS'))}]/ancestor::td[1]")->length >= 1) {
//            $f->general()->status($this->t('changed'));
//        }
//
//        if (
//            $this->http->XPath->query("//text()[{$this->contains($this->t('cancelledDetect'))}]")->length > 0
//            && $this->http->XPath->query("//tr[{$this->contains($this->t('Your old flight details'))}]")->length >= 1
//            && $this->http->XPath->query("//tr[{$this->contains($this->t('Your new flight details'))}]")->length === 0
//            && $this->http->XPath->query("//text()[{$this->contains($this->t('DEPARTS'))}]")->length >= 1) {
//            $f->general()->status($this->t('cancelled'));
//        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseSegment(Flight $f, $node, $cancelled = false)
    {
        $s = $f->addSegment();
        $airline = $this->http->FindSingleNode(".//img[contains(@src,'flight-icon-')]/ancestor::td[1]/following-sibling::td[1]",
            $node);

        if (preg_match('/([A-Z\d]{2})\s*(\d{1,5})/', $airline, $m)) {
            $s->airline()->name($m[1]);
            $s->airline()->number($m[2]);
        }
        // DEPARTS 18 Sep 2020 08:25 London Heathrow
        $str = $this->http->FindSingleNode(".//p[{$this->contains($this->t('DEPARTS'))}]/ancestor::td[1]", $node);

        if (empty($str)) {
            $str = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTS'))}]/ancestor::td[1]", $node);
        }

        if (preg_match('/(\d+.+?:\d+)\s*([[:alpha:]\s.,\-]+)/', $str, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]))
                ->strict()
            ;
            $s->departure()->name($m[2]);
            $s->departure()->noCode();
        }
        $str = $this->http->FindSingleNode(".//p[{$this->contains($this->t('ARRIVES'))}]/ancestor::td[1]", $node);

        if (empty($str)) {
            $str = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVES'))}]/ancestor::td[1]", $node);
        }

        if (preg_match('/(\d+.+?:\d+)(?:\s*\+1)?\s*([[:alpha:]\s.,\-]+)/', $str, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]))
                ->strict()
            ;
            $s->arrival()->name($m[2]);
            $s->arrival()->noCode();
        }
        $s->departure()->terminal($this->http->FindSingleNode(".//p[{$this->contains($this->t('DEPARTS'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $node, false, '/Terminal (\w+)/'), false, true);
        $s->arrival()->terminal($this->http->FindSingleNode(".//p[{$this->contains($this->t('DEPARTS'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $node, false, '/Terminal (\w+)/'), false, true);

        if ($cancelled == true) {
            $s->extra()
                ->cancelled();
        }

    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
//            "#^([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)$#", //Sep 8, 2017, 15:00
        ];
        $out = [
//            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
