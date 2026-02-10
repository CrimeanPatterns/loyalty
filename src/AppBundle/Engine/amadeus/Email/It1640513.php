<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parse pdf in amadeus/It2366105

class It1640513 extends \TAccountCheckerExtended
{
    public $mailFiles = "amadeus/it-11458451.eml, amadeus/it-1640513.eml, amadeus/it-4564552.eml, amadeus/it-4665695.eml, amadeus/it-5079383.eml, amadeus/it-6332954.eml";

    public static $dictionary = [
        'en' => [
            // block Headers
            // type 1: with blue stripe
            //            'Booking ref:' => '',
            //            'Issue date:' => '',
            //            'Airline booking ref:' => '',
            //            'Ticket:' => '',
            //            'Traveler' => '',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Booking Reference:' => '',
            //            'Passenger' => '',
            //            'Ticket number' => '',

            // block Itinerary
            //            'Seat' => '', // last column name, use for relative date
            //            'Operated by' => '',
            //            'Frequent flyer number' => '',
            //            'Equipment' => '',
            //            'Arrival Day' => '',
            //            'Duration' => '',
            //            'Number of stops' => '',

            // block Receipt
            //            'Name' => '',
            //            'Ticket number Receipt' => '',
            'Air Fare' => ['Air Fare', 'Fare'],
            //            'Equiv Fare Paid' => '',
            'Tax'          => ['Tax', 'Taxes', 'Tax & Carrier Fees/Charges'],
            'Total Amount' => ['Total Amount', 'Grand Total'],
            //            'Issuing Airline and date' => '',
        ],
        'fr' => [
            // block Headers
            // type 1: with blue stripe
            'Booking ref:' => 'Reference du dossier',
            'Issue date:'  => 'Issue date:',
            //            'Airline booking ref:' => '',
            'Ticket:'  => 'Numéro de billet:',
            'Traveler' => 'Passager',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Booking Reference:' => '',
            //            'Passenger' => '',
            //            'Ticket number' => '',

            // block Itinerary
            'Seat'        => 'Siège', // last column name, use for relative date
            'Operated by' => 'Opéré par',
            //            'Frequent flyer number' => '',
            //            'Equipment' => '',
            'Arrival Day' => 'Arrivée Jour',
            //            'Duration' => '',
            //            'Number of stops' => '',

            // block Receipt
            'Name'                  => 'Nom',
            'Ticket number Receipt' => 'Numéro de billet',
            'Air Fare'              => ['Tarif Aérien'],
            //            'Equiv Fare Paid' => '',
            'Tax'                      => ['Taxes'],
            'Total Amount'             => 'Montant total',
            'Issuing Airline and date' => 'Compagnie Emettrice et date',
        ],
        'es' => [
            // block Headers
            // type 1: with blue stripe
            'Booking ref:' => 'Loc. Reserva:',
            'Issue date:'  => 'Fecha de Emisión:',
            //            'Airline booking ref:' => '',
            'Ticket:'  => 'Billete Electrónico:',
            'Traveler' => 'Viajero',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Booking Reference:' => '',
            //            'Passenger' => '',
            //            'Ticket number' => '',

            // block Itinerary
            'Seat'        => 'Asiento', // last column name, use for relative date
            'Operated by' => 'Operado por',
            //            'Frequent flyer number' => '',
            'Equipment' => 'Equipo',
            //            'Arrival Day' => '',
            'Duration' => 'Duración',
            //            'Number of stops' => '',

            // block Receipt
            'Name'                     => 'Nombre',
            'Ticket number Receipt'    => 'Numero de Billete',
            'Air Fare'                 => ['Tarifa aérea'],
            'Equiv Fare Paid'          => 'Tarifa Equiv Pagada',
            'Tax'                      => ['Tasa', 'Recargo De Aerolinea'],
            'Total Amount'             => 'Importe Total',
            'Issuing Airline and date' => 'Compania Emisora y fecha',
        ],
        'pt' => [
            // block Headers
            // type 1: with blue stripe
            'Booking ref:' => 'Código de reserva:',
            'Issue date:'  => 'Data de emissão:',
            //            'Airline booking ref:' => '',
            'Ticket:'  => 'Bilhete:',
            'Traveler' => 'Passageiro',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Booking Reference:' => '',
            //            'Passenger' => '',
            'Ticket number' => 'Número do Bilhete',

            // block Itinerary
            'Seat'        => 'Assento', // last column name, use for relative date
            'Operated by' => 'Operado por',
            //            'Frequent flyer number' => '',
            'Equipment'   => 'Equipment',
            'Arrival Day' => 'Dia da chegada',
            'Duration'    => 'Duration',
            //            'Number of stops' => '',

            // block Receipt
            //            'Name' => 'Nome',
            'Ticket number Receipt'    => 'Número do Bilhete',
            'Air Fare'                 => ['Tarifa Aérea'],
            'Equiv Fare Paid'          => 'Tarifa Equiv Paga',
            'Tax'                      => ['Taxa'],
            'Total Amount'             => ['Valor Total'],
            'Issuing Airline and date' => 'Empresa Emissora e data',
        ],
    ];

