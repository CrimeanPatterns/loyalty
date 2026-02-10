<?php

namespace AwardWallet\Engine\airfrance\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlyingBlueStatement extends \TAccountChecker
{
    public $mailFiles = "airfrance/statements/it-63029311.eml, airfrance/statements/it-63225088.eml, airfrance/statements/it-63438573.eml, airfrance/statements/it-64620959.eml";
    public $subjects = [
        '/(?:Time to|Only|world|bonus)/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'MR'                 => ['MR', 'MRS', 'MS', 'Dear Mrs', 'Dear Mr'],
            'Your Miles balance' => ['Your Miles balance by', 'Your Miles balance'],
            //            'To learn more about' => '',
            'Log in to my account' => ['Log in to my account', 'LOGIN TO YOUR ACCOUNT'],
        ],
        "fr" => [
            'MR'                   => ['MR', 'MRS', 'MS', 'Cher Monsieur', 'Cher Madame'],
            'Your Miles balance'   => ['Vos Miles-Prime au', 'Vos Miles au'],
            'To learn more about'  => 'Retrouvez tous vos avantages',
            'Log in to my account' => ['Accédez à votre profil', 'ACCÉDEZ À VOTRE ESPACE'],
        ],
        "pt" => [
            'MR'                 => ['MR', 'MRS', 'MS', 'Caro Senhor'],
            'Your Miles balance' => ['As suas Milhas Prémio em', 'Suas Milhas em'],
            //            'To learn more about' => 'Retrouvez tous vos avantages',
            'Log in to my account' => ['ACESSA A SUA CONTA', 'Acessa a sua conta'],
        ],
        "es" => [
            'MR' => ['MR', 'MRS'],
            //            'Your Miles balance' => 'As suas Milhas Prémio em',
            'To learn more about'  => 'Para descubrir todas sus ventajas',
            'Log in to my account' => 'Acceda a su perfil',
        ],
        "de" => [
            'MR' => ['MR', 'MRS'],
            //            'Your Miles balance' => 'As suas Milhas Prémio em',
            'To learn more about'  => 'Lassen Sie sich Ihre',
            'Log in to my account' => ['EINLOGGEN', 'Einloggen'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $parser->getDate();
        $st = $email->add()->statement();

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Log in to my account']) && !empty($this->http->FindSingleNode("//a[" . $this->eq($dict['Log in to my account']) . "]"))) {
                $this->lang = $lang;

                break;
            }

            if (!empty($dict['Your Miles balance']) && !empty($this->http->FindSingleNode("//text()[" . $this->starts($dict['Your Miles balance']) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $blockRow = $this->http->FindNodes("//*[.//img[@alt='Flying Blue' or @class = 't_w100p m_w100p']]/following-sibling::*[1][.//a[" . $this->eq($this->t('Log in to my account')) . "]]/ancestor::*[1]//text()[normalize-space()]");

        if (!empty($blockRow)) {
            if (preg_match("/^\s*(?<status>\w+|Platinum(?: \w+){1,2})\s*\n\s*(?<number>\d{5,})\s*\n\s*{$this->opt($this->t('MR'))}\s+(?<name>[\w ]+)\s*\W?\s*\n\s*{$this->opt($this->t('To learn more about'))}[\s\S]+\n\s*{$this->opt($this->t('Log in to my account'))}\s*$/u", implode("\n", $blockRow), $m)) {
                $st->setNumber($m['number'])
                    ->setLogin($m['number'])
                    ->addProperty('Status', $m['status'])
                    ->addProperty('Name', $m['name'])
                    ->setNoBalance(true)
                ;

                return $email;
            }
        }

        $xpathLeft = "//a[" . $this->starts($this->t("Your Miles balance")) . "]";

        if (empty($this->http->FindSingleNode($xpathLeft))) {
            $xpathLeft = "//td[" . $this->starts($this->t("Your Miles balance")) . "]";
        }

        $st->setBalance($this->http->FindSingleNode($xpathLeft . "/preceding::text()[normalize-space()][1]", null, true, "/^(\d+)\s*{$this->opt($this->t('Miles'))}?/"));
        $emailDate = false;

        if (self::detectEmailFromProvider($parser->getCleanFrom()) == true) {
            $emailDate = strtotime($parser->getDate());
        }
        $st->setBalanceDate($this->normalizeDate($this->http->FindSingleNode($xpathLeft, null, true, "/{$this->opt($this->t('Your Miles balance'))}\s+(.+)/"), $emailDate));

        $userData = $this->http->FindSingleNode($xpathLeft . "/preceding::a[{$this->contains($this->t('MR'))}]/ancestor::table[2]");

        if (empty($userData)) {
            $userData = $this->http->FindSingleNode($xpathLeft . "/preceding::td[{$this->contains($this->t('MR'))}][1]/ancestor::table[2]");
        }

        if (preg_match("/^(?<status>\w+)\s+(?<number>\d+)\s+{$this->opt($this->t('MR'))}\s+(?<name>\D+)$/u", $userData, $m)) {
            $st->setNumber($m['number'])
                ->setLogin($m['number'])
                ->addProperty('Status', $m['status'])
                ->addProperty('Name', $m['name']);
        }
        $this->logger->debug($userData);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:enews|client)[-]airfrance\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        print_r($headers['from'], true);

        return self::detectEmailFromProvider($headers['from']) == true;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Your Miles balance by'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('LOGIN TO YOUR ACCOUNT'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Air France'))}]")->count() > 0
            && $this->http->XPath->query("//a[{$this->starts($this->t('LOGIN TO YOUR ACCOUNT'))}]/preceding::img[contains(@alt, 'Flying Blue')]")->count() > 0;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str, $emailDate)
    {
        $date = null;

        if (preg_match("/^\s*(?<m>\w+)\s*(?<d>\d+)\,\s+(?<y>\d{4})\s*$/u", $str, $m)
            || preg_match("/^\s*(?<d>\d+)\/(?<m>\d+)\/(?<y>\d{4})\s*$/u", $str, $m)
        ) {
            if (is_numeric($m['m'])) {
                if ($m['m'] > 12 && $m['d'] < 12) {
                    $date = $m['m'] . '.' . $m['d'] . '.' . $m['y'];
                } elseif ($m['d'] > 12 && $m['m'] < 12) {
                    $date = $m['d'] . '.' . $m['m'] . '.' . $m['y'];
                } elseif (!empty($emailDate)) {
                    $date1 = strtotime($m['m'] . '.' . $m['d'] . '.' . $m['y']);
                    $d1 = $emailDate - $date1;
                    $date2 = strtotime($m['d'] . '.' . $m['m'] . '.' . $m['y']);
                    $d2 = $emailDate - $date2;

                    if (abs($d1) < 60 * 60 * 24 * 5 && abs($d2) > 60 * 60 * 24 * 25) {// 5 days and 25 days
                        $date = $m['m'] . '.' . $m['d'] . '.' . $m['y'];
                    } elseif (abs($d1) > 60 * 60 * 24 * 25 && abs($d2) < 60 * 60 * 24 * 5) {// 25 days and 5 days
                        $date = $m['d'] . '.' . $m['m'] . '.' . $m['y'];
                    } elseif (abs($d1) < 60 * 60 * 24 * 10 && abs($d2) > 60 * 60 * 24 * 60) {// 10 days and 60 days
                        $date = $m['m'] . '.' . $m['d'] . '.' . $m['y'];
                    } elseif (abs($d1) > 60 * 60 * 24 * 60 && abs($d2) < 60 * 60 * 24 * 10) {// 10 days and 60 days
                        $date = $m['d'] . '.' . $m['m'] . '.' . $m['y'];
                    } elseif ($date1 == $date2) {
                        $date = $m['d'] . '.' . $m['m'] . '.' . $m['y'];
                    }
                }
            } else {
                $date = $m['d'] . ' ' . $m['m'] . ' ' . $m['y'];
            }
        }

        return strtotime($date);
    }
}
