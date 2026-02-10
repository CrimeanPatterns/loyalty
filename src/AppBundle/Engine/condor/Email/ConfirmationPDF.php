<?php

namespace AwardWallet\Engine\condor\Email;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "condor/it-388680385.eml, condor/it-6390687.eml, condor/it-6408005.eml, condor/it-6412723.eml, condor/it-6422071.eml, condor/it-6476746.eml, condor/it-6476779.eml, condor/it-6536266.eml, condor/it-6575520.eml, condor/it-6594875.eml, condor/it-6598725.eml, condor/it-669496483.eml, condor/it-673353593.eml, condor/it-7778691.eml, condor/it-8134117.eml";
    // + emails from Flight

    public $reFrom = '@condor.com';

    public $reBody = [
        'es' => ['/ FACTURA', 'Confirmacion de Reserva'],
        'de' => ['/ RECHNUNG', 'B u c h u n g s - Bestaetigung', 'Buchungsbestätigung', 'Bestätigung Ihrer gebuchten Zusatzleistungen'],
        'nl' => ['/ FACTUUR'],
        'en' => ['/ INVOICE', 'Booking - Confirmation', 'Cancellation - Confirmation'],
        'fr' => ['/ Facture'],
    ];

    public $reSubject = [
        'es' => ['Su reserva en Condor n'],
        'de' => ['Reisebestaetigun', 'Reisebestätigun'],
        'nl' => ['Reisbevestiging'],
        'en' => ['Confirmation'],
    ];

    public $lang = '';

    public $tot;

    public static $dict = [
        'es' => [
            'body'                     => ['/ FACTURA', 'Confirmacion de Reserva'],
            'reDate'                   => 'Fecha exp.',
            'Seats'                    => 'Reserva de asientos',
            'operated by'              => 'operado por',
            'Total'                    => 'suma total',
            'Your customer number is:' => 'Su numero de cliente es:',
            'booking ref.:'            => 'No.reserva:',
            // 'Confirmation of your booked additional services' => '',
        ],
        'de' => [
            'body'                                            => ['/ RECHNUNG', 'B u c h u n g s - Bestaetigung', 'Buchungsbestätigung', 'Bestätigung Ihrer gebuchten Zusatzleistungen'],
            'reDate'                                          => 'Best.-Dat',
            'Seats'                                           => ['Sitzplatzreservierung', 'Sitz:'],
            'operated by'                                     => ['durchgeführt von', 'durchgefuehrt von'],
            'Total'                                           => 'Gesamtpreis',
            'Your customer number is:'                        => 'Ihre Kundennummer lautet:',
            'booking ref.:'                                   => 'Buchung:',
            'Confirmation of your booked additional services' => 'Bestätigung Ihrer gebuchten Zusatzleistungen',
        ],
        'nl' => [
            'body'                     => '/ FACTUUR',
            'reDate'                   => 'Bevest.dat.',
            'Seats'                    => 'Stoelreservering',
            'operated by'              => 'uitgevoerd door',
            'Total'                    => 'Totaalbedrag',
            'Your customer number is:' => 'Uw klantnummer is:',
            // 'Confirmation of your booked additional services' => '',
        ],
        'en' => [
            'body'   => ['/ INVOICE', 'Booking - Confirmation', '/ Invoice', 'Cancellation - Confirmation'],
            'reDate' => 'conf. date',
            'Seats'  => 'Seat reservation',
            'Total'  => 'total amount',
            //            'Your customer number is:' => '',
            // 'Confirmation of your booked additional services' => '',
        ],
        'fr' => [
            'body'                     => ['/ Facture'],
            'reDate'                   => 'Date comm:',
            // 'Seats'                    => 'Reserva de asientos',
            // 'operated by'              => 'operado por',
            'Total'                    => 'suma total',
            'Your customer number is:' => 'Votre numéro de client:',
            'booking ref.:'            => 'Priorite:',
            // 'Confirmation of your booked additional services' => '',
        ],
    ];

    protected $providerCode = '';

    /** @var \HttpBrowser */
    private $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = str_replace(chr(194) . chr(160), ' ', $textPdf);

            $this->assignProvider($textPdf);

            if ($this->assignLang($textPdf) === false) {
                continue;
            }

            $textBody = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($textBody);

            $its = $this->parseEmail($textPdf);
            $class = explode('\\', __CLASS__);

            if (!empty($this->tot['Total']) && !preg_match("/{$this->preg_implode($this->t('Confirmation of your booked additional services'))}/", $textBody)) {
                if (count($its) === 1) {
                    $its[0]['TotalCharge'] = $this->tot['Total'];
                    $its[0]['Currency'] = $this->tot['Currency'];
                } elseif (count($its) > 1) {
                    return [
                        'parsedData' => [
                            'Itineraries' => $its,
                            'TotalCharge' => [
                                'Amount'   => $this->tot['Total'],
                                'Currency' => $this->tot['Currency'],
                            ],
                        ],
                        'emailType'    => end($class) . ucfirst($this->lang),
                        'providerCode' => $this->providerCode,
                    ];
                }
            }

            return [
                'parsedData' => [
                    'Itineraries' => $its,
                ],
                'emailType'    => end($class) . ucfirst($this->lang),
                'providerCode' => $this->providerCode,
            ];
        }

        return null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = str_replace(chr(194) . chr(160), ' ', $textPdf);

            // Detecting Provider
            if ($this->assignProvider($textPdf) === false) {
                continue;
            }

            // Detecting Language
            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false && strpos($headers['subject'], 'Condor') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
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

    public function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public static function getEmailProviders()
    {
        return ['condor', 'thomascook'];
    }

    /*
    private function findRL($g_i, $rl, $its): int
    {
        foreach ($its as $i => $it) {
            if ($g_i !== $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function mergeItineraries($its): array
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) !== -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers'])) {
                    $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                    $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                }
                unset($its[$i]);
            }
        }

        return $its;
    }
    */

    protected function assignProvider($text = ''): bool
    {
        if (stripos($text, 'CONDOR FLUGDIENST GMBH') !== false || stripos($text, 'www.condor.com') !== false || strpos($text, 'booked Condor flight') !== false || stripos($text, '@condor.com') !== false) {
            $this->providerCode = 'condor';

            return true;
        } elseif (stripos($text, 'THOMAS COOK AIRLINES LTD') !== false || strpos($text, 'about Thomas Cook Airlines') !== false || stripos($text, 'www.flythomascook.com') !== false || stripos($text, 'thomascookairlines.com') !== false || strpos($text, 'with Thomas Cook Airlines Limited') !== false || stripos($text, '@thomascook.com') !== false) {
            $this->providerCode = 'thomascook';

            return true;
        }

        return false;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($stext): array
    {
        $its = [];

        if (is_array($this->t('body'))) {
            foreach ($this->t('body') as $value) {
                $str = strstr($stext, $value, true);
                $text = strstr($stext, $value);

                if ($text) {
                    break;
                }
            }
        } else {
            $str = strstr($stext, $this->t('body'), true);
            $text = strstr($stext, $this->t('body'));
        }

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $rls = explode(',', preg_replace('#\s+#', '', $this->re("#Filekey:\s+(.+)#", $text)));
        $it['RecordLocator'] = $rls[0];

        if (empty($it['RecordLocator'])) {
            $rls = explode(',', preg_replace('#\s+#', '', $this->re("#\n\s*FK:\s+([A-Z\d]+)#", $text)));
            $it['RecordLocator'] = $rls[0];
        }
        $recLocText = strstr(strstr($stext, 'departure return', true), 'booking ref');

        if (empty($it['RecordLocator']) && preg_match('/\s*\d+\.\d+\.\d+\s+\d+\s+([\d\-]+)/', $recLocText, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (empty($it['RecordLocator'])
            && preg_match("/{$this->preg_implode($this->t('booking ref.:'))} *(\d{5,}[\d\-]*)(?:\n| *version:)/i", $stext, $m)
            && preg_match('/' . $this->preg_implode($this->t('reDate')) . ': *(\d+\.\d+\.\d+)\n/', $stext, $md)
        ) {
            $it['RecordLocator'] = $m[1];
            $confDate = $md[1];
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        if (preg_match_all("#^\s*\d+\s+(?:Mr|Mrs|Ms|Sr|Sra|Bebé|Men|H|K|D|Mevr|Chd|Inf|Miss|Mw)\S*?(?:\.| )+([-A-Z\/ ]+)\s*(?:\d[,.\d ]*)?$#m", $text, $m)) {
            $it['Passengers'] = $m[1];

            foreach ($it['Passengers'] as $key => $value) {
                $it['Passengers'][$key] = trim(str_ireplace('cancel', '', $value));
            }
        }

        if (preg_match("#^\s*{$this->t('Your customer number is:')} *(\d+)\s*$#m", $text, $m)) {
            $it['AccountNumbers'][] = $m[1];
        }

        if (false !== stripos($text, 'Cancelled on') || false !== stripos($text, 'Cancelled by') || false !== stripos($text, 'Cancellation - Confirmation')) {
            $it['Cancelled'] = true;
            $it['Status'] = 'cancelled';
        }

        if (empty($confDate)) {
            $confDate = $this->pdf->FindSingleNode('//text()[contains(normalize-space(.),"' . $this->t('reDate') . '")]/following::text()[normalize-space(.)][1]',
                null, true, '/^(\d{1,2}\.\d{1,2}\.\d{2,4})$/');
        }
        $it['ReservationDate'] = strtotime($this->normalizeDate($confDate));

        $seats = [];

        if (preg_match_all("/\n\s*{$this->preg_implode($this->t('Seats'))}\s+(.+?)\s+\d[\d.]+\n/", $text, $seatMatches)) {
            $arrSeats = [];

            foreach ($seatMatches[1] as $item) {
                $arrSeats[] = preg_split('/[, ]+/', $item);
            }
            //transposition of matrix
            $last = sizeof($arrSeats) - 1;
            eval("\$seats = array_map(null, \$arrSeats[" . implode("], \$arrSeats[", range(0, $last)) . "]);");
        }
        $this->tot = $this->getTotalCurrency($this->re("#\n\s*{$this->t('Total')}\s+(.+)#", $text));

        $text = str_replace('MOSKAU - ', '', $text); // 6575520 for correct determinate DepName and ArrName, when MOSKAU - SHEREMETYEVO hardcode :((
        $segments = $this->splitter('/\n(.*?[ ]*(?:\d+\.\d+\.\d+\s+)?[ ]*[A-Z].+?[ ]*-[ ]*.+\n[ ]*[A-Z]{1,2} )/', $text);
        $date = $it['ReservationDate'];

        foreach ($segments as $root) {
            $seg = [];

            if (preg_match('/(\d+\.\d+\.\d+\s+)?[ ]*([A-Z].+?)[ ]*-[ ]*(.+)\n/', $root, $m)) {
                if (!empty($m[1])) {
                    $date = strtotime($this->normalizeDate($m[1]));
                }
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = $m[2];
                $seg['ArrName'] = $m[3];
            }

            if (preg_match('/[ ]{2,}(?<Class>[A-Z]{1,2})?[ ]+(?<AirName>[A-Z\d][A-Z]|[A-Z][A-Z\d]|[A-Z]{3})[ ]*(?<FNum>\d+)[ ]+(?<DTime>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]*-[ ]*(?<ATime>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)(?:[ ]*(?<Day>[+-]\d{1,3}))?[ ]*(?:\((?<Dur>[^)]+)\))?$/m', $root, $m)) {
                $seg['BookingClass'] = $m['Class'];
                $seg['AirlineName'] = $m['AirName'];
                $seg['FlightNumber'] = $m['FNum'];
                $seg['DepDate'] = strtotime($m['DTime'], $date);
                $seg['ArrDate'] = strtotime($m['ATime'], $date);

                if (!empty($m[6])) {
                    $seg['ArrDate'] = strtotime($m[6] . ' days', $seg['ArrDate']);
                    $date = strtotime($m[6] . ' days', $date);
                }

                if (!empty($m[7])) {
                    $seg['Duration'] = $m[7];
                }
            }

            if (preg_match("/\)\s*(?:{$this->preg_implode($this->t('operated by'))}\s+(.+?))?\s+(?:SPO|LM)\s+.+?\n\s*(.+?)(?:\n|$)/s", $root, $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $seg['Operator'] = preg_replace("#\s+#", " ", $m[1]);
                }
                $seg['Cabin'] = $m[2];
            }

            if (empty($seg['Cabin']) && false !== stripos($root, 'Premium Class')) {
                $seg['Cabin'] = 'Premium Class';
            }

            if (empty($seg['Cabin']) && false !== stripos($root, 'Economy Class')) {
                $seg['Cabin'] = 'Economy Class';
            }

            if (empty($seg['Cabin']) && false !== stripos($root, 'Business Class')) {
                $seg['Cabin'] = 'Business Class';
            }

            if (count($seats) > 0) {
                $seg['Seats'] = (array) array_shift($seats);
            }

            $it['TripSegments'][] = $seg;
        }

        $its = [$it];

        return array_values($its);
    }

    private function normalizeDate(?string $date): string
    {
        $in = [
            '#(\d{2})[\.\/]*(\d{2})[\.\/]*(\d+)#',
        ];
        $out = [
            '$3-$2-$1',
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

    private function assignLang($text): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($text, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
