<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketUrl extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-13909877.eml, alitalia/it-14904909.eml";

    private $providerCode = '';
    private $langDetectors = [
        'ru' => ['Уважаемый клиент'],
        'it' => ['Gentile Cliente'],
        'ja' => ['旅程を印刷'],
        'en' => ['Dear Customer'],
    ];
    private $lang = '';
    private static $dict = [
        'ru' => [
            // HTML
            'Your ticket(s) is/are:' => 'Your ticket(s) is/are:',
            // PDF
            'Itinerary Details' => ['Сведения о маршруте', 'Сведения О Маршруте'],
            // URL
            'Ticket Number'                      => 'Номер билета',
            'Passenger Name'                     => 'Фамилия пассажира',
            'Issue Date'                         => 'Дата выдачи билета',
            'Issuing Airline'                    => 'Выдан авиакомпанией',
            'Issuing Agent'                      => 'Агент',
            'Booking Reference'                  => 'Справочная информация по бронированию',
            'Flight Depart Arrive'               => 'Flight Отправление Прибытие',
            'Receipt and payment details'        => 'Квитанция и сведения о платеже',
            'Total Fare'                         => 'Общая стоимость',
            'Fare'                               => 'Тариф',
            'Equivalent'                         => 'Эквивалент',
            'Taxes/Fees/Carrier imposed charges' => 'Налоги / пошлины / сборы',
            'Fare Calculation Line'              => 'Строка расчета тарифа',
        ],
        'it' => [
            // HTML
            'Your ticket(s) is/are:' => 'Biglietto',
            // PDF
            'Itinerary Details' => ["Dettagli dell'itinerario", "Dettagli Dell'itinerario"],
            // URL
            'Ticket Number'               => 'Numero biglietto',
            'Passenger Name'              => 'Cognome passeggero',
            'Issue Date'                  => 'Data di emissione biglietto',
            'Issuing Airline'             => 'Compagnia aerea emittente',
            'Issuing Agent'               => 'Agenzia emittente',
            'Booking Reference'           => 'Riferimento prenotazione',
            'Flight Depart Arrive'        => 'VOLO Partenza Arrivo',
            'Receipt and payment details' => 'INFORMAZIONI SUL PAGAMENTO',
            'Total Fare'                  => 'Tariffa totale',
            'Fare'                        => 'Tariffa',
            //            'Equivalent' => '',
            'Taxes/Fees/Carrier imposed charges' => 'Tasse / Supplementi',
            'Fare Calculation Line'              => 'Calcolo tariffa',
        ],
        'ja' => [
            // HTML
            'Your ticket(s) is/are:' => 'Your ticket(s) is/are:',
            // PDF
            'Itinerary Details' => ['旅程の詳細'],
            // URL
            'Ticket Number'               => 'チケット番号',
            'Passenger Name'              => '搭乗者の姓',
            'Issue Date'                  => 'チケット発券日',
            'Issuing Airline'             => '発券元の航空会社',
            'Issuing Agent'               => '発券元の代理店',
            'Booking Reference'           => '予約の参照',
            'Flight Depart Arrive'        => 'Flight 出発 到着',
            'Receipt and payment details' => '受領および支払いの詳細',
            'Total Fare'                  => '運賃総額',
            'Fare'                        => '運賃',
            //            'Equivalent' => '',
            'Taxes/Fees/Carrier imposed charges' => '税金/手数料/料金',
            'Fare Calculation Line'              => '運賃計算行',
        ],
        'en' => [
            // PDF
            'Itinerary Details' => ['Itinerary details', 'Itinerary Details'],
        ],
    ];

    private $http2; // for remote html-content

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        if ($this->assignLang() === false) {
            return false;
        }

        // Detecting Format
        return $this->detectFormat($parser);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (!$this->detectFormat($parser)) {
            $this->logger->debug("Can't detect email format!");

            return $email;
        }
        $email->setType('ETicketUrl' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);
        $this->http2 = clone $this->http;
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $targetLinks = $this->http->XPath->query('//text()[' . $this->eq($this->t('Your ticket(s) is/are:')) . ']/ancestor::td[1]/descendant::a[string-length(normalize-space(.))>4 and ' . $this->contains(['//www.virtuallythere.com/new/eticketPrint', '%2F%2Fwww.virtuallythere.com%2Fnew%2FeticketPrint'], '@href') . ']');

        foreach ($targetLinks as $targetLink) {
            $this->parseUrl($email, $targetLink);
        }
    }

    private function parseUrl(Email $email, $root)
    {
        $url = $this->http->FindSingleNode('./@href', $root);

        if (empty($url)) {
            return;
        }

        $this->http2->GetURL($url);

        $f = $email->add()->flight();

        // ticketNumbers
        $f->addTicketNumber($this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Ticket Number')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]', null, true, '/^(\d{5,})$/'), false);

        // travellers
        $f->addTraveller($this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Passenger Name')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]'));

        // reservationDate
        $issueDate = 0;
        $issueDateText = $this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Issue Date')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

        if ($issueDateText) {
            $issueDateNormal = $this->normalizeDate($issueDateText);

            if ($issueDateNormal) {
                $issueDate = strtotime($issueDateNormal);
            }
        }
        $f->general()->date($issueDate);

        // issuingAirlineName
        $f->issued()->name($this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Issuing Airline')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]'));

