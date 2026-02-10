<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckInNow extends \TAccountChecker
{
    public $mailFiles = "azul/it-73341959.eml, azul/it-73596556.eml";
    public $subjects = [
        '/^Faça agora seu check-in$/',
    ];

    public $lang = 'pt';
    public $nextDay = false;

    public static $dictionary = [
        "pt" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news-voeazul.com.br') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Azul')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Resolva os últimos detalhes da viagem antes de chegar ao aeroporto'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('baixe o aplicativo da Azul e tenha uma experiência completa'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\-voeazul\.com\.br$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Código de reserva:')]", null, true, "/{$this->opt($this->t('Código de reserva:'))}\s*([A-Z\d]+)$/u"),
                'Código de reserva')
            ->travellers($this->http->FindNodes("//img[contains(@alt, 'Marcar assento') or contains(@alt, 'Alterar assento')]/ancestor::tr[1]/descendant::td[normalize-space()][1]"), true);

        $s = $f->addSegment();

        $s->airline()
            ->noName()
            ->number($this->http->FindSingleNode("//text()[normalize-space()='Voo:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/u"));

        $date = str_replace('/', '.', $this->http->FindSingleNode("//text()[normalize-space()='Data:']/following::text()[normalize-space()][1]"));

        $timeDep = $this->http->FindSingleNode("//text()[normalize-space()='Saída:']/following::text()[normalize-space()][1]");
        $timeArr = $this->http->FindSingleNode("//text()[normalize-space()='Chegada:']/following::text()[normalize-space()][1]");

        if (preg_match("/\(\+\d+\s*\w+\)/", $timeArr)) {
            $this->nextDay = true;
            $timeArr = $this->re("/(.+)\s*\(/", $timeArr);
        }

        $s->departure()
            ->noCode()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='De:']/following::text()[normalize-space()][1]"))
            ->date(strtotime($date . ', ' . $timeDep));

        $s->arrival()
            ->noCode()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Para:']/following::text()[normalize-space()][1]"));

        if ($this->nextDay == true) {
            $s->arrival()
                ->date(strtotime('+1 day', strtotime($date . ', ' . $timeArr)));
        } else {
            $s->arrival()
                ->date(strtotime($date . ', ' . $timeArr));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

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
