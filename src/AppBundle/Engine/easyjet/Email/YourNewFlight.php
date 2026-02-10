<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourNewFlight extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-393058105.eml, easyjet/it-395098177.eml, easyjet/it-401962368-pt.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'depDate'       => ['Nova data de partida'],
            'flightNumber'  => ['Número do voo novo'],
            'flightsHeader' => ['Novos dados de voo'],
            'confNumber'    => ['A tua reserva:', 'A tua reserva :'],
            // 'confNumberV2' => '',
            'Dear'               => 'Caro(a)',
            'New Route'          => 'Nova Rota',
            'to'                 => 'para',
            'New Departure Time' => 'Nova hora de partida',
            'New Arrival Time'   => 'Nova hora de chegada',
        ],
        'en' => [
            'depDate'       => ['New Departure Date'],
            'flightNumber'  => ['New Flight number'],
            'flightsHeader' => ['New TAP Portugal Flight Details', 'New Flight Details'],
            'confNumber'    => ['Your Booking:', 'Your Booking :'],
            'confNumberV2'  => ['Booking:', 'Booking :'],
        ],
    ];

    private $subjects = [
        'en' => ['Your new flight details'], // .. and pt
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"easyJet Airline Company Limited,") or normalize-space()="easyJet Customer Services" or normalize-space()="Serviço de Apoio ao Cliente da easyJet"]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourNewFlight' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/,\s*({$this->opt($this->t('confNumberV2'))})[:\s]*([A-Z\d]{5,})$/", $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('flightsHeader'))}]"), $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $travellerNames = array_filter($this->http->FindNodes("//p[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $segments = $this->http->XPath->query("//tr[{$this->starts($this->t('depDate'))}]/following-sibling::tr[{$this->starts($this->t('flightNumber'))}][not(following::tr[{$this->starts($this->t('flightsHeader'))}])]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $route = $this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][2]', $root, true, "/^{$this->opt($this->t('New Route'))}\s*[-–]+\s*(.{7,})$/");

            if (preg_match("/^([A-Z]{3})\s*{$this->opt($this->t('to'))}\s*([A-Z]{3})$/", $route, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $date = strtotime($this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][1]', $root, true, "/^{$this->opt($this->t('depDate'))}\s*[-–]+\s*(.*\d.*)$/"));

            $s->airline()
                ->number($this->http->FindSingleNode('.', $root, true, "/^{$this->opt($this->t('flightNumber'))}\s*[-–]+\s*(\d+)$/"))
                ->noName()
                // ->name('U2') // hard-code easyJet
            ;

            $timeDep = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]', $root, true, "/^{$this->opt($this->t('New Departure Time'))}\s*[-–]+\s*({$patterns['time']})$/");
            $timeArr = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][2]', $root, true, "/^{$this->opt($this->t('New Arrival Time'))}\s*[-–]+\s*({$patterns['time']})$/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['depDate']) || empty($phrases['flightNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['depDate'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['flightNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
