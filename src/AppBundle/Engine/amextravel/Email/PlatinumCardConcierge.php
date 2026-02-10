<?php

namespace AwardWallet\Engine\amextravel\Email;

class PlatinumCardConcierge extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#American\s+Express\s+Platinum\s+Concierge#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#platinumrequests@concierge.americanexpress\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#americanexpress\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "16.01.2015, 13:24";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "amextravel/it-1694333.eml, amextravel/it-1797763.eml, amextravel/it-1797764.eml, amextravel/it-1797765.eml, amextravel/it-1797811.eml, amextravel/it-1813028.eml, amextravel/it-1827717.eml, amextravel/it-1830395.eml, amextravel/it-1903948.eml, amextravel/it-1906928.eml, amextravel/it-1906929.eml, amextravel/it-1968200.eml, amextravel/it-2115324.eml, amextravel/it-2130390.eml, amextravel/it-2340325.eml, amextravel/it-2340440.eml, amextravel/it-2345807.eml, amextravel/it-2370858.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->fullText = $text;
                    $this->guestPrefixVariants = [
                        'Number\s+in\s+party',
                        'Party\s+Of',
                        'Number\s+Reserved/purchased',
                        '\#\s+of\s+tickets',
                        'Party size',
                    ];
                    $name = node('//strong');
                    $r = '#';
                    $r .= '\s*\n\s*\n\s*';
                    $r .= '(';

                    if ($name) {
                        $r .= preg_quote($name);
                    } else {
                        $r .= '(?:.*?\n\s*)\d+';
                    }
                    $r .= '(?s).*?\s+(?:Date/time|Date\s+and\s+time|Day/Date):(?s)[^\n]+\s*';
                    $r .= '(?:Reserved\s+under:[^\n]+\s+|(?:' . implode('|', $this->guestPrefixVariants) . '):[^\n]+\s+|Time:[^\n]+\s+)*';
                    $r .= ')';
                    $r .= '#i';
                    $s = preg_replace('[\x00-\x1F\x80-\xFF]', '', $text);
                    $reservations = [];

                    if (preg_match_all($r, $s, $m)) {
                        if (count($m[1]) > 1) {
                            $reservations = $m[1];
                        } else {
                            $reservations = preg_split('#----+#i', $m[1][0]);
                        }
                    }

                    return $reservations;

                    // Old bad code follows. Now more generic and good approach was found, hope it will work
                    $variants = [
                        'please\s+find\s+your\s+reservation\s+details:',
                        'Please\s+find\s+your\s+reservation\s+details\s+below:',
                        'details\s+of\s+the\s+reservation\s+below:',
                        'details\s+regarding\s+your\s+dining\s+reservation.',
                        'review\s+your\s+reservation\s+details\s+below:',
                        'I\s+hope\s+you\s+have\s+some\s+wonderful\s+meals!',
                        'with\s+the\s+restaurant\s+based\s+on\s+their\s+confirmation\s+policy\.',
                        'the\s+tickets\s+I\s+have\s+ordered\s+on\s+your\s+behalf\s+to:',
                        'Please\s+contact\s+the\s+restaurant\s+on\s+the\s+morning\s+of\s+your\s+reservation\s+to\s+confirm.',
                        'Requesting\s+to\s+do\s+so\s+may\s+forfeit\s+your\s+reservation\s+as\s+availability\s+is\s+extremely\s+limited.\s*\n',
                        'details\s+regarding\s+the\s+requested\s+change\s+to\s+your\s+reservation\.',
                        'Please\s+see\s+below\s+for\s+your\s+confirmation\s+details\.',
                        'please\s+let\s+me\s+know\s+and\s+we\s+can\s+look\s+into\s+alternate\s+restaurants.',
                        'you\s+can\s+always\s+contact\s+us[^\n]+',
                    ];
                    $subj = re('#(?:' . implode('|', $variants) . ')\n\s*\n\s*(.*)#s');
                    $reservations = preg_split('#----+#i', $subj);

                    return $reservations;
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        // By default we return unknown confno and then in postprocessing tries to find real confno
                        return CONFNO_UNKNOWN;
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Please\s+let\s+me\s+know(?:(?s).*?)based\s+on\s+their\s+confirmation\s+policy.\s+',
                            'requested change to your reservation.',
                            'Please\s+see\s+below\s+for\s+your\s+confirmation\s+details\.',
                        ];
                        $subj = re('#.*(?:Date/time|Day/Date|Date\s+and\s+time)#is');
                        $res = [];
                        $regex = '#';
                        $regex .= '\s*';
                        $regex .= '(?:' . implode('|', $variants) . ')?';
                        $regex .= '(.*)\n\s*';
                        $regex .= '((?s).*?)\n\s*';
                        $r = '(?P<Phone>[\d\s\+\-\(\)]+?)\n\s*';

                        if (re('#\n\s*' . $r . '#i', $subj)) {
                            $regex .= $r;
                        }
                        $r = '(?:www\.|http).*\n\s*';

                        if (re('#' . $r . '#i', $subj)) {
                            $regex .= $r;
                        }
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $m)) {
                            $res['Name'] = $m[1];
                            $res['Address'] = nice($m[2], ',');
                            $res['Phone'] = (isset($m['Phone'])) ? $m[3] : null;
                        }

                        return $res;
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $regex1 = '#(?:Date/time|Date\s+and\s+time):\s+\w+,\s+(\w+\s+\d+\w*,\s+\d+)\s+at\s+(\d+:\d+\s*(?:am|pm))#i';
                        $regex2 = '#Day/Date:\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+Time:\s+(\d+:\d+\s*(?:am|pm))#i';

                        if (preg_match($regex1, $text, $m) or preg_match($regex2, $text, $m)) {
                            return strtotime($m[1] . ', ' . $m[2]);
                        }
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#(?:Reserved\s+Under:)\s*(.*)#i'),
                            re('#Dear\s+([^,]+)#i', $this->fullText)
                        );
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#(?:' . implode('|', $this->guestPrefixVariants) . '):\s+(\d+)#i');

                        if ($subj) {
                            return (int) $subj;
                        }
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    if (count($it) == 1) {
                        $changed = false;

                        if ($t = re('#Total\s+amount:\s+(.*)#', $this->fullText)) {
                            $it[0]['TotalCharge'] = cost($t);
                            $it[0]['Currency'] = currency($t);
                        }

                        if ($cn = orval(re('#Confirmation(?:\s+number)?\s*[:\#]\s+([\w\-]+)#i', $this->fullText), CONFNO_UNKNOWN)) {
                            $it[0]['ConfNo'] = $cn;
                        }

                        return $it;
                    }
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
