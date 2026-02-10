<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelAuthorizationPdf extends \TAccountChecker
{
    public $mailFiles = "egencia/it-6423631.eml, egencia/it-65013343.eml, egencia/it-65180363.eml, egencia/it-69139660.eml, egencia/it-69422229.eml, egencia/it-70526877.eml, egencia/it-8266949.eml";

    public $reFrom = "@customercare.egencia.com";
    public $reSubject = [
        "en"=> "Travel Authorization",
    ];

    public $reBody2 = [
        "en"=> "Trip Summary",
        "da"=> "Rejseoversigt",
        "zh"=> "订单摘要",
    ];
    public $travellers;

    public static $dictionary = [
        "en" => [ // it-6423631.eml, it-65013343.eml, it-65180363.eml, it-69139660.eml, it-69422229.eml, it-8266949.eml
            //			"Trip Summary" => "",
            //			"Trip Details" => "",
            "flightSegmentEnd" => "Notes|Cost Summary|Customer Service|\n\n\n\n",
            //			"Confirmation" => "",
            "Passenger Itinerary \#" => "Itinerary \#", //regexp
            //			"Itinerary \#" => "", //regexp
            //			"FLIGHT" => "",
            //			"Operated by" => "",
            "Arriving in terminal" => ["Arriving in terminal", "Arriving in"],
            //			"Trips flight class" => "", /// ?????
            "dateFormat" => "[^\d\s]+,\s+[^\d\s]+\s+\d+,\s+\d{4}",
            "STATUS"     => "BOOKED",
            //			"CLASS" => "",
            //			"Cost Summary" => "",
            //			"Cost Summary" => "Flight",
        ],
        "da" => [ // ?
            "Customer Service" => "Kundeservice",
            //			"Trip Summary" => "Rejseoversigt",
            //			"Trip Details" => "",
            //"flightSegmentEnd" => "Prisoversigt|Kundeservice|\n\n\n\n\n",
            "flightSegmentEnd"       => "Prisoversigt|Kundeservice",
            "Confirmation"           => "Bekræftelse",
            "Passenger Itinerary \#" => "Egencia-reference",
            //			"Itinerary \#" => "Itinerary \#", //regexp
            "FLIGHT" => "FLY",
            //			"Operated by" => "",
            "Arriving in terminal" => "Ankommer til terminal",
            //			"Trips flight class" => "",
            "dateFormat"        => "(?:[-[:alpha:]]+[ ]*,[ ]*\d{1,2}[ ]*[[:alpha:]]{3,}[ ]+\d{4}|\d{1,2}[ .]+[[:alpha:]]{3,}[ ]+\d{4})",
            "PAYMENT"           => "BETALING",
            "room"              => "værelse",
            "CLASS"             => "KLASSE",
            "Cost Summary"      => "Prisoversigt",
            "Flight"            => "Fly",
            'STATUS'            => ['GODKENDT', 'Bekræft billetter inden', 'AFVENTER DIN BEKRÆFTELSE'],
            'Egencia reference' => 'Egencias referencenummer',
        ],
        "zh" => [ // it-70526877.eml
            "Customer Service"       => "客户服务",
            "Trip Summary"           => "订单摘要",
            "Trip Details"           => " 行程详细信息",
            "flightSegmentEnd"       => "备注|Capital",
            "Confirmation"           => "确认代码",
            "Passenger Itinerary \#" => "易信达参考编号",
            //			"Itinerary \#" => "Itinerary \#", //regexp
            "FLIGHT" => "航班",
            //			"Operated by" => "",
            //			"Arriving in terminal" => "",
            //			"Trips flight class" => "",
            "dateFormat" => "\d{4}年\d+月\d+日\s*\w+",
            // "PAYMENT" => "",
            // "room" => "",
            "CLASS"             => "等级",
            "Cost Summary"      => "Prisoversigt",
            "Flight"            => "航班",
            "STATUS"            => '已预订',
            'Egencia reference' => '易信达参考编号',
        ],
    ];
    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, 'egencia') === false) {
            if (($pos = strpos($text, 'Customer Service')) !== false) {
                $subText = substr($text, $pos);

                if (preg_match("#^Customer Service\s*\n\s*Email:.+Page[ ]*\d+\s*\n\s*Phone:.+\n#", $subText)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName('.*.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        // remove footers
        $this->text = preg_replace("/\n[ ]{25,}{$this->opt($this->t('Customer Service'))}.*(?:\n[ ]{25,}.+)+/", "\n", $this->text);

        $this->priceText = substr($this->text, strrpos($this->text, $this->t('Cost Summary')));
        $text = substr($this->text, $s = strpos($this->text, $this->t("Trip Summary")), (strpos($this->text, $this->t("Trip Details")) !== false ? strpos($this->text, $this->t("Trip Details")) - $s : strlen($this->text) - $s));

        // Passengers
        $this->travellers = [$this->re("#\s{2,}(.*?)\n[^\n]+" . $this->t("Passenger Itinerary \#") . "#", $this->text)];

        if (empty(trim($this->travellers[0]))) {
            $this->travellers = [$this->re("/\s{2,}(.*?)\n[^\n]+\s+{$this->opt($this->t('Egencia reference'))}\s*(?:[#]|\s)/s", $this->text)];
        }

        if (preg_match_all("/({$this->t('dateFormat')}\s+[^\n]+\n(?:[^\n]*\n){1,4}\s*.*?)(?:{$this->t('flightSegmentEnd')})/msu", $text, $match)) {
            $flightAllSegments = implode("\n", $match[1]);
            $flights = $this->split("/({$this->t('dateFormat')}\s+(?={$this->opt($this->t('STATUS'))}))/u", $flightAllSegments);
            $this->parseFlight($email, $flights, $text);
        }

        if (preg_match_all("#([^\d\s]+,\s+[^\d\s]+\s+\d+,\s+\d{4}\s+[^\d\s]+\s+.*?\d+/\d+/\d{4}:\s+\d+:\d+\s+[ap]m\s+-\s+\d+:\d+\s+[ap]m,\s+\d+/\d+/\d{4}:\s+\d+:\d+\s+[ap]m\s+-\s+\d+:\d+\s+[ap]m)#ms", $text, $csegments, PREG_SET_ORDER)) {//  - is icon char,  - time icon
            $this->parseCar($email, $csegments, $text);
        } elseif (preg_match_all("#[^\d\s]+,\s+[^\d\s]+\s+\d+,\s+\d{4}\s+[^\d\s]+\s+.*?\D+\d+\D+\w+\s*\d+\,\s*\d{4}\s+\w+\s*\d+\,\s*\d{4}\D+[\d\:\s]+\s*a?p?m\s+[\d\:\s]+\s*a?p?m\s+\D+\n#sm", $text, $csegments, PREG_SET_ORDER)) {
            $this->parseCar3($email, $csegments, $text);
        } elseif (preg_match_all("#([^\d\s]+,\s+[^\d\s]+\s+\d+,\s+\d{4}\s+[^\d\s]+\s+.*?)(?:\n\n\n\n\n)#ms", $text, $csegments, PREG_SET_ORDER)) {
            $this->parseCar2($email, $csegments, $text);
        }

        if (preg_match_all("/({$this->t("dateFormat")}\s+-\s+{$this->t("dateFormat")}\s+[^\n]+\s+.*?)(?:Notes|{$this->t("dateFormat")}|Cost Summary)/su", $text, $hotels, PREG_SET_ORDER)
            || preg_match_all("/({$this->t("dateFormat")}\s+-\s+{$this->t("dateFormat")}\s+[^\n]+\s*trips_payment_method_.*[^\n]+\s+.*?)(?:Notes|{$this->t("dateFormat")}|Cost Summary)/su", $text, $hotels, PREG_SET_ORDER)
        ) {
            $this->parseHotel($email, $hotels, $text);
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

    private function parseFlight(Email $email, $flights, $text): void
    {
        preg_match_all("#^.{0,30}()\s+[A-Z][a-z]#m", $text, $airIcon);

        if (isset($airIcon[1]) && count($flights) != count($airIcon[1])) {
            $this->logger->debug("bad flights segments count");

            return;
        }

        $airs = [];
        $otaCollected = [];

        foreach ($flights as $flight) {
            foreach ($this->split("#(" . $this->t("Confirmation") . " [A-Z\d]+)#", $flight) as $stext) {
                if ($rl = $this->re("#" . $this->t("Confirmation") . " ([A-Z\d]+)#", $stext)) {
                    $airs[$rl][] = [
                        'date' => $this->re("#(" . $this->t("dateFormat") . ")#u", $flight),
                        'text' => $stext,
                    ];
                } else {
                    $this->logger->debug("RL not matched");

                    return;
                }
            }
        }

        foreach ($airs as $rl=>$data) {
            $f = $email->add()->flight();

            $confirmation = $this->re("#" . $this->t("Passenger Itinerary \#") . "[ ]*([A-Z\d]{5,})\b#", $this->text);

            if (empty(trim($confirmation))) {
                $confirmation = $this->re("/Egencia\s*reference\s*[#][ ]*([A-Z\d]{5,})\b/sm", $this->text);
            }

            if (!empty($confirmation) && in_array($confirmation, $otaCollected) == false) {
                $otaCollected[] = $confirmation;
                $email->ota()
                    ->confirmation($confirmation);
            }

            $f->general()
                ->confirmation($rl);

            // Passengers
            if (count(array_filter(array_unique($this->travellers))) > 0) {
                $f->general()
                    ->travellers($this->travellers);
            }

            foreach ($data as $skey=>$seg) {
                $s = $f->addSegment();

                $tableText = preg_replace(["#^.*?" . $this->t("Confirmation") . "[^\n]+#s", "#\n[^\n]*(?:" . $this->opt($this->t("Arriving in terminal")) . "|" . $this->t("Flight arrives") . "|" . $this->t("Operated by") . ").+#s"], '', $seg['text']);
                $colpos = $this->TableHeadPos($this->inOneRow($tableText));

                if (count($colpos) < 7) {
                    $this->logger->debug("bad parse columns segment:" . $skey);

                    return;
                }

                $cols = $this->SplitCols($tableText, $colpos);
                $date = strtotime($this->normalizeDate($seg['date']));

                $s->airline()
                    ->name($this->re("#(?:\n|\s{2,})(\w{2})\s+\d+(?:\n|\s{2,})#", $cols[4]))
                    ->number($this->re("#(?:\n|\s{2,})\w{2}\s+(\d+)(?:\n|\s{2,})#", $cols[4]));

                $depName = implode(" ", array_filter(array_map("trim", explode("\n", $this->re("#\n\d+:\d+\s*\n?(?:\s[ap]m)?\s+([A-Z].+)#ms", $cols[1])))));

                if (empty($depName)) {
                    $depName = implode(" ", array_filter(array_map("trim", explode("\n", $this->re("#([A-Z].+)\n[A-Z]{3}\s*\n\d+:\d+\s*\n?(?:\s[ap]m)?\s+#ms", $cols[1])))));
                }
                $s->departure()
                    ->name($depName)
                    ->code($this->re("#\n([A-Z]{3})\n#", $cols[1]))
                    ->date(strtotime($this->re("#\n(\d+:\d+(?:\s[ap]m)?)\n#", $cols[1]), $date));

                $depTerminal = trim($this->re("#TERMINAL\s+(.+)#", $cols[5]), ' -');

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }

                $arrName = implode(" ", array_filter(array_map("trim", explode("\n", $this->re("/\n\d+:\d+(?:\s[ap]m)?(?:[ ]*[+][ ]*(?:\d{1,3}))?\n+[ ]*(.{3,})/is", $cols[3])))));

                if (empty($arrName)) {
                    $arrName = implode(" ", array_filter(array_map("trim", explode("\n", $this->re("#([A-Z].+)\n[A-Z]{3}\s*\n\d+:\d+\s*\n?(?:\s[ap]m)?\s+#ms", $cols[3])))));
                }
                $s->arrival()
                    ->name($arrName)
                    ->code($this->re("#\n([A-Z]{3})\n#", $cols[3]))
                    ->date(strtotime($this->re("/\n(\d+:\d+(\s[ap]m)?)(?:[ ]*[+][ ]*(?<overnight>\d{1,3}))?\n/i", $cols[3]), $date));

                $arrTerminal = $this->re("#" . $this->opt($this->t("Arriving in terminal")) . "\s+(.+)#", $seg['text']);

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }

                if (preg_match("#" . $this->t("CLASS") . "(?:\s+" . $this->t("Trips flight class") . ")?\s+(.+)\(([A-Z]{1,2})\)#ms", $cols[7], $m)
                    || preg_match("#" . $this->t("CLASS") . "(?:\s+" . $this->t("Trips flight class") . ")?\s+(.+)\(([A-Z]{1,2})\)#ms", $cols[6], $m)) {
                    $s->extra()
                        ->cabin(trim(preg_replace("#\s+#", ' ', $m[1])))
                        ->bookingCode($m[2]);
                }

                $seat = $this->re("#\n(\d{1,3}[A-Z])\n#", $cols[6]);

                if (!empty($seat)) {
                    $s->extra()->seat($seat);
                }

                $arr = array_filter(explode("", str_replace("\n", " ", $cols[2])));

                if (count($arr) > 0) {
                    $s->extra()
                        ->duration(array_shift($arr));
                }

                if (!preg_match("/^\s*{$s->getDepCode()}\s*-\s*{$s->getDepCode()}[ ]*{$this->opt($this->t('Flight'))}[ ]*,.*?\d{4}.*?[ ]{3,}(?<currency>[^\d)(]+)[ ]?(?<amount>\d[,.\'\d]*)$/m", $this->priceText, $m) && count($airs) > 1
                ) {
                    if (preg_match("/^\s*{$s->getDepCode()}\s*-\s*[A-Z]{3}[ ]*{$this->opt($this->t('Flight'))}[ ]*,.*?\d{4}.*?[ ]{3,}(?<currency>[^\d)(]+)[ ]?(?<amount>\d[,.\'\d]*)$/m", $this->priceText, $m)) {
                        $f->price()
                            ->total($this->normalizeAmount($m['amount']))
                            ->currency($this->currency($m['currency']));
                    }
                }
            }

            if (count($airs) == 1) {
                if (preg_match("/^[ ]*[A-Z]{3}\s*-\s*[A-Z]{3}[ ]*{$this->opt($this->t('Flight'))}[ ]*,.*?\d{4}.*?[ ]{3,}(?<currency>[^\d)(]+)[ ]?(?<amount>\d[,.\'\d]*)$/m", $this->priceText, $m)) {
                    $f->price()
                        ->currency($this->currency($m['currency']))
                        ->total($this->normalizeAmount($m['amount']))
                    ;
                }
            }
        }
    }

    private function parseCar(Email $email, $csegments, $text): void
    {
        $test = substr_count($text, "");

        if (count($csegments) != $test && count($csegments) !== $test / 2) {
            $this->logger->debug("bad cars segments count");

            return;
        }

        $colpos = [0, 52, 76, 100];
        arsort($colpos);

        $cars = [];

        foreach ($csegments as $skey => $ctext) {
            $ctext = $ctext[1];
            $rows = explode("\n", $ctext);
            $cols = [];

            foreach ($rows as $row) {
                foreach ($colpos as $k => $pos) {
                    $crow = trim(mb_substr($row, $pos, null, 'UTF-8'));

                    if (strpos($crow, "  ") !== false && strpos($crow, "") === false) {
                        $this->logger->debug("bad parse columns car:" . $skey);

                        return;
                    }
                    $cols[$k][] = $crow;
                    $row = mb_substr($row, 0, $pos, 'UTF-8');
                }
            }

            foreach ($cols as &$col) {
                $col = implode("\n", $col);
            }

            if (preg_match("#\s+(?<company>.*?):\s*(?<action>Drop-off|Pick-up)#", $cols[0], $m)) {
                $cars[$m['company']][strtolower($m['action'])] = $cols;
            } else {
                $this->logger->debug("cant match company/action:" . $skey);

                return;
            }
        }

        foreach ($cars as $company => $actions) {
            if (!isset($actions['pick-up']) || !isset($actions['drop-off'])) {
                $this->logger->debug("action not found");

                return;
            }
            $pu = $actions['pick-up'];
            $do = $actions['drop-off'];

            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->re("#\n(\w+)\nItinerary#", $pu[3]));

            $r->pickup()
                ->location(trim(preg_replace("#\n+#", " ", $this->re("#\s+[^\n]+\n(.*?)#ms", $pu[0]))))
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#PICK-UP\s+(.+)#ms", $pu[1]))))))
                ->phone(trim(preg_replace("#\n+#", " ", $this->re("#\s*([\d-]+)#ms", $pu[0]))))
                ->openingHours(trim(preg_replace("#\n+#", " ", $this->re("#(.+)#ms", $pu[0]))));

            $r->dropoff()
                ->location(trim(preg_replace("#\n+#", " ", $this->re("#\s+[^\n]+\n(.*?)#ms", $do[0]))))
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#DROP-OFF\s+(.+)#ms", $pu[2]))))))
                ->phone(trim(preg_replace("#\n+#", " ", $this->re("#\s*([\d-]+)#ms", $do[0]))))
                ->openingHours(trim(preg_replace("#\n+#", " ", $this->re("#(.+)#ms", $do[0]))));

            $r->extra()
                ->company($company);

            $r->car()
                ->type(trim($this->re("#CAR\s+(.+)#ms", $pu[3])));

            if (count(array_filter(array_unique($this->travellers))) > 0) {
                $r->general()
                    ->travellers($this->travellers);
            }
        }
    }

    private function parseCar3(Email $email, $csegments, $text): void
    {
        $test = substr_count($text, "");

        if (count($csegments) != $test && count($csegments) !== $test / 2) {
            $this->logger->debug("bad cars segments count");

            return;
        }

        $colpos = [0];
        arsort($colpos);

        $cars = [];

        foreach ($csegments as $skey => $ctext) {
            $ctext = $ctext[0];
            $rows = explode("\n", $ctext);
            $cols = [];

            foreach ($rows as $row) {
                foreach ($colpos as $k => $pos) {
                    $crow = trim(mb_substr($row, $pos, null, 'UTF-8'));
                    $cols[$k][] = $crow;
                    $row = mb_substr($row, 0, $pos, 'UTF-8');
                }
            }

            foreach ($cols as &$col) {
                $col = implode("\n", $col);
            }

            if (preg_match("#\s+(?<company>.*?):\s*(?<action>Drop-off|Pick-up)#", $cols[0], $m)) {
                $cars[$m['company']][strtolower($m['action'])] = $cols;
            } else {
                $this->logger->debug("cant match company/action:" . $skey);

                return;
            }
        }

        foreach ($cars as $company => $actions) {
            if (!isset($actions['pick-up']) || !isset($actions['drop-off'])) {
                $this->logger->debug("action not found");

                return;
            }
            $pu = $actions['pick-up'];
            $do = $actions['drop-off'];

            $tablePU = $this->SplitCols($this->re("/.+\n(^PICK-UP.+)/sm", $pu[0]), [0, 22, 55]);
            $tableDO = $this->SplitCols($this->re("/.+\n(^PICK-UP.+)/sm", $do[0]), [0, 22, 55]);

            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->re("#\n(\w+)\n(?:Itinerary|Egencia reference)#", $pu[0]))
                ->date(strtotime($this->re("/^(\w+\,\s+\w+\s+\d+\,\s+\d{4})\s+BOOKED\n/", $pu[0])));

            $r->pickup()
                ->location(trim(preg_replace("#\n+#", " ", $this->re("#\n(.+)#ms", $pu[0]))))
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#PICK-UP\s+(.+)#ms", $tablePU[0]))))))
                ->phone(trim(preg_replace("#\n+#", " ", $this->re("#\s*([\d-\s]+)#ms", $pu[0]))))
                ->openingHours(trim(preg_replace("#\n+#", " ", $this->re("#\s*(Open.+)PICK\-UP#ms", $pu[0]))));

            $r->dropoff()
                ->location(trim(preg_replace("#\n+#", " ", $this->re("#\n(.+)#ms", $do[0]))))
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#DROP-OFF\s+(.+)#ms", $tableDO[1]))))))
                ->phone(trim(preg_replace("#\n+#", " ", $this->re("#\s*([\d-\s]+)#ms", $do[0]))))
                ->openingHours(trim(preg_replace("#\n+#", " ", $this->re("#\s*(Open.+)PICK\-UP#ms", $do[0]))));

            $r->extra()
                ->company($company);

            if (preg_match("/CAR\s+\n(.+)\n(\D+)\n\s+/", $tablePU[2], $m)) {
                $r->car()
                    ->type(trim($m[1]));

                if (isset($m[2]) && !empty(trim($m[2]))) {
                    $r->car()
                        ->model(trim($m[2]));
                }
            }

            if (preg_match("/.+Car\s*\,\s*\w+\s+\d+\,\s+\d{4}\s*\-\s*\w+\s+\d+\,\s+\d{4}\s+(\S{1})([\d\.]+)/ms", $this->priceText, $m)) {
                $r->price()
                    ->currency($this->currency($m[1]))
                    ->total($this->normalizeAmount($m[2]));
            }

            if (count(array_filter(array_unique($this->travellers))) > 0) {
                $r->general()
                    ->travellers($this->travellers);
            }
        }
    }

    private function parseCar2(Email $email, $csegments, $text): void
    {
        foreach ($csegments[0] as $csegment) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->re("#\s*(\d+)\n\s*(?:Itinerary|Egencia reference)#u", $csegment));

            if (preg_match("#Egencia reference.+\n+\s*\s*(?<Company>\D+)\s+At\s+(?<Location>\D+\n.+)\n\s*\s*(?<Phone>[\d\s]+)\n\n\s+\s*(?<Hours>[\d\:]+\s*\-\s*[\d\:]+)#", $csegment, $match)) {
                $r->pickup()
                    ->location(preg_replace("#\n+#", " ", $match['Location']))
                    ->phone($match['Phone'])
                    ->openingHours($match['Hours']);

                $r->dropoff()->same();

                $r->extra()
                    ->company($match['Company']);
            }

            $dateInfo = $this->SplitCols($csegment, [0, 25, 55]);

            $r->pickup()
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#PICK-UP\s+(.+)#ms", $dateInfo[0]))))));

            $r->dropoff()
                ->date(strtotime($this->normalizeDate(preg_replace("#\n+#", " ", trim($this->re("#DROP-OFF\s+(.+)#ms", $dateInfo[1]))))));

            $r->car()
                ->type(trim(preg_replace("#\n+#", " ", $this->re("#CAR\s+(.+)#ms", $dateInfo[2]))));

            if (count(array_filter(array_unique($this->travellers))) > 0) {
                $r->general()
                    ->travellers($this->travellers);
            }
        }
    }

    private function parseHotel(Email $email, $hotels, $text): void
    {
        $test = substr_count($text, "");

        if (count($hotels) != $test) {
            $this->logger->debug("bad hotels count");

            return;
        }

        $colpos = [0, 52, 76, 100];
        arsort($colpos);

        foreach ($hotels as $htext) {
            $htext = $htext[1];
            $rows = explode("\n", $htext);
            $cols = [];
            $tablestarted = false;

            foreach ($rows as $row) {
                if (strpos($row, "Itinerary") !== false || preg_match("/{$this->opt($this->t('Egencia reference'))}/", $row)) {
                    $tablestarted = true;
                }

                if ($tablestarted) {
                    foreach ($colpos as $k=>$pos) {
                        $crow = trim(mb_substr($row, $pos, null, 'UTF-8'));
                        $cols[$k][] = $crow;
                        $row = mb_substr($row, 0, $pos, 'UTF-8');
                    }
                }
            }

            foreach ($cols as &$col) {
                $col = implode("\n", $col);
            }

            $h = $email->add()->hotel();

            $confirmation = trim($this->re("#\s{2,}(\w+)\n\s+(?:Itinerary|Egencia)#", $htext));

            if (empty($confirmation)) {
                $confirmation = $this->re("/{$this->opt($this->t('Egencia reference'))}[\s#]+(\d{5,})[ ]*\n/u", $htext);
            }
            $h->general()
                ->confirmation($confirmation);

            $h->hotel()
                ->name($this->re("#\s+(.+)#", $htext));

            if (preg_match("/^({$this->t("dateFormat")})\s+-\s+({$this->t("dateFormat")})\s+{$this->opt($this->t('PAYMENT'))}/u", $htext, $dates)) {
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($dates[1])))
                    ->checkOut(strtotime($this->normalizeDate($dates[2])));
            }

            $address = trim(preg_replace("#\n+#", " ", $this->re("#Managed Hotel \s*(.*?)#ms", $cols[0])));

            if (empty($address)) {
                $address = trim(preg_replace("#\n+#", " ", $this->re("#(.*?)#ms", $cols[0])));
            }
            $h->hotel()
                ->address($address);

            $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';

            if (preg_match("/({$patterns['phone']})\s*Fax:\s*({$patterns['phone']})?/i", $htext, $phones)) {
                $h->hotel()
                    ->phone($phones[1])
                    ->fax(empty($phones[2]) ? null : $phones[2], false, true);
            }

            $h->booked()
                ->rooms($this->re("/\b(\d{1,3})\s+{$this->opt($this->t('room'))}/iu", $htext));

            if (preg_match("/" . $h->getHotelName() . "\s*Hotel\s*\,.+?\s*(\b[A-Z]{3}|\D{1})[ ]?(?<normalizeAmount>\d[,.\'\d]*)$/m", $this->priceText, $m)) {
                $h->price()
                    ->total($this->normalizeAmount($m[2]))
                    ->currency($this->currency($m[1]));
            }

            $roomTypeDescription = trim(preg_replace("#\n+#", " ", $this->re("/\d+\s+{$this->opt($this->t('room'))}(.+)/s", $cols[3])));

            if (empty($roomTypeDescription)) {
                $roomTable = $this->SplitCols($htext, [0, 45]);
                $roomTypeDescription = preg_replace("#\n+#", " ", $this->re("/\d+\s*{$this->opt($this->t('room'))}\s*(Room.+)/s", $roomTable[1]));
            }

            if (empty($roomTypeDescription)) {
                $roomTable = $this->SplitCols($htext, [0, 50]);
                $roomTypeDescription = preg_replace("#\n+#", " ", $this->re("/\d+\s*{$this->opt($this->t('room'))}\s+(.+)/s", $roomTable[1]));
            }

            if (!empty($roomTypeDescription)) {
                $room = $h->addRoom();
                $room->setDescription($roomTypeDescription);
            }

            if (preg_match("/Hotel.+\s(\S{1})(?<normalizeAmount>\d[,.\'\d]*)\n/", $this->priceText, $m)) {
                $h->price()
                    ->currency($this->currency($m[1]))
                    ->total($this->normalizeAmount($m[2]));
            }

            if (count(array_filter(array_unique($this->travellers))) > 0) {
                $h->general()
                    ->travellers($this->travellers);
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //$this->logger->error($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Saturday, December 3, 2016
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+\s+[ap]m)$#", //December 3, 2016 6:00 pm
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //December 3, 2016
            "#^\s*(\d+)[.\s]+([^\d\s]+)\s+(\d{4})\s*$#", //6. august 2018
            "#^(\d{4})年(\d+)月(\d+)日\s*\w+$#u",
            '/^[-[:alpha:]]+[ ]*,[ ]*(\d{1,2})[ ]*([[:alpha:]]{3,})[ ]+(\d{4})$/u', // søndag, 7 februar 2021
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
            "$2 $1 $3",
            "$1 $2 $3",
            "$3.$2.$1",
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => '$',
            '£' => 'GBP',
            'kr'=> 'DKK',
            '￥' => 'JPY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function inOneRow($text)
    {
        $textRows = explode("\n", $text);
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
