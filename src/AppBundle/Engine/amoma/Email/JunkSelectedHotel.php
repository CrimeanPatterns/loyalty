<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkSelectedHotel extends \TAccountChecker
{
    public $mailFiles = "amoma/it-42839366.eml, amoma/it-42861675.eml";
    public $lang;

    public $langs = [
        'en' => ['Availability runs out quickly. Do not miss out on this deal!'],
        'it' => ['La disponibilità si esaurisce rapidamente. Non perdere questa offerta!'],
    ];

    public static $dict = [
        'en' => [
            'conditions' => [
                'Book now',
                'We are pleased to suggest this accommodation for your booking from',
                'Availability runs out quickly. Do not miss out on this deal!',
            ],
            'hotelPhoto' => 'Hotel photo gallery',
        ],
        'it' => [
            'conditions' => [
                'Prenota ora',
                'Siamo lieti di suggerire questa sistemazione',
                'La disponibilità si esaurisce rapidamente. Non perdere questa offerta!',
            ],
            'hotelPhoto' => 'Galleria fotografica hote',
        ],
    ];
    private $subjects = [
        'en' => ['Your selected hotel - thanks for choosing AMOMA.com'],
        'it' => ["hotel selezionato - grazie per aver scelto AMOMA.com"],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amoma.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'], $headers['subject']) && self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'AMOMA.com') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//img[contains(@src,".amoma.com/201402/amoma_it.png")]/@src')->length === 0;
        $condition2 = $this->http->XPath->query("//a[contains(@href,'email-tracking.amoma.com/track/') and contains(text(),'Go to AMOMA.com')]")->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->langs as $lang) {
            foreach ($lang as $phrase) {
                if ($this->http->XPath->query("//text()[contains(.,'$phrase')]")->length) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailByBody($parser)) {
            foreach ($this->langs as $lang => $value) {
                foreach ($value as $phrase) {
                    if ($this->http->XPath->query("//text()[contains(.,'$phrase')]")->length) {
                        $this->lang = $lang;
                        $a = explode('\\', __CLASS__);
                        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

                        if ($this->parseEmail($email)) {
                            return $email;
                        }
                    }
                }
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function parseEmail(Email $email)
    {
        foreach ($this->t('conditions') as $condition) {
            if ($this->http->XPath->query("//text()[contains(.,'{$condition}')]")->length === 0) {
                $this->logger->debug("[not found TEXT / should be]: " . $condition);

                return false;
            }
        }

        if ($this->http->XPath->query("//text()[contains(., '{$this->t('hotelPhoto')}')]")->length === 0) {
            $this->logger->debug("[found TEXT(s) / shouldn't be]: " . var_export($this->t('conditions'), true));

            return false;
        }

        $email->setIsJunk(true);

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return null;
        }

        return self::$dict[$this->lang][$word];
    }
}