//        $issuingAgent = $this->http2->FindSingleNode('/descendant::text()[' . $this->eq($this->t('Issuing Agent')) . '][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

        // confirmation number
        $confirmationTitle = $this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Booking Reference')) . ']');
        $confirmation = $this->http2->FindSingleNode('//text()[' . $this->eq($this->t('Booking Reference')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        if ($confirmation) {
            $f->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
        }

        $patterns['airportInfo'] = '/^'
            . '\s*(?<name>.+)\s+\((?<code>[A-Z]{3})\)' // Катания(Фонтанаросса) (CTA)
            . '(?:\s+(?<terminal>TERMINAL [A-Z\d]+|[A-Z\d]+ TERMINAL))?' // TERMINAL 1
            . '\s+(?<date>.{3,})' // 19/авг/2018
            . '\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)' // 12:25
            . '$/i';

        // segments
        $segments = $this->http2->XPath->query('//tr[not(.//tr) and ' . $this->contains($this->t('Flight Depart Arrive')) . ']/following-sibling::tr');

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http2->FindSingleNode('./td[1]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)\b/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $s->airline()->name($matches['airline']);
                }
                $s->airline()->number($matches['flightNumber']);
            }

            $departTexts = $this->http2->FindNodes('./td[2]/descendant::text()[normalize-space(.)]', $segment);
            $departText = implode("\n", $departTexts);

            if (!empty($departText) && preg_match($patterns['airportInfo'], $departText, $matches)) {
                // depName
                // depCode
                $s->departure()
                    ->name($matches['name'])
                    ->code($matches['code']);

                // depTerminal
                if (!empty($matches['terminal'])) {
                    $s->departure()->terminal(preg_replace('/\s*TERMINAL\s*/i', '', $matches['terminal']));
                }

                // depDate
                $dateDepNormal = $this->normalizeDate($matches['date']);

                if ($dateDepNormal) {
                    if (preg_match('/\b\d{2,4}$/', $dateDepNormal)) {
                        $s->departure()->date(strtotime($dateDepNormal . ', ' . $matches['time']));
                    } elseif ($issueDate) {
                        $dateDepNormal = EmailDateHelper::parseDateRelative($dateDepNormal, $issueDate);
                        $s->departure()->date(strtotime($matches['time'], $dateDepNormal));
                    }
                }
            }

            $arriveTexts = $this->http2->FindNodes('./td[3]/descendant::text()[normalize-space(.)]', $segment);
            $arriveText = implode("\n", $arriveTexts);

            if (!empty($arriveText) && preg_match($patterns['airportInfo'], $arriveText, $matches)) {
                // arrName
                // arrCode
                $s->arrival()
                    ->name($matches['name'])
                    ->code($matches['code']);

                // arrTerminal
                if (!empty($matches['terminal'])) {
                    $s->arrival()->terminal(preg_replace('/\s*TERMINAL\s*/i', '', $matches['terminal']));
                }

                // arrDate
                $dateArrNormal = $this->normalizeDate($matches['date']);

                if ($dateArrNormal) {
                    if (preg_match('/\b\d{2,4}$/', $dateArrNormal)) {
                        $s->arrival()->date(strtotime($dateArrNormal . ', ' . $matches['time']));
                    } elseif ($issueDate) {
                        $dateArrNormal = EmailDateHelper::parseDateRelative($dateArrNormal, $issueDate);
                        $s->arrival()->date(strtotime($matches['time'], $dateArrNormal));
                    }
                }
            }

            // cabin
            $class = $this->http2->FindSingleNode('./td[4]/descendant::text()[normalize-space(.)][1]', $segment, true, '/^(\D{2,})$/');

            if ($class) {
                $s->extra()->cabin($class);
            }

            // seats
            $seat = $this->http2->FindSingleNode('./td[4]/descendant::text()[normalize-space(.)][2]', $segment, true, '/^(\d{1,3}[A-Z])\b/');

            if ($seat) {
                $s->extra()->seat($seat);
            }
        }

        $xpathFragment1 = '//text()[' . $this->eq($this->t('Receipt and payment details')) . ']';
        $totalFare = $this->http2->FindSingleNode($xpathFragment1 . '/following::text()[' . $this->eq($this->t('Total Fare')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d\s]*)$/', $totalFare, $matches)) {
            // p.currencyCode
            // p.total
            $f->price()->currency($matches['currency']);
            $f->price()->total($this->normalizeAmount($matches['amount']));

            // p.cost
            $fare = $this->http2->FindSingleNode($xpathFragment1 . '/following::text()[' . $this->eq($this->t('Fare')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');
            $equivalent = $this->http2->FindSingleNode($xpathFragment1 . '/following::text()[' . $this->eq($this->t('Equivalent')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

            if (
                preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d\s]*)$/', $fare, $m)
                || preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d\s]*)$/', $equivalent, $m)
            ) {
                $f->price()->cost($this->normalizeAmount($m['amount']));
            }

            // p.fees
            $feesRows = $this->http2->XPath->query($xpathFragment1 . '/following::tr[ not(.//tr) and ( ' . $this->starts($this->t('Taxes/Fees/Carrier imposed charges')) . ' or ./preceding-sibling::tr[' . $this->starts($this->t('Taxes/Fees/Carrier imposed charges')) . '] and ./following-sibling::tr[' . $this->starts($this->t('Fare Calculation Line')) . '] ) ]');

            foreach ($feesRows as $feesRow) {
                $fee = $this->http2->FindSingleNode('./descendant::td[normalize-space(.)][last()]', $feesRow);

                if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d\s]*)\s+(?<title>.+)$/', $fee, $m)) {
                    $f->price()->fee($m['title'], $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})([^\d\W]{3,})(\d{4})$/u', $string, $matches)) { // 01ДЕК2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/([^\d\W]{3,})\/(\d{4})$/u', $string, $matches)) { // 07/Sep/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/([^\d\W]{3,})$/u', $string, $matches)) { // 03/Oct
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s*月\s*(\d{1,2})\s*日[,\s]+(\d{4})$/u', $string, $matches)) { // 1月12日, 2018
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\s*月\s*(\d{1,2})\s*日$/u', $string, $matches)) { // 2月8日
            $month = $matches[1];
            $day = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function assignProvider(array $headers)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"thank you for choosing Alitalia service") or contains(normalize-space(.),"On Alitalia operated flight you may") or contains(.,"www.alitalia.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.alitalia.com")]')->length > 0;
        $condition3 = stripos($headers['from'], '@alitalia.sabre.com') !== false || stripos($headers['subject'], 'ALITALIA ELECTRONIC TICKET RECEIPT') !== false;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'alitalia';

            return true;
        }

        return false;
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectFormat(\PlancakeEmailParser $parser)
    {
        $detectFormat = false;

        // HTML
        $xpathFragment1 = '//text()[' . $this->eq($this->t('Your ticket(s) is/are:')) . ']';
        $targetLinks = $this->http->FindNodes($xpathFragment1 . '/ancestor::td[1]/descendant::a[normalize-space(.) and ' . $this->contains(['//www.virtuallythere.com/new/eticketPrint', '%2F%2Fwww.virtuallythere.com%2Fnew%2FeticketPrint'], '@href') . ']', null, '/^\s*(\d{5,})\s*$/');
        $targetLinkValues = array_values(array_filter($targetLinks));

        $stopContents = $this->http->FindNodes($xpathFragment1 . '/following::*[contains(.,":")]', null, '/\d{1,2}\s*:\s*\d{2}(?:\s*[AaPp][Mm])?/');
        $stopContentValues = array_values(array_filter($stopContents));

        if (!empty($targetLinkValues[0]) && empty($stopContentValues[0])) {
            $detectFormat = true;
        }

        // PDF
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (preg_match('/\b' . $this->opt($this->t('Itinerary Details')) . '\b/u', $textPdf)) {
                $detectFormat = false;

                break;
            }
        }

        return $detectFormat;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
