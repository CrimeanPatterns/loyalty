<?php

namespace AwardWallet\Engine\axs\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "axs.com";
    public $reBody = [
        'en' => ['Event Information', 'You will find your e-tickets attached to this email'],
    ];
    public $reSubject = [
        '#Your E-Tickets are attached\s+[\-\d\s]+#',
    ];
    public $lang = '';
    public $subj;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subj = $parser->getSubject();
        $body = $this->http->Response['body'];
        $this->AssignLang($body);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'axs.com')]")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'E'];
        $it['ConfNo'] = str_replace(" ", "", $this->re("#Your E-Tickets are attached\s+.*?(\d[\-\d\s]+)#", $this->subj));
        $node = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Event Information')}']/following::text()[normalize-space(.)][1]");

        if (preg_match("#(.+)\s+\-\s+(\w+\s+\d+,.+)#", $node, $m)) {
            $it['Name'] = $m[1];
            $it['StartDate'] = strtotime($this->normalizeDate($m[2]));
        }
        $it['Address'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Venue:']/following::text()[normalize-space(.)][1]/ancestor::*[1]") . '-' . $this->http->FindSingleNode("//text()[normalize-space(.)='Location:']/following::text()[normalize-space(.)][1]/ancestor::*[1]");
        $it['EventType'] = EVENT_SHOW;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //August 11, 2017 at 5:30 PM
            '#^(\w+)\s+(\d+),\s+(\d+)\s+(?:at\s*)?(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
        ];
        $out = [
            '$2 $1 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
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
}