    private $detects = [
        'Electronic Ticket Receipt',
        'Reçu de Billet Électronique',
        'Comprobante de Billete Electrónico',
        'Sundor E-Ticket',
        'Mémo Voyage Billet',
        'Recibo de bilhete eletrônico',
        // es
        'Comprobante de Billete Electrónico',
        // pt
        'Recibo de bilhete eletrônico',
    ];

    private $emailDate;
    private $lang = 'en';
    private $providerCode;
    private $detectsLang = [
        // en - first
        'en' => [
            ['From', 'To', 'Flight', 'Class'],
        ],
        'fr' => [
            ['De', 'À', 'Vol', 'Classe'],
        ],
        'es' => [
            ['De', 'A', 'Vuelo', 'Clase'],
        ],
        'pt' => [
            ['De', 'Para', 'Voo', 'Classe'],
        ],
    ];
    private static $detectsProvider = [
        'asia' => [
            'isTA'               => false,
            'from'               => ['@asiamiles.com'],
            'containsHeaderText' => ['CATHAY PACIFIC AIRWAYS LTD'],
        ],
        'finnair' => [
            'isTA'               => false,
            'containsHeaderText' => ['FINNAIR'],
        ],
        'srilankan' => [
            'isTA'               => false,
            'from'               => ['@srilankan.com'],
            'containsHeaderText' => ['SRILANKAN AIRLINES'],
        ],
        'aviancataca' => [
            'isTA'               => false,
            'from'               => ['@avianca.com'],
            'containsHeaderText' => ['AVIANCA BRAZIL', 'AVIANCA BRASIL'],
        ],
        'airgreenland' => [
            'isTA'               => false,
            'from'               => ['@airgreenland.gl'],
            'containsHeaderText' => ['AIR GREENLAND'],
        ],
        // the last
        'amadeus' => [
            'isTA' => true,
            'from' => ['@asiamiles.com'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        if (isset($headers['from']) && stripos($headers['from'], 'eticket@amadeus.com') !== false) {
            $finded = true;
        }

        if (!$finded && !empty($headers['from'])) {
            foreach (self::$detectsProvider as $code => $detect) {
                if (!empty($detect['from']) && $this->striposAll($headers['from'], $detect['from']) !== false) {
                    $this->providerCode = 'asia';
                    $finded = true;
                }
            }
        }

        if ($finded && isset($headers['subject']) && (
                stripos($headers['subject'], 'YOUR ETICKET RECEIPT:') !== false
                || stripos($headers['subject'], 'Your Electronic Ticket Receipt') !== false
                || preg_match('/.+? [A-Z]{3} [A-Z]{3} [A-Z]{3}/', $headers['subject'])
                || stripos($headers['subject'], 'Votre reçu de billet électronique :') !== false
                || stripos($headers['subject'], 'Your eTicket is ready: use booking ref') !== false
                )) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//img[contains(@src, 'amadeus')]")->length === 0 && stripos($body, 'amadeus') === false
                && $this->http->XPath->query("//a[contains(@href, 'www.cathaypacific.com')] | //text[contains(normalize-space(), 'CATHAY PACIFIC AIRWAYS')]")->length === 0
                && $this->http->XPath->query("//td[normalize-space()='NVB(2)']/following::td[normalize-space()][1][normalize-space()='NVA(3)']")->length === 0 && stripos($body, 'amadeus') === false
        ) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)
                || $this->http->XPath->query("//*[contains(normalize-space(),\"" . $detect . "\")]")->length > 0
            ) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public function assignLang()
    {
        foreach ($this->detectsLang as $lang => $detects) {
            foreach ($detects as $detect) {
                $conditions = preg_replace('/(.+)/', './/text()[normalize-space() = "$1"]', (array) $detect);

                if ($this->http->XPath->query("//tr[not(.//tr)][" . implode(' and ', $conditions) . "]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $etrPos = count($this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[position() < 3][following-sibling::tr[.//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "]]]/preceding-sibling::tr"));
        $travellerPos = count($this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[position() < 3]/following-sibling::tr[.//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "]]/preceding-sibling::tr"));

        if (!empty($etrPos) && !empty($travellerPos)) {
            $header = implode(" ", $this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[position() < 3][following-sibling::tr[.//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "]]]/following-sibling::tr[position() < " . ($travellerPos - $etrPos) . "]//td[not(.//td)][string-length(normalize-space()) > 1]//text()[normalize-space()]"));
        }

        if (empty($this->providerCode) && !empty($header)) {
            foreach (self::$detectsProvider as $code => $detects) {
                if (!empty($detects['containsHeaderText']) && $this->striposAll($header, $detects['containsHeaderText']) !== false) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        $this->assignLang();

        $this->parseHtml($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseHtml(Email $email)
    {
        $beforeDate = strtotime($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Seat")) . "]/following::text()[normalize-space()][2]",
            null, true, "/^\s*(\w+\s*\d+\s*\w+\s*\d{4})\s*$/"));

        if (empty($beforeDate)) {
            $beforeDate = strtotime($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t('Issue date:')) . "]/following::td[normalize-space(.)][1]"));
        }

        if (empty($beforeDate)) {
            $beforeDate = strtotime($this->http->FindSingleNode("//td[not(.//td) and " . $this->contains($this->t('Issuing Airline and date')) . "]/following::td[normalize-space(.) and not(" . $this->starts('/') . ")][1]",
                null, true, "/ (\d{1,2}[A-Z]{3}\d{2})\s*(?:$|IATA)/i"));
        }

        if (!empty($beforeDate)) {
            $beforeDate = strtotime("-1day", $beforeDate);
        }

        $f = $email->add()->flight();

        // Travel Agency

        $noRef = false;
        // type 1: с синей полосой
        $ref = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t('Booking ref:')) . "]/following::td[normalize-space(.)][1]",
            null, true, "/^\s*[A-Z\d]{5,7}\s*$/");

        // type 2:
        //    Passenger                   Ticket number
        //    Yang Benchung Mr            297 2403544740
        if (empty($ref)) {
            $ref = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking Reference:')) . " or " . $this->eq($this->addSlash($this->t('Booking Reference:'))) . "]/following::text()[normalize-space(.)][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
        }
        // type 2:
        if (empty($ref)) {
            $ref = $this->http->FindSingleNode("//text()[" . $this->eq(preg_replace('/\s*:\s*$/', '', $this->t('Booking Reference:'))) . "]/following::text()[normalize-space(.) and not(" . $this->starts('/') . ")][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
        }
        // type 2:
        if (empty($ref)) {
            $ref = $this->http->FindSingleNode("//text()[" . $this->eq(preg_replace('/\s*:\s*$/', '', $this->t('Booking Reference:'))) . "]/following::text()[normalize-space(.)=':'][1]/following::text()[normalize-space(.)][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
        }
        // type 2:
        if (empty($ref)) {
            $ref = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Booking Reference:')) . " or " . $this->contains($this->addSlash($this->t('Booking Reference:'))) . "]",
                null, true, "/" . $this->opt($this->t('Booking Reference:')) . "\s*([A-Z\d]{5,7})\s*$/");
        }
        // type 2:
        if (empty($ref)) {
            $etrPos = count($this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[1]/preceding-sibling::tr"));
            $travellerPos = count($this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[1]/following-sibling::tr[.//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "]]/preceding-sibling::tr"));

            if (!empty($etrPos) && !empty($travellerPos)) {
                $tds = $this->http->FindNodes("//text()[" . $this->eq('Electronic Ticket Receipt') . "]/ancestor::tr[1]/following-sibling::tr[position() < " . ($travellerPos - $etrPos) . "]//td[not(.//td)][string-length(normalize-space()) > 1]"
                    . "[not(" . $this->contains("Telephone") . ")]");

                if (empty($tds)) {
                    $noRef = true;
                }
            }
            $ref = $this->http->FindSingleNode("//text()[" . $this->eq(preg_replace('/\s*:\s*$/', '', $this->t('Booking Reference:'))) . "]/following::text()[normalize-space(.) and not(" . $this->starts('/') . ")][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
        }

        if (!empty($this->providerCode) && isset(self::$detectsProvider[$this->providerCode], self::$detectsProvider[$this->providerCode]['isTA'])
            && self::$detectsProvider[$this->providerCode]['isTA'] === false) {
            if ($noRef === true) {
                $f->general()
                    ->noConfirmation();
            } else {
                $f->general()
                    ->confirmation($ref);
            }
        } else {
            if ($noRef === true) {
                $email->obtainTravelAgency();
            } else {
                $email->ota()
                    ->confirmation($ref);
            }
            $f->general()
                ->noConfirmation();
        }

        // Travellers and Ticket
        // type 1: с синей полосой
        $traveller = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Traveler")) . "]/following-sibling::td[2]",
            null, false, '/(.+?)\s*\(/');
        $ticket = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Ticket:")) . "]/following-sibling::td[1]",
            null, true, '/^\s*(\d{3,}[- ]?\d{10,}[- \d*]*)\s*$/');

        // type 2:
        //    Passenger                   Ticket number
        //    Yang Benchung Mr            297 2403544740
        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//tr[not(.//tr)][td[normalize-space()][1]//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "] and td[normalize-space()][2]//text()[" . $this->eq($this->t("Ticket number")) . " or " . $this->eq($this->addSlash($this->t("Ticket number"))) . "]]/following-sibling::tr[normalize-space()][1]/td[normalize-space()][1]",
                null, false, '/^\s*([[:alpha:] \-]+?)\s*(?:\(.*\))?$/');
            $ticket = $this->http->FindSingleNode("//tr[not(.//tr)][td[normalize-space()][1]//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "] and td[normalize-space()][2]//text()[" . $this->eq($this->t("Ticket number")) . " or " . $this->eq($this->addSlash($this->t("Ticket number"))) . "]]/following-sibling::tr[normalize-space()][1]/td[normalize-space()][2]",
                null, false, '/^\s*(\d{3}[- ]?\d{10}[- \d*]*)\s*$/');
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//tr[not(.//tr)][td[normalize-space()][1]//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "] and td[normalize-space()][2]//text()[" . $this->eq($this->t("Ticket number")) . " or " . $this->eq($this->addSlash($this->t("Ticket number"))) . "]]/following::tr[normalize-space()][1][count(.//td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][1]",
                null, false, '/^\s*([[:alpha:] \-]+?)\s*(?:\(.*\))?$/');
            $ticket = $this->http->FindSingleNode("//tr[not(.//tr)][td[normalize-space()][1]//text()[" . $this->eq($this->t("Passenger")) . " or " . $this->eq($this->addSlash($this->t("Passenger"))) . "] and td[normalize-space()][2]//text()[" . $this->eq($this->t("Ticket number")) . " or " . $this->eq($this->addSlash($this->t("Ticket number"))) . "]]/following::tr[normalize-space()][1][count(.//td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][2]",
                null, false, '/^\s*(\d{3}[- ]?\d{10}[- \d*]*)\s*$/');
        }

        // Receipt
        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Name")) . " or " . $this->eq($this->addSlash($this->t("Name"))) . "]/ancestor::td[1]/following-sibling::td[1]",
                null, true, '/^\s*:\s*([[:alpha:] \-]+?)\s*(?:\(.*\))?$/');
        }

        if (empty($ticket)) {
            $receiptXpath = "//text()[" . $this->eq($this->t("Name")) . " or " . $this->eq($this->addSlash($this->t("Name"))) . "]";
            $ticket = $this->http->FindSingleNode($receiptXpath . "/following::text()[" . $this->eq($this->t("Ticket number Receipt")) . " or " . $this->eq($this->addSlash($this->t("Ticket number Receipt"))) . "]/ancestor::td[1]/following-sibling::td[1]",
                null, true, '/^\s*:\s*(\d{3}[- ]?\d{10}[- \d*]*)\s*$/');
        }

        if (!empty($traveller)) {
            $traveller = preg_replace("/ (?:Mr|Ms|Miss|Mrs)\s*$/i", '', $traveller);
            $f->general()
                ->traveller($traveller, true);
        }

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent flyer number")) . " or " . $this->eq($this->addSlash($this->t("Frequent flyer number"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
            null, '/^\s*([A-Z\d ]{5,})\s*$/')));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Price

        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Amount")) . " or " . $this->eq($this->addSlash($this->t("Total Amount"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
            null, true, '/^\s*:\s*(.+)$/');

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\.]*)\s*$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($m['currency'])
            ;
            $currency = $m['currency'];
        } elseif (preg_match("/^\s*(?<amount>\d[\d\.]*)\s*$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::cost($m['amount']))
            ;
        }

        $taxesStr = "\n\n" . implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1][normalize-space()=':']/following-sibling::td[normalize-space()]"));
        $level = 1;

        if (empty(trim($taxesStr)) && !empty($this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::td[2][count(.//td[not(.//td)][normalize-space()]) = 2 and descendant::td[not(.//td)][normalize-space()][2][normalize-space()=':']]/following-sibling::td[normalize-space()]"))) {
            $taxesStr = "\n\n" . implode("\n",
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::td[2]/following-sibling::td[normalize-space()]/descendant-or-self::td[not(.//td)][normalize-space()]"));
            $level = 2;
        }

        if (empty(trim($taxesStr)) && !empty($this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::td[3][count(.//td[not(.//td)][normalize-space()]) = 2 and descendant::td[not(.//td)][normalize-space()][2][normalize-space()=':']]/following-sibling::td[normalize-space()]"))) {
            $taxesStr = "\n\n" . implode("\n",
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::td[3]/following-sibling::td[normalize-space()]/descendant-or-self::td[not(.//td)][normalize-space()]"));
            $level = 3;
        }

        if (!empty(trim($taxesStr))) {
            for ($i = 1; $i < 5; $i++) {
                $row = trim(implode("\n",
                    $this->http->FindNodes("//text()[" . $this->eq($this->t("Tax")) . " or " . $this->eq($this->addSlash($this->t("Tax"))) . "]/ancestor::tr[{$level}]/following-sibling::tr[normalize-space()][{$i}][not(contains(., ':'))]//td[not(.//td)][normalize-space()]")));

                if (!empty($row)) {
                    $taxesStr .= "\n\n" . $row;
                } else {
                    break;
                }
            }
            $taxesRows = $this->split("/\n *(?<currency>[A-Z]{3})/", $taxesStr);

            foreach ($taxesRows as $row) {
                if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?:PD )?(?<amount>\d[\d\.]*)\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*$/", $row,
                    $m)) {
                    if (!empty($currency)) {
                        if ($currency === $m['currency']) {
                            $taxes[] = ['name' => $m['name'], 'amount' => PriceHelper::cost($m['amount'])];
                        } else {
                            $taxes = [];

                            break;
                        }
                    } else {
                        $currency = $m['currency'];
                        $f->price()
                            ->currency($currency);
                        $taxes[] = ['name' => $m['name'], 'amount' => PriceHelper::cost($m['amount'])];
                    }
                } else {
                    $taxes = [];

                    break;
                }
            }

            if (!empty($taxes)) {
                foreach ($taxes as $tax) {
                    $f->price()
                        ->fee($tax['name'], $tax['amount']);
                }
            }
        }

        if (!empty($currency)) {
            $fare = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Equiv Fare Paid")) . " or " . $this->eq($this->addSlash($this->t("Equiv Fare Paid"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*:\s*({$currency}\s*.+)$/");

            if (empty($fare)) {
                $fare = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Air Fare")) . " or " . $this->eq($this->addSlash($this->t("Air Fare"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                    null, true, "/^\s*:\s*({$currency}\s*.+)$/");
            }
        } else {
            $fare = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Air Fare")) . " or " . $this->eq($this->addSlash($this->t("Air Fare"))) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*:\s*(.+)$/");
        }

        if (!empty($fare) && preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\.]*)\s*$/", $fare, $m)) {
            $f->price()
                ->cost(PriceHelper::cost($m['amount']))
                ->currency($m['currency'])
            ;
        }

        // Segments

        $RLText = implode('\n', $this->http->FindNodes("//td[not(.//td) and " . $this->eq($this->t('Airline booking ref:')) . "]/following::td[normalize-space(.)][1]//text()[normalize-space()]"));
        $rls = [];

        if (!empty($RLText) && preg_match_all("/\b([A-Z\d]{2})\s*\/\s*([A-Z\d]{5,7})\b/", $RLText, $m)) {
            foreach ($m[1] as $i => $al) {
                $rls[$al] = $m[2][$i];
            }
        }

        $xpath = "//text()[translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','ZZZZZZZZZZZZZZZZZZZZZZZZZZ')='Z']/ancestor::td[1]/following-sibling::td[normalize-space(.)][2][starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'dd:dd')]/ancestor::tr[1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $date = $this->normalizeDateRelative($this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root), $beforeDate);

            // Airline
            $name = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root, true, "/^\s*([A-Z\d]{2})\s*\d{1,5}\s*$/");
            $number = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root, true, "/^\s*[A-Z\d]{2}\s*(\d{1,5})\s*$/");
            $s->airline()
                ->name($name)
                ->number($number)
            ;

            if (!empty($name) && isset($rls[$name])) {
                $s->airline()
                    ->confirmation($rls[$name]);
            }

            $operator = $this->http->FindSingleNode("./following-sibling::tr[position() < 4]/td[" . $this->contains($this->t('Operated by')) . "]/following-sibling::td[normalize-space(.)][1]", $root);

            if (!empty($operator)) {
                $operator = preg_replace("/(.+?)\s+DBA\s+.*/", '$1', $operator);
                $s->airline()
                    ->operator($operator);
            }

            // Departure
            $dep = $this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;
            } elseif (!empty($dep)) {
                $s->departure()
                    ->name($dep)
                    ->noCode()
                ;
            }
            $time = $this->http->FindSingleNode("./td[normalize-space(.)!=''][6]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            // Arrival
            $arr = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;
            } elseif (!empty($arr)) {
                $s->arrival()
                    ->name($arr)
                    ->noCode()
                ;
            }
            $time = $this->http->FindSingleNode("./td[normalize-space(.)!=''][7]", $root);

            if (!empty($date) && !empty($time)) {
                /*if (!empty($subj = $this->http->FindSingleNode("./following-sibling::tr[position() < 5]/td[".$this->contains($this->t("Arrival Day"))."]", $root, true, "/".$this->opt($this->t("Arrival Day"))."\s*([\+\-]\s*\d+)\b/"))) {
                    $date = strtotime($subj . ' days', $date);
                }*/
                if (!empty($subj = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Operated by'))}][1]/ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->starts($this->t('Arrival Day'))}]", $root, true, "/" . $this->opt($this->t("Arrival Day")) . "\s*([\+\-]\s*\d+)\b/"))) {
                    $date = strtotime($subj . ' days', $date);
                }

                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            $terminal1 = trim(preg_replace(["/.+?:\s*(.+)/", "/\s*terminal\s*/i"], ['$1', ' '], $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)!=''][1]", $root, true, "/.*Terminal.*/i")));

            if (empty($terminal1)) {
                $terminal1 = trim(preg_replace(["/.+?:\s*(.+)/", "/\s*terminal\s*/i"], ['$1', ' '], $this->http->FindSingleNode("./following::tr[2]/descendant::text()[normalize-space()][1]", $root, true, "/.*Terminal.*/i")));
            }

            $terminal2 = trim(preg_replace(["/.+?:\s*(.+)/", "/\s*terminal\s*/i"], ['$1', ' '], $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)!=''][2]", $root, true, "/.*Terminal.*/i")));

            if (empty($terminal2)) {
                $terminal2 = trim(preg_replace(["/.+?:\s*(.+)/", "/\s*terminal\s*/i"], ['$1', ' '], $this->http->FindSingleNode("./following::tr[2]/descendant::text()[normalize-space()][2]", $root, true, "/.*Terminal.*/i")));
            }

            if (!empty($terminal1) && !empty($terminal2)) {
                $s->departure()
                    ->terminal($terminal1);
                $s->arrival()
                    ->terminal($terminal2);
            } elseif (!empty($terminal1)) {
                $arrNameCol = 0;

                foreach ($this->http->XPath->query("./td[normalize-space(.)!=''][2]/preceding-sibling::td", $root) as $cRoot) {
                    $col = $this->http->FindSingleNode("@colspan", $cRoot);
                    $arrNameCol += (!empty($col) ? $col : 1);
                }
                $terminalCol = 0;

                foreach ($this->http->XPath->query("./following-sibling::tr[1]/td[normalize-space(.)!=''][1]/preceding-sibling::td", $root) as $cRoot) {
                    $col = $this->http->FindSingleNode("@colspan", $cRoot);
                    $terminalCol += (!empty($col) ? $col : 1);
                }

                if ($terminalCol >= $arrNameCol) {
                    $s->arrival()
                        ->terminal($terminal1);
                } else {
                    $s->departure()
                        ->terminal($terminal1);
                }
            }

            // Extra
            $s->extra()
                ->bookingCode($this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "/^\s*([A-Z]{1,2})\s*$/"))
                ->aircraft($this->http->FindSingleNode("following-sibling::tr[position() < 5][" . $this->contains($this->t('Equipment')) . "][1]/descendant::td[not(.//td) and " . $this->contains($this->t('Equipment')) . "]/following-sibling::td[normalize-space(.)][1]", $root), true, true)
                ->duration($this->http->FindSingleNode("following-sibling::tr[position() < 5][" . $this->contains($this->t('Duration')) . "][1]/descendant::td[not(.//td) and " . $this->contains($this->t('Duration')) . "]/following-sibling::td[normalize-space(.)][1]",
                    $root, true, "/(.+?) *(?:\(|$)/"), true, true)

            ;
            $seat = $this->http->FindSingleNode("./td[normalize-space(.)][last()][preceding-sibling::td[normalize-space()][1][string-length(normalize-space(.))=3 or normalize-space(.)='NO']]",
                $root, true, "#^\s*(\d{1,3}[A-Z])\s*$#");

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            }

            $stops = $this->http->FindSingleNode("./following-sibling::tr[3]/td[" . $this->contains($this->t('Number of stops')) . "]/following-sibling::td[normalize-space(.)][1]", $root);

            if (!empty($stops) || $stops === '0') {
                $s->extra()
                    ->stops($stops);
            }
            $nonstop = $this->http->FindSingleNode("following-sibling::tr[position() < 5][" . $this->contains($this->t('Duration')) . "][1]/descendant::td[not(.//td) and " . $this->contains($this->t('Duration')) . "]/following-sibling::td[normalize-space(.)][1]",
                $root, true, '/\bnon[- ]?stop/i');

            if (!empty($nonstop)) {
                $s->extra()
                    ->stops(0);
            }
        }
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($relativeDate)) {
            return null;
        }
        $year = date("Y", $relativeDate);
        $in = [
            //17May
            '#^\s*(\d+)\s*([[:alpha:]]+)\s*$#u',
        ];
        $out = [
            '$1 $2',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        $date = EmailDateHelper::parseDateRelative($date, $relativeDate, true);

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSlash($field)
    {
        return preg_replace("/(.+)/", '/ $1', $field);
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
