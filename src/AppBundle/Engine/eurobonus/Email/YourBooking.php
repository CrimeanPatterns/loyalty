<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-184806167.eml";
    public $subjects = [
        'Baggage receipt(s) for your booking',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sas.se') !== false) {
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
        if ($this->http->XPath->query("//img[contains(@alt, 'SAS LABS')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Thanks for checking in your baggage'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Bag delayed?'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sas\.se$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) === true) {
            $f = $email->add()->flight();

            if (preg_match("/{$this->opt($this->t('for your booking'))}\s*([A-Z\d]{6})\s*$/", $parser->getSubject(), $m)) {
                $f->general()
                    ->confirmation($m[1])
                    ->travellers(array_unique(array_filter($this->http->FindNodes("//img[contains(@alt, '→')]/ancestor::tr[1]/ancestor::table[2]/following::tr/descendant::table[last()]/descendant::tr[1]/td[1]", null, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u"))), true);
            }

            $nodes = $this->http->XPath->query("//img[contains(@alt, '→')]/ancestor::tr[1]");

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $flight = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root);

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                // 04 Jan, 18:55
                $patterns = "/^(?<date>.{3,}?)\s*,\s*(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/u";

                $dateDep = $timeDep = null;
                $dateDepVal = $this->http->FindSingleNode("ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][2]", $root);

                if (preg_match($patterns, $dateDepVal, $m)) {
                    $dateDep = EmailDateHelper::calculateDateRelative($m['date'], $this, $parser, '%D% %Y%');
                    $timeDep = $m['time'];
                }

                $s->departure()
                    ->code($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z]{3})$/"))
                    ->date(strtotime($timeDep, $dateDep))
                ;

                $dateArr = $timeArr = null;
                $dateArrVal = $this->http->FindSingleNode("ancestor::tr[1]/descendant::td[last()]/ancestor::td[1]/descendant::text()[normalize-space()][2]", $root);

                if (preg_match($patterns, $dateArrVal, $m)) {
                    $dateArr = EmailDateHelper::calculateDateRelative($m['date'], $this, $parser, '%D% %Y%');
                    $timeArr = $m['time'];
                }

                $s->arrival()
                    ->code($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[last()]/ancestor::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z]{3})$/"))
                    ->date(strtotime($timeArr, $dateArr))
                ;
            }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
