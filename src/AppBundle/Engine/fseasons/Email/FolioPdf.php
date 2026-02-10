<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// This parser only for Four Seasons Hotels! Adding multi-prov is prohibited!

// parsers with similar formats: goldpassport/InvoicePDF, aceh/YourReservationPDF

class FolioPdf extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-486309198.eml, fseasons/it-490511591.eml, fseasons/it-495065490.eml, fseasons/it-502177788.eml, fseasons/it-495115654.eml, fseasons/it-505046875.eml, fseasons/it-493160216.eml, fseasons/it-496721518.eml, fseasons/it-497174725.eml, fseasons/it-497706265.eml, fseasons/it-498348256.eml, fseasons/it-498598553.eml, fseasons/it-503702484.eml, fseasons/it-67552307.eml, fseasons/it-139998016.eml, fseasons/it-123098916.eml, fseasons/it-113619924.eml, fseasons/it-505070215.eml, fseasons/it-505979078.eml, fseasons/it-504681368.eml, fseasons/it-511288479.eml, fseasons/it-512525444.eml, fseasons/it-516074414.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'checkIn'      => ['Chegada'],
            'checkOut'     => ['Saída'],
            'GUESTS'       => ['Adultos'],
            'Guest Name'   => ['Hóspede'],
            'Company Name' => ['Empresa'],
            'Date'         => ['Data'],
            // 'Reference'   => [''],
            'Description' => ['Descrição'],
            // 'Text'        => [''],
            'Room No.'    => ['Quarto'],
            'Page No.'    => ['Página', 'Página N.º'],
            'Folio No.'   => ['Número'],
            'A/R Number'  => ['N.º A/R'],
            'Cashier No.' => ['Caixa N.º'],
            'confNumber'  => ['Conf. N.º'],
            // 'Charges' => [''],
            'Balance'     => ['Saldo'],
            'TEL'         => ['TEL', 'Tel.'],
            'FAX'         => ['FAX', 'Fax.'],
        ],
        'it' => [
            'checkIn'     => ['Arrivo'],
            'checkOut'    => ['Partenza'],
            // 'GUESTS'      => [''],
            'Guest Name'  => ['Cliente'],
            // 'Company Name' => [''],
            'Date'        => ['Data'],
            // 'Reference'   => [''],
            'Description' => ['Descrizione'],
            // 'Text'        => [''],
            'Room No.'    => ['Camera'],
            'Page No.'    => ['Pagina'],
            // 'Folio No.'   => [''],
            // 'A/R Number'  => [''],
            // 'Cashier No.' => [''],
            // 'confNumber'  => [''],
            // 'Charges' => [''],
            // 'Balance' => [''],
            'TEL'         => ['TEL', 'Tel.', 'Telefono'],
            'FAX'         => ['FAX', 'Fax.'],
        ],
        'en' => [ // always last!
            'checkIn'     => ['Arrival Date', 'ARRIVAL DATE', 'Arrival', 'ARRIVAL'],
            'checkOut'    => ['Departure Date', 'DEPARTURE DATE', 'Departure', 'DEPARTURE'],
            'GUESTS'      => ['GUESTS', 'Adults'],
            'Guest Name'  => ['Guest Name', 'GUEST NAME'],
            // 'Company Name' => [''],
            'Date'        => ['Date', 'DATE'],
            'Reference'   => ['Reference', 'REFERENCE'],
            'Description' => ['Description', 'DESCRIPTION'],
            'Text'        => ['Text', 'TEXT'],
            'Room No.'    => ['Room No.', 'Room Number', 'Room', 'ROOM'],
            'Page No.'    => ['Page No.', 'PAGE'],
            'Folio No.'   => ['Folio No.', 'Folio Number'],
            // 'A/R Number'  => [''],
            'Cashier No.' => ['Cashier No.', 'CASHIER'],
            'confNumber'  => ['Conf. No.', 'Conf No', 'Confirm No.', 'Confirmation No.', 'Conf. Number', 'Confirmation #', 'CHECK#'],
            'Charges'     => ['Charges', 'Charge'],
            // 'Balance' => [''],
            'TEL'         => ['TEL', 'Tel.', 'TÉL', 'Tél.'],
            'FAX'         => ['FAX', 'Fax.'],
        ],
    ];

    private $subjects = [
        'en' => ['Guest Folio'],
    ];

    private $patterns = [
        'time'           => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'          => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName'  => '[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:alpha:]][-,.\'’[:alpha:] ]*?[[:alpha:]]',
        'travellerName3' => '[[:alpha:]][-,.\'’[:alpha:]\s]*[[:alpha:]]',
    ];

    private $hotelNameID = '';
    private $enDatesInverted = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fourseasons.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], 'Four Seasons') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = stripos($parser->getCleanFrom(), '@fourseasons.com') !== false
            || strpos($parser->getSubject(), 'Four Seasons') !== false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if (!$detectProvider
                && stripos($textPdf, 'www.fourseasons.com') === false
                && stripos($textPdf, '@fourseasons.com') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf, $parser->getSubject(), $parser->getCleanFrom());
            }
        }

        if (empty($this->hotelNameID)) {
            $this->logger->debug('Hotel could not be detected!');
        }

        $email->setType('FolioPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text, string $subject, ?string $from): void
    {
        if (stripos($text, 'Rodrigo da Fonseca') !== false) {
            $this->hotelNameID = 'Hotel Ritz Lisbon';
            $this->hotelRitzLisbon($email, $text);
        } elseif (stripos($text, ' Marunouchi') !== false) {
            $this->hotelNameID = 'Hotel Tokyo at Marunouchi';
            $this->hotelTokyoMarunouchi($email, $text);
        } elseif (stripos($subject, ' Hotel Singapore') !== false || stripos($text, ' 248646') !== false) {
            $this->hotelNameID = 'Hotel Singapore';
            $this->hotelTokyoMarunouchi($email, $text);
        } elseif (stripos($subject, ' Hotel Firenze') !== false || stripos($text, ' 50121') !== false) {
            $this->hotelNameID = 'Hotel Firenze';
            $this->hotelFirenze($email, $text);
        } elseif (stripos($subject, ' Hotel Taormina') !== false || stripos($text, ' PIAZZA DAN DOMENICO ') !== false) {
            $this->hotelNameID = 'Hotel Taormina';
            $this->hotelFirenze($email, $text);
        } elseif (stripos($subject, ' Hotel Milano') !== false || stripos($text, ' VIA GESÚ ') !== false) {
            $this->hotelNameID = 'Hotel Milano';
            $this->hotelFirenze($email, $text);
        } elseif (stripos($text, ' Cap Ferrat') !== false || stripos($text, ' Cap-Ferrat') !== false) {
            $this->hotelNameID = 'Grand Hotel du Cap Ferrat';
            $this->hotelCapFerrat($email, $text);
        } elseif (stripos($text, 'Jimbaran ') !== false || stripos($text, 'Jimbaran,') !== false) {
            $this->hotelNameID = 'Resort Bali at Jimbaran Bay';
            $this->hotelCapFerrat($email, $text);
        } elseif (stripos($text, 'Four Seasons Miami') !== false || stripos($text, 'Four Seasons Hotel Miami') !== false) {
            $this->hotelNameID = 'Hotel Miami';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons New Orleans') !== false || stripos($text, 'Four Seasons Hotel New Orleans') !== false) {
            $this->hotelNameID = 'Hotel New Orleans';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons Dubai International Financial Centre') !== false || stripos($text, 'Four Seasons Hotel Dubai International Financial Centre') !== false) {
            $this->hotelNameID = 'Hotel Dubai International Financial Centre';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons San Francisco at Embarcadero') !== false || stripos($text, 'Four Seasons Hotel San Francisco at Embarcadero') !== false) {
            $this->hotelNameID = 'Hotel San Francisco at Embarcadero';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons Hotel at The Surf Club') !== false) {
            $this->hotelNameID = 'Hotel at The Surf Club';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons Hotel Nashville') !== false) {
            $this->hotelNameID = 'Hotel Nashville';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons Abu Dhabi at Al Maryah Island') !== false || stripos($text, 'Four Seasons Hotel Abu Dhabi at Al Maryah Island') !== false) {
            $this->hotelNameID = 'Hotel Abu Dhabi at Al Maryah Island';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'chiangmai@fourseasons.com') !== false
            || stripos($text, 'Four Seasons Resort Chiang Mai') !== false || stripos($text, 'Four Seasons Chiang Mai') !== false
        ) {
            $this->hotelNameID = 'Resort Chiang Mai';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, ' Fort Lauderdale') !== false) {
            $this->hotelNameID = 'Fort Lauderdale';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '/napavalley') !== false) {
            $this->hotelNameID = 'Napa Valley';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '/siliconvalley') !== false) {
            $this->hotelNameID = 'Silicon Valley at East Palo Alto';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '/newyorkdowntown') !== false) {
            $this->hotelNameID = 'Hotel New York Downtown';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '/losangeles') !== false) {
            $this->hotelNameID = 'Hotel Los Angeles at Beverly Hills';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '99 UNION STREET') !== false) {
            $this->hotelNameID = 'Hotel Seattle';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '120 EAST DELAWARE PLACE') !== false) {
            $this->hotelNameID = 'Hotel Chicago';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '75 FOURTEENTH STREET') !== false) {
            $this->hotelNameID = 'Hotel Atlanta';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '3960 LAS VEGAS') !== false) {
            $this->hotelNameID = 'Hotel Las Vegas';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '1300 LAMAR STREET') !== false) {
            $this->hotelNameID = 'Hotel Houston';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '60 YORKVILLE AVENUE') !== false) {
            $this->hotelNameID = 'Hotel Toronto';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '1111 14TH ST') !== false) {
            $this->hotelNameID = 'Hotel Denver';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Westlake Village') !== false) {
            $this->hotelNameID = 'Hotel Westlake Village';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, ' Minneapolis') !== false || stripos($text, ',Minneapolis') !== false) {
            $this->hotelNameID = 'Hotel Minneapolis';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, ' Sydney') !== false || stripos($text, ',Sydney') !== false) {
            $this->hotelNameID = 'Hotel Sydney';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, ' Mauritius') !== false || stripos($text, ',Mauritius') !== false) {
            $this->hotelNameID = 'Mauritius at Anahita';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, '/johannesburg') !== false || stripos($text, ' Johannesburg') !== false || stripos($text, ',Johannesburg') !== false) {
            $this->hotelNameID = 'Hotel The Westcliff, Johannesburg';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Maui') !== false || stripos($text, ',Maui') !== false) {
            $this->hotelNameID = 'Resort Maui at Wailea';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'QC H3G 1Z5') !== false || stripos($text, 'QC, H3G 1Z5') !== false) {
            $this->hotelNameID = 'Hotel Montreal';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Cairo at Nile Plaza') !== false) {
            $this->hotelNameID = 'Hotel Cairo at Nile Plaza';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Resort Hualalai') !== false || stripos($text, ' 96740') !== false) {
            $this->hotelNameID = 'Resort Hualalai';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Hotel Mexico City') !== false || stripos($text, ' 06600') !== false) {
            $this->hotelNameID = 'Hotel Mexico City';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Hotel Seoul') !== false || stripos($text, ' 03183') !== false) {
            $this->hotelNameID = 'Hotel Seoul';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, 'Four Seasons Resort Seychelles') !== false) {
            $this->hotelNameID = 'Four Seasons Resort Seychelles';
            $this->hotelMiami($email, $text);
        } elseif (stripos($text, ' www.fourseasons.com/boston') !== false) {
            $this->hotelNameID = 'Hotel Boston';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, ' Hotel One Dalton Street, Boston') !== false || stripos($text, ' 1 Dalton Street') !== false) {
            $this->hotelNameID = 'Hotel One Dalton Street, Boston';
            $this->hotelMiami($email, $text);
        } elseif (stripos($subject, 'Four Seasons Resort Whistler') !== false
            || stripos($subject, 'Four Seasons Resort & Residences Whistler') !== false
            || stripos($subject, 'Four Seasons Resort and Residences Whistler') !== false
        ) {
            $this->hotelNameID = 'Resort Whistler';
            $this->hotelMiami($email, $text);
        } elseif (preg_match("/Four Seasons Hotel Philadelphia at Comcast Center/u", $subject, $m)) {
            $this->hotelNameID = 'Hotel Philadelphia at Comcast Center';
            $this->hotelMiami($email, $text);
        }

        if ($this->hotelNameID) {
            $this->logger->debug('Hotel detected: ' . $this->hotelNameID);
        }
    }

    private function hotelRitzLisbon(Email $email, string $text): void
    {
        // examples: it-504681368.eml
        $this->logger->debug('Method used: ' . __FUNCTION__ . '()');

        $headerText = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Date'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?(?:[ ]{2,}IVA)?[ ]{2,}{$this->opt($this->t('Description'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[ ]{2}/u", $text);

        $tablePos = [0];

        $tablePosTd2 = [];

        if (preg_match("/^(.{50,}[ ]{2}){$this->opt($this->t('Guest Name'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?(?:[ ]{2}| ?:|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{50,}[ ]{2}){$this->opt($this->t('A/R Number'))}(?: ?\/ ?[- .[:alpha:]\/]{3,15})?(?:[ ]{2}| ?:|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{50,}[ ]{2}){$this->opt($this->t('Company Name'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?(?:[ ]{2}| ?:|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        sort($tablePosTd2);

        if (count($tablePosTd2) > 0) {
            $tablePos[] = $tablePosTd2[0];
        }

        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong header table!');

            return;
        }

        $h = $email->add()->hotel();

        if (preg_match("/^[ ]*({$this->opt($this->t('Room No.'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[:\s]+[A-Z]?\d{1,5}[A-Z]?)$/mu", $table[0], $m)) {
            $room = $h->addRoom();
            $room->setDescription(preg_replace('/\s+/', ' ', $m[1]));
        }

        $checkInVal = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[:\s]+(\S.{4,}\S)\n+[ ]*{$this->opt($this->t('checkOut'))}/mu", $table[0]);
        $checkOutVal = $this->re("/^[ ]*{$this->opt($this->t('checkOut'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[:\s]+(\S.{4,}\S)\n+[ ]*{$this->opt($this->t('GUESTS'))}/mu", $table[0]);
        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOut = strtotime($this->normalizeDate($checkOutVal));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        // 2 Cria./Child. : 0
        $guestsVal = $this->re("/^[ ]*{$this->opt($this->t('GUESTS'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[:\s]+(.*\d.*)\n+[ ]*{$this->opt($this->t('confNumber'))}/mu", $table[0]);

        if (preg_match("/\b(\d{1,3})[ ]*Cria\./i", $guestsVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\bChild\.[: ]+(\d{1,3})\b/i", $guestsVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))}(?: ?\/ ?[- .[:alpha:]]{1,15}?)?)[:\s]+([-A-Z\d]{5,15})$/mu", $table[0], $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $paymentText = $this->re("/\n([ ]*{$this->opt($this->t('Date'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?(?:[ ]{2,}IVA)?[ ]{2,}{$this->opt($this->t('Description'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[ ]{2}.+)$/s", $text);

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Total'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?[ ]+\d[,.‘\'\d]*[ ]{2,}\d[,.‘\'\d]*[ ]{2,}(\d[,.‘\'\d]*)$/mu", $paymentText, $m)) {
            $totalPrice = $m[1];
        } else {
            $totalPrice = null;
        }

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*$/u", $totalPrice, $matches)) {
            // 1,907.00
            $currencyCode = $this->re("/ {$this->opt($this->t('Balance'))}(?: ?\/ ?[- .[:alpha:]]{1,15})?\n{1,2}.+[ ]{2}[A-Z]{3}[ ]{2,}[A-Z]{3}[ ]{2,}([A-Z]{3})\n/", $paymentText);
            $h->price()->currency($currencyCode, false, true)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/\n[ ]*(?:Sede ?:[ ]*)?(?<address>[^:\n]*(?:Rodrigo da Fonseca)[^:\n]*?)[-, ]+(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/iu", $text, $matches)) {
            if ($this->hotelNameID === 'Hotel Ritz Lisbon') {
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            }
            $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));

            if (preg_match("/\b{$this->opt($this->t('TEL'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }
        }
    }

    private function hotelFirenze(Email $email, string $text): void
    {
        // examples: it-505070215.eml, it-505979078.eml
        $this->logger->debug('Method used: ' . __FUNCTION__ . '()');

        $headerText = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt($this->t('Room No.'))})?[ ]{2,}{$this->opt($this->t('Description'))}[ ]{2}/", $text);

        $tablePos = [0];

        if (preg_match("/^(.{40,}[ ]{2})Intestatario$/m", $headerText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong header table!');

            return;
        }

        $h = $email->add()->hotel();

        if (preg_match("/^[ ]*{$this->opt($this->t('Guest Name'))}\n+[ ]*({$this->patterns['travellerName']})\n+[ ]*{$this->opt($this->t('Room No.'))}/mu", $table[0], $m)) {
            $h->general()->traveller($m[1], true);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Room No.'))}[ :]+[A-Z]?\d{1,5}[A-Z]?)$/mu", $table[0], $m)) {
            $room = $h->addRoom();
            $room->setDescription(preg_replace('/\s+/', ' ', $m[1]));
        }

        $this->enDatesInverted = $this->hotelNameID === 'Hotel Firenze'
            || $this->hotelNameID === 'Hotel Taormina'
            || $this->hotelNameID === 'Hotel Milano'
        ;

        $checkInVal = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}[:\s]+(\S.{4,}\S)\n+[ ]*{$this->opt($this->t('checkOut'))}/mu", $table[0]);
        $checkOutVal = $this->re("/^[ ]*{$this->opt($this->t('checkOut'))}[:\s]+(\S[^:\n]{4,20}\S)$/mu", $table[0]);
        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOut = strtotime($this->normalizeDate($checkOutVal));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        $paymentText = $this->re("/\n([ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt($this->t('Room No.'))})?[ ]{2,}{$this->opt($this->t('Description'))}[ ]{2}.+)$/s", $text);

        if (preg_match("/^.{70,}[ ]{2}Totale Importo[ ]{2,20}Totale Credito\n{1,2}.{70,}[ ]{2}(\d[,.‘\'\d]*)[ ]{2,20}\d[,.‘\'\d]*$/mu", $paymentText, $m)) {
            $totalPrice = $m[1];
        } else {
            $totalPrice = null;
        }

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*$/u", $totalPrice, $matches)) {
            // 4,600.00
            $currencyCode = $this->re("/[ ]{2}Amount[ ]{1,10}([A-Z]{3})[ ]{1,30}\d/", $paymentText);
            $h->price()->currency($currencyCode, false, true)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/\n[ ]*(?<name>(?:Four Seasons )?{$this->opt($this->hotelNameID)})[ ]*-[ ]*(?<address>[^:\n]{3,}?)[ ]*-[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)) {
            // it-505070215.eml
            $h->hotel()->name($matches['name'])->address($matches['address']);

            if (preg_match("/\b{$this->opt($this->t('TEL'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }
        } elseif (preg_match("/\n[ ]*(?<address>[^:\n]*(?:\b98039\b|\bVIA GESÚ|\bVIA GESU)[^:\n]*?)[ ]*-[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/iu", $text, $matches)) {
            if ($this->hotelNameID === 'Hotel Taormina') {
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            } elseif ($this->hotelNameID === 'Hotel Milano') {
                // it-505979078.eml
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            }
            $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));

            if (preg_match("/\b{$this->opt($this->t('TEL'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }
        }
    }

    private function hotelMiami(Email $email, string $text): void
    {
        // examples: it-495065490.eml, it-502177788.eml, it-495115654.eml, it-505046875.eml, it-493160216.eml, it-496721518.eml, it-497174725.eml, it-497706265.eml, it-498348256.eml, it-498598553.eml, it-503702484.eml, it-67552307.eml, it-139998016.eml, it-123098916.eml, it-512525444.eml, it-516074414.eml
        $this->logger->debug('Method used: ' . __FUNCTION__ . '()');

        $headerText = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt(['‫ﺍﻟﺘﺎﺭﻳﺦ‬'])})?[ ]{2,}(?:{$this->opt($this->t('Description'))}|{$this->opt($this->t('Text'))})[ ]{2}/u", $text);

        $tablePos = [0];

        $tablePosTd2 = [];

        if (preg_match("/^(.{30,}[ ]{2})(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkIn'))}(?:[ ]{2}| ?:| \d|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{30,}[ ]{2})(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkOut'))}(?:[ ]{2}| ?:| \d|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{30,}[ ]{2})(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('Room No.'))}(?:[ ]{2}| ?:| [A-Z\d]|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{30,}[ ]{2})(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('confNumber'))}(?:[ ]{2}| ?:| [A-Z\d]|$)/mu", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        sort($tablePosTd2);

        if (count($tablePosTd2) > 0) {
            $tablePos[] = $tablePosTd2[0];
        }

        // it-498598553.eml, it-516074414.eml
        $headerText = preg_replace("/^(?:{$this->opt($this->t('Tax Registration Number'))} ?[:]+ ?[-A-Z\d]{3,32}\n+[ ]*)?{$this->opt($this->t('TAX INVOICE'))}\n/i", '', $headerText);

        if (!empty($tablePos[1])) {
            // it-493160216.eml
            $headerText = preg_replace("/^((?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('Room No.'))})/u", str_repeat(' ', $tablePos[1]) . '$1', $headerText);
        }

        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong header table!');

            return;
        }

        $h = $email->add()->hotel();

        $table[0] = preg_replace("/^\s*{$this->opt($this->t('Company Name'))}[ ]*[:]+.*\n/", '', $table[0]); // it-496721518.eml

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Guest Name'))}[ ]*[:]+[ ]{0,10}({$this->patterns['travellerName2']})(?:[ ]{2}|$)/mu", $headerText, $m) // it-498598553.eml, it-512525444.eml
            || preg_match("/[ ]*({$this->patterns['travellerName']})\n\n\s*INVOICE/u", $table[0], $m) && !preg_match("/^((?:MISS|MRS|MR|MS|Ms\.))/iu", $headerText)
            || preg_match("/^\n{0,4}[ ]*({$this->patterns['travellerName']})(?:\n|$)/u", $table[0], $m) && !preg_match("/[ ]{2}/", $m[1])
        ) {
            $travellersVal = $m[1];
        } else {
            $travellersVal = '';
        }

        $travellers = empty($travellersVal) ? [] : preg_split("/\s+{$this->opt($this->t('and'))}\s+/i", $travellersVal);
        $h->general()->travellers(array_filter($travellers, function ($item) {
            return !preg_match("/^(?:MISS|MRS|MR|MS)[\s.]*$/i", $item);
        }), true);

        $this->enDatesInverted = $this->hotelNameID === 'Hotel Sydney'
            || $this->hotelNameID === 'Hotel Cairo at Nile Plaza'
            || $this->hotelNameID === 'Mauritius at Anahita'
            || $this->hotelNameID === 'Hotel The Westcliff, Johannesburg'
            || $this->hotelNameID === 'Resort Chiang Mai'
            || $this->hotelNameID === 'Hotel Chicago'
            || $this->hotelNameID === 'Hotel Toronto'
            || $this->hotelNameID === 'Hotel Abu Dhabi at Al Maryah Island'
            || $this->hotelNameID === 'Hotel Dubai International Financial Centre'
        ;

        $checkInVal = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}[:\s]+(\d{1,2}\/\d{1,2}\/\d{2,4})[ ]+\d.+\d[ ]*{$this->opt('‫ﺗﺎﺭﻳﺦ ﺍﻟﻮﺹ‬')}$/mu", $table[1])
            ?? $this->re("/^[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkIn'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[:\s]+(\S.{4,}\S)\n+[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkOut'))}/mu", $table[1])
        ;
        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOutVal = $this->re("/^[ ]*{$this->opt($this->t('checkOut'))}[:\s]+(\d{1,2}\/\d{1,2}\/\d{2,4})[ ]+\d.+\d[ ]*{$this->opt('‫ﺗﺎﺭﻳﺦ ﺍﻟﻤﻎ‬')}$/mu", $table[1])
            ?? $this->re("/^[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkOut'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[:\s]+(\S.{4,}\S)\n+[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('Room No.'))}/mu", $table[1])
            ?? $this->re("/^[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?{$this->opt($this->t('checkOut'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[:\s]+(\S[^:\n]{4,20}\S)$/mu", $table[1])
        ;
        $checkOut = strtotime($this->normalizeDate($checkOutVal));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        if (preg_match("/^[ ]*(?:[- .[:alpha:]]{1,15} ?\/ ?)?({$this->opt($this->t('Room No.'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[:\s]+[A-Z]?\d{1,5}[A-Z]?)(?:[ ]+\d|$)/mu", $table[1], $m)) {
            $room = $h->addRoom();
            $room->setDescription(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,20})[ ]+[A-Z\d].{4,}{$this->opt('‫ﺭﻗﻢ ﺍﻟﺤﺞ‬')}$/mu", $table[1], $m)
            || preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,20})$/m", $table[1], $m)
            || preg_match("/[ ]*({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,20})/m", $text, $m)
        ) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $paymentText = $this->re("/\n([ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt(['‫ﺍﻟﺘﺎﺭﻳﺦ‬'])})?[ ]{2,}(?:{$this->opt($this->t('Description'))}|{$this->opt($this->t('Text'))})[ ]{2}.+)$/su", $text);

        if (preg_match("/^[ ]*{$this->opt($this->t('Total'))}[ ]+(\d[,.‘\'\d]* ?[A-Z]{3})(?:[ ]{2}|$)/mu", $paymentText, $m) && !preg_match("/[ ]{2}/", $m[1])
            || preg_match("/^[ ]*{$this->opt($this->t('Total'))}(?:[ ]+[A-Z]{3})?[ ]+(.*?\d.*?)(?:[ ]{2}|$)/m", $paymentText, $m) && !preg_match("/[ ]{2}/", $m[1])
            || preg_match("/\n[ ]+(\d[,.‘\'\d]*)[ ]{2,}\d[,.‘\'\d]*(?:\n[^\d\n]*)?\n[ ]*{$this->opt($this->t('Total'))}[ ]{2,}(?:{$this->opt($this->t('Balance'))}|{$this->opt('‫ﺍﻟﺮﺻﻴﺪ ﺍﻟﻤﺘﺐ‬')})/mu", $paymentText, $m) // it-493160216.eml, it-516074414.eml
        ) {
            $totalPrice = $m[1];
        } else {
            $totalPrice = null;
        }

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*$/u", $totalPrice, $matches)) {
            // 1,441.48
            $currencyCode = $this->re("/.{20} {$this->opt($this->t('Charges'))}[ ]+{$this->opt($this->t('Credits'))}\n{1,2}[ ]{20,}(?-i)([A-Z]{3})[ ]+[A-Z]{3}\n/i", $paymentText) // it-493160216.eml
                ?? $this->re("/.{20} {$this->opt($this->t('Debit'))} (?-i)([A-Z]{3})(?i)[ ]+{$this->opt($this->t('Credits'))}(?: |\n)/i", $paymentText) // it-498598553.eml
                ?? $this->re("/^[ ]*{$this->opt($this->t('Total Amount'))} - ([A-Z]{3})(?: |$)/m", $paymentText) // it-496721518.eml
                ?? $this->re("/^[ ]*{$this->opt($this->t('Total'))}[ ]+([A-Z]{3})[ ]+\d/m", $paymentText) // it-498348256.eml
                ?? $this->re("/^[ ]*{$this->opt($this->t('Balance'))}[ ]+\d[,.‘\'\d]*[ ]*([A-Z]{3})$/mu", $paymentText) // it-139998016.eml
                ?? $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Approval Amount'))}[: ]+([A-Z]{3})[ ]*\d[,.‘\'\d]*$/mu", $paymentText) // it-516074414.eml
            ;
            $h->price()->currency($currencyCode, false, true)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        } elseif (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)\s*$/u", $totalPrice, $matches)
            || preg_match("/^\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*$/u", $totalPrice, $matches)
        ) {
            // 1,441.48 AUD    |    AUD 1,441.48
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        $this->logger->debug($text);

        $hotelName = preg_match("/^[ ]*{$this->opt($this->t('Thank you for staying at'))}(?:\s+the)?\s+(.{3,70}?)[ ,.;?!]*$/im", $text, $m)
            && preg_match("/(?:\bMIAMI\b|\bDUBAI\b|\bMAURITIUS\b|\bJOHANNESBURG\b|\bABU DHABI\b)/i", $m[1]) ? $m[1] : null;

        if (($this->hotelNameID === 'Resort Whistler' || $this->hotelNameID === 'Hotel Cairo at Nile Plaza')
            && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())
        ) {
            // it-493160216.eml, it-512525444.eml
            $h->hotel()->chain('Four Seasons')->name($this->hotelNameID)->noAddress();

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }

            return;
        } elseif (preg_match("/\n[ ]*(?<address>[^:\n]*\b1 Dalton Street\b[^:\n]*) ?[\/]+ ?(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>[^:\n]*(?:\bJOHANNESBURG\b|\bSILVERADO\b|\b96740\b|\b06600\b|\b03183\b|\b99 UNION STREET\b|\b120 EAST DELAWARE PLACE\b|\b75 FOURTEENTH STREET\b|\b27 BARCLAY STREET\b|\b1300 LAMAR STREET\b|\b60 YORKVILLE AVENUE\b|\b300 SOUTH DOHENY DRIVE\b|\b1111 14TH ST|\bEAST PALO ALTO\b|\bWESTLAKE VILLAGE\b)[^:\n]*)\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>[^:\n]*(?:\b3960 LAS VEGAS\b)[^:\n]*)\n[ ]*(?<phones>(?:ACCOUNTING|HOTEL|FAX)\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>.*(?:\bSYDNEY\b|\bDUBAI\b).*)\n.*?\b(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>[^:\n]*\bMAUI\b[^:\n]*)[ ]+(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>.*\b525\b.*\s.*\b33304\b.*)(?:\n|$)/", $text, $matches)
            || preg_match("/\s+(?<address>One North.+)\n\s*(?<phones>TEL\:\s*.+)\s+http/", $text, $matches)
            || preg_match("/(?<address>\d+.+U\.S\.A\.)\n+\s+Tel\:\s*(?<phones>.+)\s+Fax\:\s+(?<fax>.+\d)/", $text, $matches)
        ) {
            if (empty($h->getHotelName()) && $hotelName) {
                $h->hotel()->name($hotelName);
            } elseif ($this->hotelNameID === 'Napa Valley') {
                // it-502177788.eml
                $h->hotel()->chain('Four Seasons Resort and Residences')->name($this->hotelNameID);
            } elseif ($this->hotelNameID === 'Fort Lauderdale') {
                // it-497174725.eml
                $h->hotel()->chain('Four Seasons Hotel and Residences')->name($this->hotelNameID);
            } elseif ($this->hotelNameID === 'Hotel Sydney' // it-505046875.eml
                || $this->hotelNameID === 'Resort Hualalai' // it-67552307.eml
                || $this->hotelNameID === 'Resort Maui at Wailea'
                || $this->hotelNameID === 'Hotel Mexico City' // it-139998016.eml
                || $this->hotelNameID === 'Hotel Seoul' // it-123098916.eml
                || $this->hotelNameID === 'Hotel Seattle'
                || $this->hotelNameID === 'Hotel Chicago'
                || $this->hotelNameID === 'Hotel Boston'
                || $this->hotelNameID === 'Hotel Atlanta'
                || $this->hotelNameID === 'Hotel Houston'
                || $this->hotelNameID === 'Hotel Toronto'
                || $this->hotelNameID === 'Hotel Denver'
                || $this->hotelNameID === 'Hotel Las Vegas'
                || $this->hotelNameID === 'Hotel Westlake Village'
                || $this->hotelNameID === 'Hotel Los Angeles at Beverly Hills'
                || $this->hotelNameID === 'Hotel New York Downtown'
                || $this->hotelNameID === 'Hotel One Dalton Street, Boston'
                || $this->hotelNameID === 'Silicon Valley at East Palo Alto'
                || $this->hotelNameID === 'Hotel Philadelphia at Comcast Center'
            ) {
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            }
            $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));

            if (!array_key_exists('phones', $matches)) {
                $matches['phones'] = '';
            }

            if (preg_match("/\b{$this->opt($this->t('TEL'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)
                || preg_match("/\bHOTEL[ :]+({$this->patterns['phone']})(?:[ ,]|$)/i", $matches['phones'], $m)
            ) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }

            return;
        } elseif (preg_match("/\n[ ]*(?<name>.*\bMINNEAPOLIS\b.*)\n[ ]*(?<address>.*\bMINNEAPOLIS\b.*)\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<name>(?:Four Seasons )?{$this->opt($hotelName ?? $this->hotelNameID)})[ ]*,[ ]*(?<address>[^:\n]{3,})\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<name>Four Seasons (?:Hotel )?(?:Nashville))\n[ ]*(?<address>[^:\n]*(?:\b37201\b)[^:\n]*)\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<name>Four Seasons (?:Hotel )?(?:New Orleans|Hotel at The Surf Club|San Francisco at Embarcadero))\n[ ]*(?<address>[^:\n]*(?:\b70130\b|\b94104\b|\b33154\b)[^:\n]*)(?:\n|$)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<name>Four Seasons (?:Hotel|Hôtel) (?:Montreal|Montréal))[ ]*,[ ]*(?<address>[^:\n]{3,})\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/iu", $text, $matches)
            || preg_match("/\n[ ]*(?<name>Four Seasons.+)\s*at\s*(?<address>[^:\n]{3,})\n[ ]*/iu", $text, $matches)
        ) {
            // it-495115654.eml, it-496721518.eml, it-497706265.eml, it-498348256.eml, it-503702484.eml
            $h->hotel()->name($matches['name'])->address($matches['address']);

            if (!array_key_exists('phones', $matches)) {
                $matches['phones'] = '';
            }

            if (preg_match("/\b{$this->opt($this->t('TEL'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}(?:[ ]+[- .[:alpha:]]{1,15}[ ]+:)?[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }

            return;
        }

        $h->hotel()->name($hotelName);

        if (!empty($h->getHotelName()) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $h->hotel()->noAddress();
        }
    }

    private function hotelCapFerrat(Email $email, string $text): void
    {
        // examples: it-490511591.eml, it-511288479.eml
        $this->logger->debug('Method used: ' . __FUNCTION__ . '()');

        $headerText = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Date'))}[ ]{2,}(?:Texte ?\/ ?)?{$this->opt($this->t('Text'))}[ ]{2}/", $text)
            ?? $this->re("/^([\s\S]+?)\n+.{40,}[ ]{2}{$this->opt($this->t('Date'))}[ ]{2,}{$this->opt($this->t('Charges'))}[ ]{2}/", $text) // it-511288479.eml
        ;

        $h = $email->add()->hotel();

        $traveller = preg_match("/^[ ]*{$this->opt($this->t('INFORMATION INVOICE'))}[ ]{2,}({$this->patterns['travellerName']})$/mu", $headerText, $m)
        && !preg_match('/[ ]{2}/', $m[1]) ? $m[1] : null;

        if ($this->hotelNameID !== 'Resort Bali at Jimbaran Bay') {
            $h->general()->traveller($traveller, true);
        }

        $tablePos = [0];

        $tablePosTd2 = [];

        if (preg_match_all("/^(.{70,}?[ ]{2})\S/m", $headerText, $td2Matches)) {
            foreach ($td2Matches[1] as $m) {
                $tablePosTd2[] = mb_strlen($m);
            }
        }

        sort($tablePosTd2);

        if (count($tablePosTd2) > 0) {
            $tablePos[] = $tablePosTd2[0];
        }

        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) === 2) {
            $headerText = implode("\n\n", $table);
        }

        if (preg_match("/(?:^[ ]*|\/ ?)({$this->opt($this->t('Room No.'))}\s+[A-Z]?\d{1,5}[A-Z]?)$/m", $headerText, $m)
            || preg_match("/(?:^[ ]*|\/ ?)({$this->opt($this->t('Villa'))}\s+[A-Z]?\d{1,6}[A-Z]?)$/m", $headerText, $m) // it-511288479.eml
        ) {
            $room = $h->addRoom();
            $room->setDescription(preg_replace('/\s+/', ' ', $m[1]));
        }

        $this->enDatesInverted = $this->hotelNameID === 'Resort Bali at Jimbaran Bay';

        $checkInVal = $this->re("/(?:^[ ]*|\/ ?){$this->opt($this->t('checkIn'))}\s+(\S.{4,}?\S)\n+(?:[ ]*|.+\/ ?){$this->opt($this->t('checkOut'))}/m", $headerText);
        $checkOutVal = $this->re("/(?:^[ ]*|\/ ?){$this->opt($this->t('checkOut'))}\s+(\S.{4,}?\S)\n+(?:[ ]*|.+\/ ?){$this->opt($this->t('Page No.'))}/m", $headerText)
            ?? $this->re("/(?:^[ ]*|\/ ?){$this->opt($this->t('checkOut'))}\s+(\S.{4,}?\S)\n+(?:[ ]*|.+\/ ?){$this->opt($this->t('Villa'))}/m", $headerText) // it-511288479.eml
        ;
        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOut = strtotime($this->normalizeDate($checkOutVal));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        if (preg_match("/(?:^[ ]*|\/ ?)({$this->opt($this->t('confNumber'))})\s+([-A-Z\d]{5,20})$/m", $headerText, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $paymentText = $this->re("/\n([ ]*{$this->opt($this->t('Date'))}[ ]{2,}(?:Texte ?\/ ?)?{$this->opt($this->t('Text'))}[ ]{2}.+)$/s", $text)
            ?? $this->re("/\n([^\n]{40,}[ ]{2}{$this->opt($this->t('Date'))}[ ]{2,}{$this->opt($this->t('Charges'))}[ ]{2}.+)$/s", $text) // it-511288479.eml
        ;

        if (preg_match("/^[ ]*{$this->opt($this->t('Total in'))}[ ]+(\S.*?)[ ]{2,}(.+?)(?:[ ]{2}|$)/m", $paymentText, $m)) {
            // it-511288479.eml
            $currency = $this->normalizeCurrency($m[1]);
            $totalPrice = $m[2];
        } else {
            // it-490511591.eml
            $currency = null;
            $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total'))}[ ]+(.+?)(?:[ ]{2}|$)/m", $paymentText);
        }

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $totalPrice, $matches)) {
            // 3,238.16
            $balance = $this->re("/(?:[ ]{2}|\/ ?){$this->opt($this->t('Balance'))}[ ]+(\S.{0,30})$/m", $paymentText);

            if (preg_match('/^[A-Z]{3}$/', $currency)) {
                $currencyCode = $currency;
            } else {
                $currencyCode = $this->re("/^\s*\d[,.‘\'\d ]*?[ ]*([A-Z]{3})\s*$/u", $balance)
                    ?? $this->re("/^\s*([A-Z]{3})[ ]*\d[,.‘\'\d ]*\s*$/u", $balance);
            }

            $h->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currencyCode);
        }

        $hotelName = preg_match("/^[ ]*{$this->opt($this->t('Thank you for staying at'))}(?:\s+the)?\s+(.{3,70}?)[ ,.;?!]*$/im", $text, $m)
            && preg_match("/\bCAP[- ]+FERRAT\b/i", $m[1]) ? $m[1] : null;

        if (preg_match("/\n[ ]*(?<address>.*\bCAP[- ]+FERRAT\b.*?)[ ]*-[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)
            || preg_match("/\n[ ]*(?<address>.*\bJIMBARAN\b.*)\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $headerText, $matches)
        ) {
            if (empty($h->getHotelName()) && $hotelName) {
                $h->hotel()->name($hotelName);
            } elseif ($this->hotelNameID === 'Resort Bali at Jimbaran Bay') {
                // it-511288479.eml
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            }
            $h->hotel()->address($matches['address']);

            if (preg_match("/\b{$this->opt($this->t('TEL'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }

            return;
        }

        $h->hotel()->name($hotelName);
    }

    private function hotelTokyoMarunouchi(Email $email, string $text): void
    {
        // examples: it-486309198.eml, it-113619924.eml
        $this->logger->debug('Method used: ' . __FUNCTION__ . '()');

        $headerText = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt($this->t('Reference'))})?[ ]{2,}{$this->opt($this->t('Description'))} /", $text);

        $tablePos = [0];

        $tablePosTd2 = [];

        if (preg_match("/^(.{15,}[ ]{2}){$this->opt($this->t('Guest Name'))}(?:[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{15,}[ ]{2}){$this->opt($this->t('Address'))}(?:[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd2[] = mb_strlen($matches[1]);
        }

        sort($tablePosTd2);

        if (count($tablePosTd2) > 0) {
            $tablePos[] = $tablePosTd2[0];
        }

        $tablePosTd3 = [];

        if (preg_match("/^(.{50,} ){$this->opt($this->t('Page No.'))}(?:[ ]*:|[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd3[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{50,} ){$this->opt($this->t('Folio No.'))}(?:[ ]*:|[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd3[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{50,} ){$this->opt($this->t('A/R Number'))}(?:[ ]*:|[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd3[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{50,} ){$this->opt($this->t('Cashier No.'))}(?:[ ]*:|[ ]{2}|$)/m", $headerText, $matches)) {
            $tablePosTd3[] = mb_strlen($matches[1]);
        }

        sort($tablePosTd3);

        if (count($tablePosTd3) > 0) {
            $tablePos[] = $tablePosTd3[0];
        }

        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) !== 3) {
            $this->logger->debug('Wrong header table!');

            return;
        }

        $h = $email->add()->hotel();

        if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('Reg. No.'))})[ :]+([-A-Z\d]{5,15})(?:[ ]{2}|$)/m", $text, $m)) {
            // it-113619924.eml
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Room No.'))}\n+[ ]*[A-Z]?\d{1,5}[A-Z]?)$/m", $table[0], $m)) {
            $room = $h->addRoom();
            $room->setDescription(preg_replace('/\s+/', ' ', $m[1]));
        }

        $checkInVal = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}\n+[ ]*(.{6,})$/m", $table[0]);
        $checkOutVal = $this->re("/^[ ]*{$this->opt($this->t('checkOut'))}\n+[ ]*(.{6,})$/m", $table[0]);
        $checkIn = strtotime($this->normalizeDate($checkInVal));
        $checkOut = strtotime($this->normalizeDate($checkOutVal));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        $traveller = $this->re("/^[ ]*{$this->opt($this->t('Guest Name'))}\n+[ ]*({$this->patterns['travellerName3']})\n+[ ]*{$this->opt($this->t('Address'))}/mu", $table[1])
            ?? $this->re("/^[ ]*{$this->opt($this->t('Guest Name'))}\n[ ]*({$this->patterns['travellerName']})$/mu", $table[1])
        ;
        $h->general()->traveller(preg_replace('/\s+/', ' ', $traveller), true);

        if (preg_match("/^[ ]*{$this->opt($this->t('GUESTS'))}[: ]+(\d{1,3})$/m", $table[2], $m)) {
            $h->booked()->guests($m[1]);
        }

        $paymentText = $this->re("/\n([ ]*{$this->opt($this->t('Date'))}(?:[ ]{2,}{$this->opt($this->t('Reference'))})?[ ]{2,}{$this->opt($this->t('Description'))}[ ]{2}.+)$/s", $text);
        $totalPrice = $this->re("/^[ ]{50,}{$this->opt($this->t('Total'))}[ ]+(.+?)(?:[ ]{2}|$)/m", $paymentText);

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*?)\s*$/u", $totalPrice, $matches)) {
            // 761,543
            $currencyCode = $this->re("/^[ ]{50,}{$this->opt($this->t('Balance'))}[ ]+([A-Z]{3})(?:[ ]{2}|$)/m", $paymentText);
            $h->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currencyCode);
        }

        if (preg_match("/(?:^|\n\n)[ ]*(?<address>[^:\n]*(?:MARUNOUCHI|\b248646\b)[^:\n]*)\n[ ]*(?<phones>(?:{$this->opt($this->t('TEL'))}|{$this->opt($this->t('FAX'))})\b.*)/i", $text, $matches)) {
            if ($this->hotelNameID === 'Hotel Tokyo at Marunouchi') {
                // it-486309198.eml
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            } elseif ($this->hotelNameID === 'Hotel Singapore') {
                // it-113619924.eml
                $h->hotel()->chain('Four Seasons')->name($this->hotelNameID);
            }
            $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));

            if (preg_match("/\b{$this->opt($this->t('TEL'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/\b{$this->opt($this->t('FAX'))}[ :]+({$this->patterns['phone']})(?:[ ,]|$)/iu", $matches['phones'], $m)) {
                $h->hotel()->fax($m[1]);
            }

            if (count($h->getConfirmationNumbers()) === 0) {
                $h->general()->noConfirmation();
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['checkIn']) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 27/08/2023
            '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})$/', // always first!
            // 27/08/23
            '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{2})$/',
            // 15-02-23    |    15.02.23
            '/^(\d{1,2}) ?[-.] ?(\d{1,2}) ?[-.] ?(\d{2})\b/',
        ];
        $out[0] = $this->enDatesInverted === true ? '$2/$1/$3' : '$1/$2/$3';
        $out[1] = $this->enDatesInverted === true ? '$2/$1/20$3' : '$1/$2/20$3';
        $out[2] = $this->enDatesInverted === true ? '$1/$2/20$3' : '$2/$1/20$3';

        return preg_replace($in, $out, $text);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'IDR' => ['Rupiah'],
            'INR' => ['Rupee'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
