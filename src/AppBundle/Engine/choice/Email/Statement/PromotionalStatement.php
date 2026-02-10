<?php

namespace AwardWallet\Engine\choice\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PromotionalStatement extends \TAccountChecker
{
    public $mailFiles = "choice/it-222661693.eml, choice/statements/it-67784198.eml, choice/statements/it-67804662.eml, choice/statements/it-68001909.eml, choice/statements/it-68007647.eml, choice/statements/it-68307032.eml, choice/statements/it-68437521.eml, choice/statements/it-68440547.eml, choice/statements/it-68472454.eml";

    // 'email_choiceprivileges@your.choicehotels.com', 'connected@your.choicehotels.com', 'email_cp_canada@your.choicehotels.com'
    private $detectFrom = ['@your.choicehotels.com', '@members.choicehotels.com'];

    private $detectBody = [
        'fr' => [
            'Ce courriel peut inclure du contenu promotionnel de Choice Hotels International, Inc.',
            'nuitées ou plus dans un établissement ou hôtel avec casino Choice Hotels',
            'lorsque vous achetez des points Choice Privileges',
            'sécurisée pour les membres Choice Privileges',
            'au moyen de l’application mobile Choice Hotels',
        ],
        'en' => [
            'This email may contain promotional content from Choice Hotels International, Inc.',
            'For Choice Privileges program details',
            'To qualify for and earn Choice Privileges',
            '2 Steps to Enhance Your Account’s Security',
            'Bonus points are only available to newly enrolled e-Rewards members',
            'may be booked through a Choice Hotels direct channel',
            'Choice Privileges Loyalty Services',
        ],
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [
            'Hi '          => ['Hi ', 'Hello,'],
            'points as of' => ['points as of', 'point as of'],
            //            'Account' => '',
            //            'Membership Level:' => '',
        ],
        'fr' => [
            'Hi '               => 'Bonjour ',
            'points as of'      => ['points au', 'point au'],
            'Account'           => 'Voir votre compte',
            'Membership Level:' => 'Statut de membre:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->http->FindSingleNode("//img[@alt='Search Hotels']/preceding::*[normalize-space() or self::img][1][contains(@src, 'logo-header')]/@src"))) {
            $email->setIsJunk(true);

            return $email;
        }
        $st = $email->add()->statement();

        // type 1: #AXM639711 | 0 points as of 10/17/2020 | Account
        $info = $this->http->FindSingleNode("//a[{$this->eq($this->t('Account'))}]/ancestor::*[{$this->contains($this->t('points as of'))}][1]");

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Choice Privileges Loyalty Services')]/preceding::a[{$this->eq($this->t('Account'))}][1]/ancestor::*[{$this->contains($this->t('points as of'))}][1]");
        }

        if (!empty($info)) {
            if (preg_match("/#?\s*([A-Z]{0,3}\d{3,15})#?\s*\|?\s*(\d[\d,. ]*?)\s+{$this->preg_implode($this->t("points as of"))}\s+([,\/[:alpha:]\d ]+?)\s*\|?\s*{$this->preg_implode($this->t('Account'))}\s*$/u",
                $info, $m)) {
                $st->setNumber($m[1]);
                $st->setBalance(str_replace([',', ' '], '', $m[2]));

                if (preg_match("/^\s*(\d{2})\/(\d{2})\/(\d{2,4})\s*$/", $m[3], $mat)) {
                    $emailDate = strtotime($parser->getDate());

                    if (strlen($mat[3]) === 2) {
                        $mat[3] = '20' . $mat[3];
                    }

                    $date1 = strtotime($mat[1] . '.' . $mat[2] . '.' . $mat[3]);

                    $date2 = strtotime($mat[2] . '.' . $mat[1] . '.' . $mat[3]);

                    if (empty($date1) && !empty($date2)) {
                        $date = $date2;
                    } elseif (!empty($date1) && empty($date2)) {
                        $date = $date1;
                    } elseif (!empty($date1) && !empty($date2)) {
                        $ds1 = abs($emailDate - $date1);
                        $ds2 = abs($emailDate - $date2);

                        if ($ds1 > $ds2) {
                            $date = $date2;
                        } else {
                            $date = $date1;
                        }
                    }
                } else {
                    $date = strtotime($m[3]);
                }
                $st->setBalanceDate($date);
            }

            $name = trim($this->http->FindSingleNode("//a[{$this->eq($this->t('Account'))}]/preceding::text()[{$this->starts($this->t('Hi '))}][1]",null, true,
                "/^\s*{$this->preg_implode($this->t('Hi '))}([[:alpha:] \-]+)\s*$/u"));

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Choice Privileges Loyalty Services,')][1]/preceding::text()[{$this->starts($this->t('Hi '))}][1]", null, true, "/^\s*{$this->preg_implode($this->t('Hi '))}\s*(\w+)\s*$/ui");
            }

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Level:'))}]/following::text()[normalize-space()][1]");

