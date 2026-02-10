<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangedYourDetails extends \TAccountChecker
{
    public $mailFiles = "expedia/it-156812165.eml, expedia/it-30705846.eml, expedia/it-30754325.eml, expedia/it-31556692.eml, expedia/it-31621580.eml, expedia/it-31709456.eml, expedia/it-31751678.eml, expedia/it-540518966.eml, expedia/it-57082998.eml, expedia/it-68561911.eml, expedia/it-72856219.eml, expedia/it-73506152.eml";
    public static $detectFroms = [
        'travelocity' => [
            'travelocity.com',
        ],
        'chase' => [
            '@customercare.expedia.com',
        ],
        'orbitz' => [
            '@orbitz.com',
        ],
        'marriott' => [
            '@vacations.marriott.com',
        ],
        'expedia' => [
            '@expedia.',
        ],
        'hawaiian' => [
            '@hawaiian.poweredbygps.com',
        ],
        'rbcbank' => [
            '@rbcrewards.com',
        ],
    ];

    public $lang = "";
    public static $dictionary = [
        "en" => [
            //            "Passenger(s):" => "",
            //            "Itinerary Number" => "",
            //            " Confirmation Code" => "",
            "(.*?)\s+Confirmation Code:" => "(.*?)\s+Confirmation Code:",
            "Flight Number"              => ["Flight Number", "Flight Number:"],
            //            "From" => "",
            //            "To" => "",
            //            "Status" => "",
            //            "CANCELLED" => "",
            //            "change" => "",
            "Depart:" => ["Depart:", "Departure time:"],
            "Arrive:" => ["Arrive:", "Arrival time:"],
            //            "Class" => "",
            "Seat"      => ["Seat", "Seats"],
            "Equipment" => ["Equipment", "Aircraft"],
            //            "Operated By" => "",
        ],
        "de" => [
            "Passenger(s):"              => "Passagier(e):",
            "Itinerary Number"           => "Reiseplannummer :",
            " Confirmation Code"         => "Bestätigungscode von ",
            "(.*?)\s+Confirmation Code:" => "Bestätigungscode von (.*?):",
            "Flight Number"              => "Flugnummer",
            "From"                       => "Von",
            "To"                         => "Nach",
            "Status"                     => "Status",
            "CANCELLED"                  => "Storniert",
            "change"                     => "änderung",
            "Depart:"                    => "Abflug:",
            "Arrive:"                    => "Ankunft:",
            "Class"                      => "Klasse",
            "Seat"                       => "Sitze",
            "Equipment"                  => "Transport",
            //            "Operated By" => "",
        ],
        "es" => [
            "Passenger(s):"              => "Pasajero(s):",
            "Itinerary Number"           => "Número de itinerario de ",
            " Confirmation Code"         => "Código de confirmación de ",
            "(.*?)\s+Confirmation Code:" => "Código de confirmación de (.*?):",
            "Flight Number"              => "Número de vuelo",
            "From"                       => "De",
            "To"                         => "A",
            "Status"                     => "Estado",
            "CANCELLED"                  => "cancelado",
            "change"                     => "cambio",
            "Depart:"                    => "Salida:",
            "Arrive:"                    => "Llegada:",
            "Class"                      => "Clase",
            "Seat"                       => "Asientos",
            "Equipment"                  => "Equipo",
            "Operated By"                => "Operado por",
        ],
        "nl" => [
            "Passenger(s):"              => "Passagier(s):",
            "Itinerary Number"           => "Expedia.nl Reisplannummer :",
            " Confirmation Code"         => "Bevestigingscode van ",
            "(.*?)\s+Confirmation Code:" => "Bevestigingscode van (.*?):",
            "Flight Number"              => "Vluchtnummer",
            "From"                       => "Van",
            "To"                         => "Naar",
            "Status"                     => "Status",
            //"CANCELLED"                  => ["cancelado", "Vuelo"],
            "change"                     => "cambio",
            "Depart:"                    => "Vertrek:",
            "Arrive:"                    => "Aankomst:",
            "Class"                      => "Klasse",
            //            "Seat" => "",
            "Equipment" => "Vliegtuig",
            //            "Operated By" => "",
        ],
        "zh" => [
            "Passenger(s):"              => "乘客:",
            "Itinerary Number"           => "行程編號 :",
            " Confirmation Code"         => "確認代碼:",
            "(.*?)\s+Confirmation Code:" => "(.*?) 確認代碼:",
            "Flight Number"              => "航班号",
            "From"                       => "出发地",
            "To"                         => "目的地",
            "Status"                     => "状态",
            //"CANCELLED"                  => ["cancelado", "Vuelo"],
            "change"                     => "變動",
            "Depart:"                    => "起飞时间:",
            "Arrive:"                    => "到达时间:",
            "Class"                      => "舱级",
            //            "Seat" => "",
            "Equipment" => "机型",
            //            "Operated By" => "",
        ],
        "pt" => [
            "Passenger(s):"              => "Passageiro(s):",
            "Itinerary Number"           => "Número de itinerário da ",
            " Confirmation Code"         => "Código de confirmação da ",
            "(.*?)\s+Confirmation Code:" => "Código de confirmação da (.*?):",
            "Flight Number"              => "Número do voo",
            "From"                       => "De",
            "To"                         => "Para",
            "Status"                     => "Status",
            //"CANCELLED"                  => ["cancelado", "Vuelo"],
            "change"                     => "mudança",
            "Depart:"                    => "Partida:",
            "Arrive:"                    => "Chegada:",
            "Class"                      => "Classe",
            "Seat"                       => "Assentos",
            "Equipment"                  => "Aeronave",
            "Operated By"                => "Operado por",
        ],

        "fr" => [
            "Passenger(s):"              => "Passager(s):",
            "Itinerary Number"           => ["Numéro de l'itinéraire ", 'Numéro de voyage '],
            " Confirmation Code"         => "Code de Confirmation ",
            "(.*?)\s+Confirmation Code:" => "Code de Confirmation (?:– )?(.*?):",
            "Flight Number"              => "Numéro de vol",
            "From"                       => "De",
            "To"                         => ["Vers", 'À'],
            "Status"                     => "État",
            //"CANCELLED"                  => ["cancelado", "Vuelo"],
            "change"                     => "changement",
            "Depart:"                    => "Départ",
            "Arrive:"                    => "Arrivée",
            "Class"                      => "Classe",
            "Seat"                       => "Sièges",
            "Equipment"                  => "Appareil",
            //"Operated By"                => "",
        ],
    ];

    private $detectSubject = [
        'changed your flight details',
        'changed your flight(s)',
        'changed your itinerary',
        'upgraded your flights',
        'have made changes to your flight',
        // de
        'hat Änderungen an Ihrem Flug vorgenommen',
        // es
        'cambiaron los detalles de su(s) vuelo(s)',
        // nl
        'Je vluchtwijzigingsverzoek voor je Expedia.nl-reisplan',
        // zh
        '的机票变更申请',
        // pt
        'alterou seu(s) voo(s)',
        'alterou os detalhes do seu voo. Você aceita?',
        // fr
        'a apporté des modifications à votre vol',
    ];

    private $detectCompany = [
        'travelocity' => [
            '//a[contains(@href,"travelocity.")]',
            '//img[contains(@src,"travelocity")]',
            'Travelocity',
        ],
        'chase' => [
            '//a[contains(@href,"chase.")]',
            '//img[contains(@src,"chase")]',
            'JP Morgan Chase',
        ],
        'orbitz' => [ // it-68561911.eml
            '//a[contains(@href,"//orbitz.com/")]',
            'Orbitz, LLC. All rights reserved',
            'Orbitz.com',
            '@orbitz.com',
        ],
        'marriott' => [
            '//a[contains(@href,".marriott.com/") or contains(@href,"vacations.marriott.com")]',
            'vacations.marriott.com',
        ],
        'expedia' => [
            '//a[contains(@href,"expedia.")]',
            '//img[contains(@src,"expedia")]',
            'Expedia',
        ],
        'hawaiian' => [
            '//a[contains(@href,"hawaiian.poweredbygps.com")]',
            'Hawaiian Airlines Confirmation Code',
        ],
        'rbcbank' => [
            '//a[contains(@href,"AvionRewards.com")]',
            'Thanks for contacting Avion Rewards Travel',
            'Thanks for contacting RBC Travel',
            'RBC Travel Customer Support',
        ],
    ];

    private $detectBody = [
        "en" => "Flight Number",
        "de" => "Flugnummer",
        "es" => "Número de vuelo",
        "nl" => "Vluchtnummer",
        "zh" => "航班号",
        "pt" => "Número do voo",
        "fr" => "Numéro de vol",
    ];
    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $htmls = $this->getHtmlAttachments($parser);

        foreach ($htmls as $html) {
            $this->http->SetEmailBody($html);
        }

        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        } else {
            $this->logger->debug('[LANG]: ' . $this->lang);
        }

        if (null !== ($this->providerCode = $this->getProvider($parser))) {
            $this->logger->debug('[providerCode]: ' . $this->providerCode);
            $email->ota()->code($this->providerCode);
        } else {
            $this->logger->debug("can't determine providerCode");
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->getProviderByBody() !== null) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectFroms as $detectFroms) {
            foreach ($detectFroms as $dFrom) {
                if (stripos($from, $dFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $foundFrom = false;

        foreach (self::$detectFroms as $code => $detectFroms) {
            foreach ($detectFroms as $dFrom) {
                if (stripos($headers['from'], $dFrom) !== false) {
                    $foundFrom = true;
                    $this->providerCode = $code;

                    break 2;
                }
            }
        }

        if (!$foundFrom) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 1; // flight
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectFroms);
    }

    private function parseEmail(Email $email): void
    {
        $rls = [];
        $nodes = $this->http->XPath->query("//text()[" . $this->contains($this->t(" Confirmation Code")) . "]");

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>1][1]",
                $root, false, "#^\s*([A-Z\d]{5,})\s*$#");

            if (!empty($rl)) {
                $rls[$this->http->FindSingleNode(".", $root, true, "#^" . $this->t("(.*?)\s+Confirmation Code:") . "$#")] = $rl;
            }
        }

        // Travel Agency
        $email->ota()
            ->code($this->providerCode)
            ->confirmation($this->http->FindSingleNode('(//text()[' . $this->contains($this->t("Itinerary Number")) . '])[1]/following::text()[1]', null, true, "#^(\d{5,})$#"),
                $this->http->FindSingleNode('(//text()[' . $this->contains($this->t("Itinerary Number")) . '])[1]', null, true, '/^(.+?)[\s:：]*$/u'), true)
        ;

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_map("trim", explode(",",
                $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Passenger(s):")) . "]/following::text()[string-length(normalize-space(.))>1][1]"))));

        $xpath = "//text()[" . $this->eq($this->t("Flight Number")) . "]/ancestor::*[" . $this->contains($this->t("Depart:")) . "][1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $nodeText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()][not(ancestor::del) and not(ancestor::*[contains(@style, '#c7c7c7')])]", $root));
            $nodeText = preg_replace("#[ ]*\(\s*" . $this->preg_implode($this->t("change")) . "\s*\)#u", '', $nodeText);

            if (empty($nodeText)) {
                $nodeText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]", $root));
                $nodeText = str_replace(['[', ']'], "", $nodeText);
                $nodeText = preg_replace("#[ ]*\(\s*" . $this->preg_implode($this->t("change")) . "\s*\)#iu", '', $nodeText);
            }

            $s = $f->addSegment();

            // Airlines
            if (preg_match("#" . $this->preg_implode($this->t("Flight Number")) . "\s*\:?\s*\n*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*\n#", $nodeText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;

                $operator = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Operated By")) . "]/following::text()[normalize-space()][1]", $root, true, "/\:?\s*OPERATED BY\s*(.+)/");

                if (preg_match("/^(.+)\s*AS\s*(.+)$/", $operator, $m)) {
                    $s->airline()
                        ->operator($m[2])
                        ->carrierName($m[1]);
                } elseif (preg_match("/^\s*\S.+ ([A-Z\d][A-Z]|[A-Z][A-Z\d]) (\d{1,5})\s*\((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\)\s*$/", $operator, $m)) {
                    // AIR FRANCE AF 1740(KL)
                    $s->airline()
                        ->carrierName($m[1])
                        ->carrierNumber($m[2])
                    ;
                } elseif (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }

                $airline = $this->http->FindSingleNode("(./preceding::text()[string-length(normalize-space(.))>1][not(ancestor::del) and not(ancestor::*[contains(@style, '#c7c7c7')])][1])[last()]", $root);
                $airline = trim(preg_replace("#\s*\(\s*" . $this->preg_implode($this->t("change")) . "\s*\)#u", '', $airline));

                if (isset($rls[$airline])) {
                    $s->airline()
                        ->confirmation($rls[$airline]);
                }
            }

            $date = $this->http->FindSingleNode("(./preceding::text()[normalize-space()][position()<5][contains(translate(normalize-space(),'0123456789', 'dddddddddd'), 'dddd')][not(ancestor::del) and not(ancestor::*[contains(@style, '#c7c7c7')])])[last()]", $root);
            $date = trim(preg_replace("#\s*\(\s*" . $this->preg_implode($this->t("change")) . "\s*\)#u", '', $date));

            // Departure
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("From")) . "\s*\:?\n*\s*(?<city>.+?)[ ]*\([ ]*(?<code>[A-Z]{3})(?:[ ]*[-–][ ]*(?<airport>.+?))?[ ]*\)\s*\n#", $nodeText, $m)) {
                $s->departure()
                    ->name(implode(", ", array_filter([$m['airport'] ?? null, $m['city']])))
                    ->code($m['code'])
                ;
            }

            if (!empty($date) && preg_match("#\n\s*" . $this->preg_implode(preg_replace("/(.+?)\s*:\s*$/", '$1', $this->t("Depart:"))) . "\s*:\s*(.+)\s*\n#", $nodeText, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ' ' . $m[1]))
                ;
            }

            // Arrival
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("To")) . "\s*\:?\n*\s*(?<city>.+?)[ ]*\([ ]*(?<code>[A-Z]{3})(?:[ ]*[-–][ ]*(?<airport>.+?))?[ ]*\)\s*\n#", $nodeText, $m)) {
                $s->arrival()
                    ->name(implode(", ", array_filter([$m['airport'] ?? null, $m['city']])))
                    ->code($m['code'])
                ;
            }

            if (!empty($date) && preg_match("#\n\s*" . $this->preg_implode(preg_replace("/(.+?)\s*:\s*$/", '$1', $this->t("Arrive:"))) . "\s*:\s*\n*([\d\:]+\s*A?P?M?)\s*(?:(?<nextday>[+\-][ ]?\d) \w+)?\s*\n*#iu", $nodeText, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ' ' . $m[1]));

                if (!empty($m['nextday']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m[2] . ' day', $s->getArrDate()));
                }
            }

            // Extra
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Class")) . "\s*:\s*(.+)\s*(?:\n|$)#", $nodeText, $m)) {
                if (preg_match('/^[A-Z]{1,2}$/', $m[1])) {
                    $s->extra()->bookingCode($m[1]);
                } else {
                    $s->extra()->cabin($m[1]);
                }
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Equipment")) . "\s*:\s*(.+)\s*(?:\n|$)#", $nodeText, $m)) {
                $s->extra()
                    ->aircraft($m[1]);
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Seat")) . "\s*:\s*(.+)\s*(?:\n|$)#", $nodeText, $m)) {
                $seats = array_filter(array_map(function ($v) {
                    if (preg_match("#\s*\d{1,3}[A-Z]+\s*#", $v)) {
                        return trim($v);
                    }

                    return null;
                },
                        explode(',', $m[1])));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Status")) . "\s*:\s*(.+)\s*(?:\n|$)#", $nodeText, $m)) {
                $s->extra()
                    ->status($m[1]);
            }

            $this->logger->debug($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Status")) . "]/following::text()[string-length(normalize-space())>1][not(ancestor::del) and not(ancestor::*[contains(@style, '#c7c7c7')])][1][" . $this->contains($this->t("CANCELLED")) . "]", $root));

            if (!empty($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Status")) . "]/following::text()[string-length(normalize-space())>1][not(ancestor::del) and not(ancestor::*[contains(@style, '#c7c7c7')])][1][" . $this->contains($this->t("CANCELLED")) . "]", $root))) {
                $s->extra()
                    ->cancelled();
            }

            if ($this->isSegmentUnique($email, $s)) {
                $f->removeSegment($s);

                continue;
            }
        }
    }

    private function isSegmentUnique(Email $email, FlightSegment $s): bool
    {
        foreach ($email->getItineraries() as $it) {
            /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
            if ($it->getType() == 'flight') {
                $i = 0;

                foreach ($it->getSegments() as $seg) {
                    if ($seg->getDepDate() == $s->getDepDate()
                        && $seg->getStatus() == $s->getStatus()
                        && $seg->getAirlineName() == $s->getAirlineName()
                        && $seg->getFlightNumber() == $s->getFlightNumber()) {
                        $i++;

                        if ($i > 1) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function getProviderByBody(): ?string
    {
        foreach ($this->detectCompany as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->providerCode)) {
            return $this->providerCode;
        }

        return $this->getProviderByBody();
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if (strpos($this->http->Response["body"], $dBody) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function getHtmlAttachments(PlancakeEmailParser $parser, $length = 6000): array
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str '.$str);
        $in = [
            // Wednesday, Feb 06, 2013 1:15 PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+(\s*[AP]M)?)\s*$#i",
            // Thursday, 23 December, 2021 15:50
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\,\s+(\d{4})\s+(\d+:\d+(\s*[AP]M)?)\s*$#i",
            //Tuesday, 17 March, 2020 at 10:40 10:40
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})\s+at(?:\s+\d+:\d+)?\s+(\d+:\d+(\s*[AP]M)?)\s*$#i",
            //Saturday, October 28 2023 at 1:05 PM 1:06 PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+)\,?\s+(\d{4})\s+at\s+[\d\:]+\s*A?P?M?\s+([\d\:]+\s*A?P?M?)$#i",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str2 '.$str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
