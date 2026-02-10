<?php

namespace AwardWallet\Engine\flixbus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ticket extends \TAccountChecker
{
    public $mailFiles = "flixbus/it-78721941.eml";
    public $subjects = [
        '/erwartet Dich! Hier ist Dein E-Ticket und wichtige Infos/',
    ];

    public $lang = '';

    public static $dictionary = [
        "de" => [
            'Confirmation Number:' => ['Buchungsnummer:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.flixbus.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".flixbus.com/") or contains(@href,"email.flixbus.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Copyright ©")][contains(normalize-space(),"FlixMobility") or contains(normalize-space(),"Flix SE")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//tr/*[starts-with(translate(normalize-space(),'0123456789 ',''),'/:')]")->length > 1;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.flixbus\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'de';

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $b = $email->add()->bus();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]");

        if (preg_match("/^({$this->opt($this->t('Confirmation Number:'))})[:\s]*(\d{5,})$/", $confirmation, $m)) {
            $b->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $xpath = "//text()[{$this->starts($this->t('Confirmation Number:'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $b->addSegment();

            $info = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^\d{1,2}\/\d{1,2}\/(?<year>\d{4})\n(?<depDay>\d{1,2})\/(?<depMonth>\d{1,2})\s+(?<depTime>{$patterns['time']})\n(?<depName>.{2,})\n.+\n(?<number>[A-z \d]+)\n(?<arrDay>\d{1,2})\/(?<arrMonth>\d{1,2})\s+(?<arrTime>{$patterns['time']})\n(?<arrName>.{2,})/", $info, $m)) {
                $s->departure()
                    ->date(strtotime($m['depDay'] . '.' . $m['depMonth'] . '.' . $m['year'] . ', ' . $m['depTime']))
                    ->name($this->normalizeNameStation($m['depName']));

                $s->setNumber($m['number']);

                $s->arrival()
                    ->date(strtotime($m['arrDay'] . '.' . $m['arrMonth'] . '.' . $m['year'] . ', ' . $m['arrTime']))
                    ->name($this->normalizeNameStation($m['arrName']));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, ''));
        }, $field)) . ')';
    }

    private function normalizeNameStation(?string $s): ?string
    {
        if (preg_match("/^(?<location>[^()]{2,}?)\s*\(\s*(?<tip>[^)(]*?)\s*\)$/", $s, $m)) {
            // Prague (Main Railway Station - Parking)
            $location = $m['location'];
            $tip = $m['tip'];
        } else {
            $location = $tip = null;
        }

        if (preg_match("/^(train|main station replacement)$/i", $tip)) {
            $tip = null;
        }

        if ($location !== null && $tip === null) {
            return $location;
        }

        if ($location !== null && $tip !== null) {
            return $tip . ', ' . $location;
        }

        return $s;
    }
}