            if (!empty($status)) {
                $st->addProperty('ChoicePrivileges', ucfirst(strtolower($status)));
            }

            $nights = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Level:'))}]/following::text()[" . $this->contains($this->t("nights to reach")) . "]/ancestor::td[1]", null, true,
                "/ (\d{1,2}) nights to reach \w+ status/");

            if (!empty($nights)) {
                $st->addProperty('Eligible', $nights);
            }

            $expirationDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'points will be forfeited on')]/following::text()[normalize-space()][1]", null, true, "/^(\d+\/\d+\/\d{4})$/");

            if (!empty($expirationDate)) {
                $st->setExpirationDate(strtotime($expirationDate));
            }
        }

        // type 2:
//            Hello, Jennifer
//            Member #: JXF9457
//            Point Balance: 5,048 as of 10/18/2020
//            View Account
        $info = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Member #:'))}]/ancestor::*[{$this->starts($this->t('Hello,'))}][1]//text()[normalize-space()]"));

        if (!empty($info)) {
            if (preg_match("/{$this->preg_implode($this->t('Hello,'))}\s*([[:alpha:] \-]+)\s*\n/", $info, $m)) {
                $st->addProperty('Name', $m[1]);
            }

            if (preg_match("/{$this->preg_implode($this->t('Member #:'))}\s*([A-Z]{0,3}\d{3,15})\s*\n/", $info, $m)) {
                $st->setNumber($m[1]);
            }

            if (preg_match("/{$this->preg_implode($this->t('Point Balance:'))}\s*(\d[\d,.]*) as of ([\d\/]+)\s*\n/",
                $info, $m)) {
                $st->setBalance(str_replace(',', '', $m[1]));
                $st->setBalanceDate(strtotime($m[2]));
            }
        }

        // type 3: #AXM639711 |  Account
        $info = $this->http->FindSingleNode("//a[{$this->eq($this->t('Account'))}]/ancestor::td[1]");

        if (!empty($info)) {
            if (preg_match("/#\s*([A-Z]{0,3}\d{3,15})#?\s*\|\s*{$this->preg_implode($this->t('Account'))}\s*$/", $info, $m)) {
                $st->setNumber($m[1]);
                $st->setNoBalance(true);

                $name = $this->http->FindSingleNode("//a[{$this->eq($this->t('Account'))}]/preceding::text()[{$this->starts($this->t('Hi '))}][1]",null, true,
                    "/^\s*{$this->preg_implode($this->t('Hi '))}([[:alpha:] \-]+)\s*$/");
                $st->addProperty('Name', $name);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        $ruleHref = $this->contains([
            '.choicehotels.com/', '.choicehotels.com%2F',
            'trk.choicehotels.com', 'members.choicehotels.com',
        ], '@href');

        if ($this->http->XPath->query("//a[{$ruleHref}]")->length < 4) {
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
        return 0;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
