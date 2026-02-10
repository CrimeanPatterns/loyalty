<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "expedia/it-109905981.eml, expedia/it-110131981.eml, expedia/it-352018310.eml, expedia/it-379441106.eml, expedia/it-399720563.eml, expedia/it-400956974.eml, expedia/it-400968939.eml, expedia/it-420468517.eml, expedia/it-427672216.eml, expedia/it-61318396.eml, expedia/it-61971884.eml, expedia/it-667874294.eml, expedia/it-679566857.eml, expedia/it-68445858.eml, expedia/it-72887764.eml, expedia/it-75787911.eml";

    private $lang = 'en';

    private $detects = [
        'en'      => 'Your flights are booked',
        'en2'     => 'we are processing your flight purchase',
        'en3'     => 'Your reservation is booked',
        'en4'     => 'Your flight is booked',
        'en5'     => 'your flight is booked',
        'en6'     => 'We are processing your purchase',
        'en7'     => 'Your car reservation is confirmed.',
        'en8'     => 'trip is confirmed.',
        'fr'      => 'Votre vol est réservé',
        'fr2'     => 'Vos vols sont réservés',
        'fr3'     => 'Votre réservation de voiture est',
        'es'      => 'Estamos procesando las compras de tus vuelos',
        'es2'     => 'Tu reservación se completó y se confirmó',
        'es3'     => 'Tus vuelos están reservados',
        'es4'     => 'Tu vuelo está reservado',
        'it'      => 'Il volo è stato prenotato',
        'pt'      => 'seu voo está reservado!',
        'pt2'     => 'seus voos estão reservados!',
        'pt3'     => 'A reserva do seu aluguel de carro está confirmada',
        'de'      => 'Ihr Flug ist gebucht.',
        'de2'     => 'Ihre Flüge sind gebucht.',
        'de3'     => 'Deine Flüge sind gebucht.',
        'zh'      => '您的机票已预订完成。',
        'zh2'     => '您的租车预订已确认。',
        'nl'      => 'Retourvlucht',
        'ja'      => '航空券の予約が完了しました。',
        'ko'      => '항공편이 예약되었습니다',
        'sv'      => 'Dina flyg har bokats',
        'da'      => 'Din flyrejse er reserveret',
    ];

    private $subjects = [
        // en
        'Expedia flight purchase confirmation',
        'Expedia travel confirmation',
        // fr
        'Confirmation d’achat de vol Expedia',
        'Confirmation d’achat d’un vol sur Expedia',
        'Confirmation de la location d’une voiture sur Expedia',
        // es
        'Confirmación de compra de vuelo en Expedia',
        'Confirmación de viaje con Expedia:',
        // it
        'Conferma acquisto volo su Expedia',
        // pt
        'Confirmação da reserva de voo na Expedia',
        'Confirmação do aluguel de carro na Expedia',
        // de
        'Expedia: Bestätigung der Flugbuchung',
        // zh
        'Expedia 机票预订确认',
        // nl
        'Reisbevestiging van Expedia',
        // ja
        '【エクスペディア】航空券の予約確認',
        // ko
        '익스피디아 항공권 구매 확인 - ',
        // sv
        'Betalningsbekräftelse för flygbokning på Expedia',
        // da
        'Bekræftelse af køb af flyrejse hos Expedia',
        'Rejsebekræftelse fra Expedia',
    ];

    private $prov = 'Expedia';

    private $date;
    private $lastDate;
    private $airlinePrevConf;
    private $rentalProvider = [
        "avis"         => ["Avis"],
        "alamo"        => ["Alamo"],
        "europcar"     => ["Europcar"],
        "dollar"       => ["Dollar Rent A Car"],
        "hertz"        => ["Hertz"],
        "national"     => ["National"],
        "perfectdrive" => ["Budget"],
        "rentacar"     => ["Enterprise"],
        "sixt"         => ["Sixt"],
        "thrifty"      => ["Thrifty Car Rental"],
    ];

    private static $dictionary = [
        'en' => [
            'Itinerary #'      => ['Itinerary #', 'Itinerary: #', 'Itinerary no.'],
            'Traveler details' => ['Traveler details', 'Traveller details', 'Traveler detail'],
            'You earned '      => ['You earned ', 'You will earn ', "You'll earn"],
            //            ' to ' => '',
            //            'Airline confirmation' => '',
            'flight duration' => 'flight duration',
            'Coach'           => 'Coach',
            // 'operated by' => '',
            //            'Terminal' => '',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => ['Price summary', 'Price Summary'],
            'feeNames'      => ['Taxes & fees', 'Locally collected mandatory fees/taxes'],
            //            'Total' => '',

            // Hotel
            'Check-in time starts at' => ['Check-in time starts at', 'Check in'],
            //            'Check-out time is' => '',
            //            'Accommodation' => '', // in price
            //            'room' => '', // in price

            // Rental
            'Pick-up' => ['Pick-up', 'Pick up'],
            //            'Hours of operation' => '',
            'Drop-off' => ['Drop-off', 'Drop off'],
            //            'or similar' => '',
        ],
        'fr' => [ // it-72887764.eml
            'Itinerary #'      => ['Numéro d’itinéraire:', 'Itinéraire nº', 'Numéro d’itinéraire :'],
            'Traveler details' => ['Détails sur le voyageur', 'Détails du ou des voyageurs', 'Détails sur le(s) voyageur(s)', 'Renseignements sur le(s) voyageur(s)'],
            //            'You earned ' => '',
            ' to '                 => ' – ',
            'Airline confirmation' => 'Confirmation de la compagnie aérienne',
            'flight duration'      => 'Durée du vol',
            'Coach'                => 'Touriste',
            // 'operated by' => '',
            //            'Terminal' => '',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => ['Sommaire du prix', 'Récapitulatif du prix'],
            'feeNames'      => ['Taxes et frais'],
            'Total'         => 'Total',

            // Hotel
            'Check-in time starts at' => 'Arrivées à partir de',
            'Check-out time is'       => ['Le départ est à', 'L’heure de départ est'],
            'Accommodation'           => 'Hébergement', // in price
            'room'                    => 'chambre', // in price

            // Rental
            'Pick-up'            => 'Prise en charge',
            'Hours of operation' => 'Heures d’ouverture',
            'Drop-off'           => 'Retour',
            'or similar'         => 'ou semblable',
        ],
        'es' => [
            'Itinerary #'          => 'Itinerario no.',
            'Traveler details'     => ['Detalles del pasajero', 'Datos de los viajeros'],
            'You earned '          => 'Obtendrás',
            ' to '                 => [' - ', ' a '],
            'Airline confirmation' => 'Confirmación de la aerolínea',
            'flight duration'      => 'Duración del vuelo',
            'Coach'                => 'turista',
            'operated by'          => 'operado por',
            'Terminal'             => 'Terminal',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Desglose de precio',
            'feeNames'      => ['Impuestos y cargos', 'Protección de vuelo'],
            'Total'         => 'Total',

            // Hotel
            'Check-in time starts at' => ['Hora de inicio de check-in:', 'Hora de inicio del registro de entrada:'],
            'Check-out time is'       => ['El check-out es a esta hora:', 'Horario de check out:', 'El registro de salida es a las'],
            'Accommodation'           => ['Hospedaje', 'Alojamiento'], // in price
            'room'                    => 'habitación', // in price

            // Rental
            'Pick-up'            => 'Entrega',
            'Hours of operation' => 'Horario',
            'Drop-off'           => 'Devolución',
            'or similar'         => 'o similar',
        ],
        'it' => [
            'Itinerary #'          => 'Itinerario n.',
            'Traveler details'     => 'Dettagli viaggiatore',
            'You earned '          => 'Accumulerai',
            ' to '                 => ' - ',
            'Airline confirmation' => 'Conferma della compagnia aerea',
            'flight duration'      => 'Durata del volo',
            'Coach'                => 'Turistica',
            // 'operated by' => '',
            //            'Terminal' => '',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Riepilogo del prezzo',
            'feeNames'      => ['Tasse e oneri'],
            'Total'         => 'Totale',

            // Hotel
            //            'Check-in time starts at' => '',
            //            'Check-out time is' => '',
            //            'Accommodation' => '', // in price
            //            'room' => '', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'pt' => [ // it-110131981.eml
            'Itinerary #'          => 'Nº do itinerário',
            'Traveler details'     => 'Detalhes do viajante',
            'You earned '          => 'Você vai ganhar',
            ' to '                 => [' a ', ' para '],
            'Airline confirmation' => 'Confirmação da companhia aérea',
            'flight duration'      => 'Duração do voo',
            'Coach'                => 'Econômica',
            // 'operated by' => '',
            //            'Terminal' => '',
            'stop' => 'escala',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Resumo do preço',
            'feeNames'      => ['Impostos e taxas'],
            'Total'         => 'Total',

            // Hotel
            //            'Check-in time starts at' => '',
            //            'Check-out time is' => '',
            //            'Accommodation' => '', // in price
            //            'room' => '', // in price

            // Rental
            'Pick-up'            => 'Retirada',
            'Hours of operation' => 'Horário de funcionamento',
            'Drop-off'           => 'Devolução',
            'or similar'         => 'ou similar',
        ],
        'de' => [
            'Itinerary #'          => 'Reiseplannr.',
            'Traveler details'     => 'Angaben zu den Reisenden',
            'You earned '          => 'Sie sammeln',
            ' to '                 => ' – ',
            'Airline confirmation' => ['Bestätigung der Fluglinie', 'Bestätigungscode der Fluglinie'],
            'flight duration'      => 'Flugdauer',
            'Coach'                => 'Economy',
            //             'operated by' => '',
            //                        'Terminal' => '',
            //            'stop' => 'escala',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Preisübersicht',
            'feeNames'      => ['Steuern und Gebühren', 'Reiserücktrittsversicherung'],
            'Total'         => ['Gesamtpreis', 'Gesamtbetrag'],

            // Hotel
            //            'Check-in time starts at' => '',
            //            'Check-out time is' => '',
            //            'Accommodation' => '', // in price
            //            'room' => '', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'zh' => [
            'Itinerary #'          => '行程编号：',
            'Traveler details'     => '旅客详细信息',
            'You earned '          => '您将累积',
            ' to '                 => [' - ', ' 至 '],
            'Airline confirmation' => '航空公司确认编号',
            'flight duration'      => '飞行时长',
            'Coach'                => ['经济舱', '二等舱'],
            //             'operated by' => '',
            'Terminal' => '航站楼',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => '价格摘要',
            'feeNames'      => ['税费和其他费用'],
            'Total'         => '总计',

            // Hotel
            //            'Check-in time starts at' => '',
            //            'Check-out time is' => '',
            //            'Accommodation' => '', // in price
            //            'room' => '', // in price

            // Rental
            'Pick-up'            => '取车',
            'Hours of operation' => '营业时间',
            'Drop-off'           => '还车',
            'or similar'         => '或类似车型',
        ],
        'nl' => [
            'Itinerary #'          => 'Reisplannummer',
            'Traveler details'     => 'Reizigersinformatie',
            //            'You earned '          => '您将累积',
            ' to '                 => ' naar ',
            'Airline confirmation' => 'Bevestiging van airline',
            'flight duration'      => 'Vluchtduur',
            'Coach'                => 'Economy',
            //             'operated by' => '',
            'Terminal' => 'Terminal',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Prijsoverzicht',
            'feeNames'      => ['Belastingen en toeslagen'],
            'Total'         => 'Totaal',

            // Hotel
            'Check-in time starts at' => 'Inchecken vanaf',
            'Check-out time is'       => 'Uitchecken:',
            'Accommodation'           => 'Accommodatie', // in price
            'room'                    => 'kamer', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'ja' => [
            'Itinerary #'          => '旅程番号 :',
            'Traveler details'     => '旅行者の詳細',
            //            'You earned '          => '您将累积',
            ' to '                 => ' → ',
            'Airline confirmation' => '航空会社の確認コード',
            'flight duration'      => '所要時間',
            'Coach'                => 'エコノミー',
            'operated by'          => '運航会社',
            'Terminal'             => 'ターミナル',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => '料金の概要',
            'feeNames'      => ['税およびサービス料'],
            'Total'         => '合計',

            // Hotel
            //            'Check-in time starts at' => 'Inchecken vanaf',
            //            'Check-out time is' => 'Uitchecken:',
            //            'Accommodation' => 'Accommodatie', // in price
            //            'room' => 'kamer', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'ko' => [
            'Itinerary #'          => '일정 번호:',
            'Traveler details'     => '여행객 정보',
            //            'You earned '          => '您将累积',
            ' to '                 => ' → ',
            'Airline confirmation' => '항공사 확인 코드',
            'flight duration'      => '비행 시간',
            'Coach'                => '이코노미석',
            //             'operated by' => '',
            'Terminal' => '터미널',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => '요금 요약',
            'feeNames'      => ['세금 및 수수료'],
            'Total'         => '총계',

            // Hotel
            //            'Check-in time starts at' => 'Inchecken vanaf',
            //            'Check-out time is' => 'Uitchecken:',
            //            'Accommodation' => 'Accommodatie', // in price
            //            'room' => 'kamer', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'sv' => [
            'Itinerary #'          => 'Resplansnummer',
            'Traveler details'     => 'Resenärsuppgifter',
            //            'You earned '          => '您将累积',
            ' to '                 => ' till ',
            'Airline confirmation' => 'Flygbolagets bekräftelsenummer',
            'flight duration'      => 'Flygtid',
            'Coach'                => 'Economy',
            'operated by'          => 'trafikeras av',
            'Terminal'             => 'Terminal',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Prisöversikt',
            'feeNames'      => ['Skatter och avgifter'],
            'Total'         => 'Totalt',

            // Hotel
            //            'Check-in time starts at' => 'Inchecken vanaf',
            //            'Check-out time is'       => 'Uitchecken:',
            //            'Accommodation'           => 'Accommodatie', // in price
            //            'room'                    => 'kamer', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
        'da' => [
            'Itinerary #'          => 'Rejseplansnummer',
            'Traveler details'     => 'Oplysninger om rejsende',
            'You earned '          => 'Du optjener ',
            ' to '                 => ' til ',
            'Airline confirmation' => 'Bekræftelse fra flyselskabet',
            'flight duration'      => 'Flyrejsens varighed',
            'Coach'                => 'Økonomiklasse',
            //            'operated by' => 'trafikeras av',
            //            'Terminal' => 'Terminal',
            //            'stop' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Price summary' => 'Prisoversigt',
            'feeNames'      => ['Skatter og gebyrer'],
            'Total'         => 'Samlet beløb',

            // Hotel
            'Check-in time starts at' => 'Indtjekning starter kl.',
            'Check-out time is'       => 'Udtjekningtidspunkt: kl.',
            'Accommodation'           => 'Overnatningssted', // in price
            'room'                    => 'værelse', // in price

            // Rental
            //            'Pick-up' => '',
            //            'Hours of operation' => '',
            //            'Drop-off' => '',
            //            'or similar' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach (self::$dictionary as $lang => $dicts) {
            if (
                (!empty($dicts['flight duration']) && $this->http->XPath->query("//node()[" . $this->contains($dicts['flight duration']) . "]")->length > 0)
                || (!empty($dicts['Check-in time starts at']) && $this->http->XPath->query("//node()[" . $this->contains($dicts['Check-in time starts at']) . "]")->length > 0)
                || (!empty($dicts['Hours of operation']) && $this->http->XPath->query("//node()[" . $this->contains($dicts['Hours of operation']) . "]")->length > 0)
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach (self::$dictionary as $lang => $dicts) {
                if (
                (!empty($dicts['Coach']) && $this->http->XPath->query("//node()[" . $this->contains($dicts['Coach']) . "]")->length > 0)
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (isset($headers['subject']) && false !== stripos($headers['subject'], $subject)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        if ($this->http->FindSingleNode("//img[@src = 'https://a.travel-assets.com/travel-assets-manager/8ca78ac4-b662-439b-88b4-b9c989f4377b/Icon_hotels_24x24.jpg']/@src")) {
            return true;
        }

        foreach ($this->detects as $detect) {
            if (false !== strpos($body, $detect)
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@expediamail.com') !== false || stripos($from, '.expediamail.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            // 9:30 pm    |    2:00 p. m.    |    18 h 05    |    noon
            'time'          => '(?:\d{1,2}(?: ?[:：h\.] ?\d{2})?(?:\s*[AaPp](?:\.[ ]*)?[Mm]\.?|\s*Uhr|\s*uur)?|noon)',
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        ];

        // Travel Agency

        $conf = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Itinerary #"))}][1]", null, false, "/{$this->preg_implode($this->t("Itinerary #"))}\:?\s*(\d{5,})(?:\s*[)(]|$)/")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Itinerary #"))}][1]/following::text()[normalize-space()][1]", null, false, '/^\d{12,}$/')
        ;

        if (!empty($conf)) {
            $email->ota()->confirmation($conf);
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Traveler details'))}]/ancestor::table[ descendant::text()[normalize-space()][2] ][1]/descendant::tr[not(.//tr) and not({$this->starts($this->t('Traveler details'))})]", null, "/^({$patterns['travellerName']})\s*[\(（]/u"));

        if (count($travellers) === 0) {
            // it-379441106.eml
            $travellers = array_filter($this->http->FindNodes("//p[{$this->starts($this->t('Traveler details'))}]/following-sibling::p/descendant::text()[normalize-space()]", null, "/^({$patterns['travellerName']})\s*[\(（]/u"));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//p[{$this->starts($this->t('Traveler details'))}]/following-sibling::p/descendant::text()[normalize-space()]", null, "/^({$patterns['travellerName']})\,?\s*\w*$/u"));
        }

        $points = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("You earned ")) . "]", null, false, "/" . $this->preg_implode($this->t("You earned ")) . "\s*(.+)/");

        if (empty($points)) {
            $points = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("You earned ")) . "]/ancestor::p[1]", null, false, "/" . $this->preg_implode($this->t("You earned ")) . "\s*(.+)/");
        }

        if (stripos($points, 'OneKeyCash') !== false) {
            $points = $this->re("/^(.+\s*OneKeyCash)/", $points);
        }

        if ($points !== null) {
            $email->ota()->earnedAwards($points);
        }

        // FLIGHT

        $xpath = "//text()[" . $this->contains($this->t("flight duration")) . " or " . $this->contains($this->t("Coach")) . "]/ancestor::*[" . $this->contains($this->t("Traveler details")) . "][1]/descendant::*[self::p or self::li]";
        $text = implode("\n", $this->http->FindNodes($xpath));

        /* pre-transform for it-379441106.eml (START) */
        $preTransformError = false;
        $flightHeaders = $flightHeaders_text = [];
        $flightFeatures = $this->http->XPath->query("//li[{$this->contains($this->t('flight duration'))} or {$this->contains($this->t('Coach'))}]");

        $rows = $this->http->XPath->query("//text()[normalize-space() = 'Web Fare' or normalize-space() = 'Special Fare']/ancestor::*[normalize-space() = 'Web Fare' or normalize-space() = 'Special Fare']");

        foreach ($rows as $row) {
            $row->parentNode->removeChild($row);
        }

        foreach ($flightFeatures as $feature) {
            $preTextNode = $this->http->XPath->query("preceding::text()[normalize-space()][1]", $feature);

            while ($preTextNode->length === 1) {
                $feature = $preTextNode->item(0);

                if ($this->http->XPath->query("ancestor::li", $feature)->length === 0) {
                    $content = $this->http->FindSingleNode('ancestor::*[1]', $feature);

                    if (!in_array($content, $flightHeaders_text)) {
                        $flightHeaders[] = $feature;
                        $flightHeaders_text[] = $content;
                    }

                    break;
                }

                $preTextNode = $this->http->XPath->query("preceding::text()[normalize-space()][1]", $feature);
            }
        }

        foreach ($flightHeaders as $fHeader) {
            $replaceNodes = $this->http->XPath->query("ancestor-or-self::node()[{$this->starts($fHeader->nodeValue)}][last()]", $fHeader);

            if ($replaceNodes->length === 0) {
                $preTransformError = true;
                $this->logger->debug('Wrong flight header!');

                // continue;
                break;
            }

            $replaceNode = $replaceNodes->item(0);

            $html = '<div style="text-align: center; background-color: #f8f5f4;"><table style="margin: 0 auto; max-width: 600px; background-color: #ffffff;">';

            $ht = html_entity_decode($this->http->FindHTMLByXpath('.', null, $replaceNode));
            $ht = trim(preg_replace("/\<[^>]*\>/ui", '', $ht));
            $html .= '<tr><td>' . $ht . '</td></tr>';

            $nextTextNodes = $this->http->XPath->query("following::text()[normalize-space()]", $replaceNode);

            foreach ($nextTextNodes as $textNode) {
                $liNodes = $this->http->XPath->query("ancestor::li[1]", $textNode);

                if ($liNodes->length === 0) {
                    break;
                }

                $liNode = $liNodes->item(0);

                if (empty($liNode) || empty($liNode->parentNode)) {
                    continue;
                }

                $html .= '<tr><td>' . str_replace("\n", '<br />', $this->htmlToText($this->http->FindHTMLByXpath('.', null, $liNode))) . '</td></tr>';
                $liNode->parentNode->removeChild($liNode); // remove <li> from source
            }

            $html .= '</table></div>';

            $htmlFragment = $this->http->DOM->createDocumentFragment();
            $htmlFragment->appendXML($html);
            $replaceNode->parentNode->replaceChild($htmlFragment, $replaceNode);
        }
        /* pre-transform (END) */

        // var_dump( $this->http->DOM->saveHTML() );

        //Segments
        $rule = "({$this->contains($this->t("flight duration"))} and count(.//text()[{$this->contains($this->t("flight duration"))}]) = 1)"
            . " or ({$this->contains($this->t("Coach"))} and count(.//text()[" . $this->contains($this->t("Coach")) . "]) = 1)";
        $xpath = "//text()[" . $this->contains($this->t("flight duration")) . " or " . $this->contains($this->t("Coach")) . "]/ancestor::table[1][contains(., ' - ') or contains(., ' – ') ][" . $this->contains($this->t(" to ")) . "][{$rule}]";

        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->contains($this->t("flight duration")) . " or " . $this->contains($this->t("Coach")) . "]/ancestor::table[1][{$rule}]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $segments = [];

        if ($nodes->length == 0 || $preTransformError === true) {
            $segments = array_filter($this->split("/\n(.+?\s+\d+(?:\s+|편).+\(\s*[A-Z]{3}\b.*\)\s*{$this->preg_implode($this->t(' to '))}\s*.+\(\s*[A-Z]{3}\b.*\))/u", $text));
        }

        if (count($segments) === 0) {
            unset($text);
        }

        if ($preTransformError === true && count($segments) === 0) {
            $email->add()->cruise(); // for 100% fail
        }

        if ($preTransformError === true) {
            $nodes = null;
        }

        if ($nodes->length == 0 && count($segments) === 0) {
            $this->logger->debug("Flight segments not found!");
        } else {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            //Travellers
            if (count($travellers) > 0) {
                $f->general()->travellers($travellers, true);
            }
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("descendant::tr[normalize-space() and not(.//tr)][position()<3][{$this->contains($this->t(' to '))}]", $root);

            if (empty($airlineText)) {
                $airlineText = $this->http->FindSingleNode("descendant::text()[normalize-space()][(contains(normalize-space(),' to '))][3]", $root);
            }

            if (empty($airlineText)) {
                $airlineText = $this->http->FindSingleNode(".", $root);
            }

            $airlineText = str_replace('nbsp;', '', $airlineText);

            if (preg_match("/^(?<airline>.+?)\s+(?<flightNumber>\d+)(?:\s+|편).+\(\s*(?<dep>[A-Z]{3})\b.*?\)?.+\(\s*(?<arr>[A-Z]{3})\b.*?\)/u", $airlineText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                $s->departure()
                    ->code($m['dep']);

                $s->arrival()
                    ->code($m['arr']);
            } elseif (preg_match("/^(?<airline>.+?)\s+(?<flightNumber>\d+).+{$this->preg_implode($this->t(' to '))}/u", $airlineText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            if (empty($s->getDepCode())) {
                $airlineText = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t(" to ")) . "]/preceding::tr[normalize-space()][1]", $root);

                if (preg_match("/^.+\((?<dep>[A-Z]{3})\).+\((?<arr>[A-Z]{3})\)/u", $airlineText, $m)) {
                    $s->departure()
                        ->code($m['dep']);

                    $s->arrival()
                        ->code($m['arr']);
                }
            }

            $airlineConf = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t("Airline confirmation")) . "]", $root, true, "/" . $this->preg_implode($this->t("Airline confirmation")) . "\s*[:：]\s*([A-Z\d]{6})\b/u");

            if (!empty($airlineConf)) {
                $s->airline()
                    ->confirmation($airlineConf);

                $this->airlinePrevConf = $airlineConf;
            }

            if (empty($airlineConf) && !empty($this->airlinePrevConf)) {
                $s->airline()
                    ->confirmation($this->airlinePrevConf);
            }

            $duration = $this->http->FindSingleNode("descendant::tr[" . $this->contains($this->t("flight duration")) . "]", $root);

            if (strlen($duration) > 500) {
                $duration = $this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t("flight duration")) . "][1]", $root);
            }

            if (preg_match("/^(\d+[\d hm]*[hm])\s+" . $this->preg_implode($this->t("flight duration")) . "/ui", $duration, $m)
                || preg_match("/^\s*" . $this->preg_implode($this->t("flight duration")) . "[: ：]+(\d+[\d hm]*[hm]|( *\d+ *[시간분]+)+)\b/ui", $duration, $m)
            ) {
                $s->extra()
                    ->duration($m[1]);
            }

            $cabinText = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t("Coach")) . "]", $root);

            if (strlen($cabinText) > 500) {
                $cabinText = $this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t("Coach")) . "][1]", $root);
            }

            if (!empty($cabinText) && preg_match("/^(?<cabin>[^\d\/]+?)\s*(?:\/\D+)?[\(（](?<code>[A-Z]{1,2})[\)）]/u", $cabinText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['code']);
            }

            if (empty($cabinText)) {
                $cabinTexts = array_filter($this->http->FindNodes("./descendant::tr[contains(normalize-space(), '(') and contains(normalize-space(), '(')]", $root, "/.*\([A-Z]{1,2}\)\d*$/"));

                if (empty($cabinTexts)) {
                    $cabinTexts = array_filter($this->http->FindNodes("./descendant::text()[contains(normalize-space(), '(') and contains(normalize-space(), '(')]", $root, "/.*\([A-Z]{1,2}\)\d*$/"));
                }

                if (count($cabinTexts) == 1) {
                    $cabinText = array_shift($cabinTexts);

                    if (!empty($cabinText) && preg_match("/^(?<cabin>\D+)(?:\s+\/\D+)?\s*\((?<code>[A-Z]{1,2})\)/", $cabinText, $m)) {
                        $s->extra()
                            ->cabin($m['cabin'])
                            ->bookingCode($m['code']);
                    }
                }
            }

            $terminal = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t("Terminal")) . "]", $root, true, "/^" . $this->preg_implode($this->t("Terminal")) . "\s+(.+)$/u");

            if (!empty($terminal)) {
                $s->departure()
                    ->terminal($terminal);
            }

            $stop = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t("stop")) . "]", $root, true, "/\((\d+)\s+" . $this->preg_implode($this->t("stop")) . "\)/u");

            if (!empty($stop)) {
                $s->extra()
                    ->stops($stop);
            }

            $operator = $this->http->FindNodes("./descendant::tr[" . $this->contains($this->t("operated by")) . "]", $root, "/" . $this->preg_implode($this->t("operated by")) . "\s+(.+)$/u");
            $operator = array_unique($operator);

            if (count($operator) == 1) {
                if (strlen($operator[0]) > 500) {
                    $operator = [$this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t("operated by")) . "][1]", $root, null, "/{$this->t('operated by')}\s*(.+)/")];
                }

                $s->airline()
                    ->operator($operator[0]);
            }

            $dateTime = $this->http->FindSingleNode("./descendant::tr[" . $this->contains($this->t("Coach")) . "]/following::tr[normalize-space()][1]", $root);

            if (empty($dateTime) && !empty($s->getCabin())) {
                $dateTime = $this->http->FindSingleNode("./descendant::tr[contains(normalize-space(), '" . $s->getCabin() . "')]/following::tr[normalize-space()][1]", $root);
            }

            if (empty($dateTime) && !empty($s->getCabin())) {
                $dateTime = $this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t("Coach")) . "][1]/following::text()[normalize-space()][string-length()>5][1]", $root);
            }

            if (empty($dateTime) && !empty($s->getCabin())) {
                $dateTime = $this->http->FindSingleNode("descendant::text()[" . $this->contains($s->getCabin()) . "][1]/following::text()[normalize-space()][string-length()>5][1]/ancestor::p[1]", $root, true, "/^[\s\·]*(.+)/");
            }

            // Thu, Sep 3, 5:45pm - 7:11pm
            // Thu, Sep 3, 10:15pm - 6:41am (Nonstop) +1
//            sex, 20 de ago, 23h59 - 6h35 (sem escalas) +1
            // dim. 20 déc., 15 h 37 – 18 h 05
            //Di., 30. Nov., 13:05 Uhr–14:15 Uhr
            if (
                preg_match("/^(?<date>[-[:alpha:]]+[,.\s]+\s*(?:[[:alpha:]]+[.]?\s+\d{1,2}|\d{1,2}[\.]?\s+(?:de\s+)?[[:alpha:]]+[.]?)),\s+(?<depTime>{$patterns['time']})\s+[\-\–]\s+(?<arrTime>{$patterns['time']})(?:\s*\(\D+\))?\s*(?:(?<nextDay>[+\-]{1,2}\d\b)|\(\D+\))?$/ui", $dateTime, $m)
                || preg_match("/^(?<date>[-[:alpha:]]+[,.\s]+\s*(?:[[:alpha:]]+[.]?\s+\d{1,2}|\d{1,2}[\.]?\s+(?:de\s+)?[[:alpha:]]+[.]?)),\s+(?<depTime>{$patterns['time']})\s*[\-\–]\s*(?<arrTime>{$patterns['time']})(?:\s*\(\D+\))?\s*(?:(?<nextDay>[+\-]{1,2}\d\b)|\(\D+\))?$/ui", $dateTime, $m)
                // 9 月 21 日星期三，6:25 - 8:01; 11 月 8 日 (火) 16:50 ～ 9:15; 9월 16일(금) 13:35 ~ 15:35
                || preg_match("/^(?<date>\d{1,2}\s*[月월]\s*\d{1,2}\s*[日일]\s*[\(（]?[[:alpha:]]*[\)）]?)[，,\s]\s*(?<depTime>{$patterns['time']})\s*[\-\–～~]\s*(?<arrTime>{$patterns['time']})(?:\s*[\(（]\D+[\)）])?\s*(?:(?<nextDay>[+\-]{1,2}\d\b)|[\(（]\D+[\)）])?$/ui", $dateTime, $m)
            ) {
                $m['date'] = $this->normalizeDate($m['date']);
                $s->departure()
                    ->date(strtotime($this->normalizeTime($m['depTime']), $m['date']));

                $arrDate = strtotime($this->normalizeTime($m['arrTime']), $m['date']);

                if (empty($m['nextDay'])) {
                    $s->arrival()->date($arrDate);
                } else {
                    $m['nextDay'] = str_replace('+-', '-', $m['nextDay']);
                    $s->arrival()->date(strtotime("{$m['nextDay']} days", $arrDate));
                }

                $this->lastDate = $s->getArrDate();
            }

            if (empty($s->getDepDate())) {
                $depDate = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t("Departs"))}]", $root);

                if (preg_match("/^{$this->preg_implode($this->t("Departs"))}\s+(?<date>[-[:alpha:]]+,\s+[[:alpha:]]+\s+\d+),\s+(?<time>{$patterns['time']})/u", $depDate, $m)) {
                    $s->departure()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                }

                $arrDate = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t("Arrives"))}]", $root);

                if (preg_match("/^{$this->preg_implode($this->t("Arrives"))}\s+(?<date>[-[:alpha:]]+,\s+[[:alpha:]]+\s+\d+),\s+(?<time>{$patterns['time']})/u", $arrDate, $m)) {
                    $s->arrival()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                }
            }

            if (empty($s->getDepDate())) {
                $depInfo = $this->http->FindSingleNode(".", $root);

                if (preg_match("/{$s->getFlightNumber()}\s*(?<depTime>[\d\:]+a?p?m)\s*\-/", $depInfo, $m)) {
                    $depDate = strtotime($m['depTime'], $this->lastDate);

                    if ($depDate < $this->lastDate) {
                        $s->departure()
                            ->date(strtotime('+1 day', $depDate));
                    } else {
                        $s->departure()
                            ->date($depDate);
                    }
                    $s->arrival()
                        ->noDate();
                }
            }
        }

        foreach ($segments as $sText) {
            if (preg_match("/" . $this->preg_implode($this->t("flight duration")) . "/ui", $sText, $m)) {
                $sText = preg_replace("/^([\s\S]+?" . $this->preg_implode($this->t("flight duration")) . "\s*.+\s*\n)[\s\S]+/", '$1', $sText);
            } elseif (preg_match("/" . $this->preg_implode($this->t("Coach")) . "/ui", $sText, $m)) {
                $sText = preg_replace("/^([\s\S]+?" . $this->preg_implode($this->t("Coach")) . "\s*.+(?:\n.+){2}\s*\n)[\s\S]+/", '$1', $sText);
            }

            $s = $f->addSegment();

            if (preg_match("/^(?<airline>.+?)\s+(?<flightNumber>\d+)(?:\s+|편).+\(\s*(?<dep>[A-Z]{3})\b.*?\).+\(\s*(?<arr>[A-Z]{3})\b.*?\)/u", $sText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                $s->departure()
                    ->code($m['dep']);

                $s->arrival()
                    ->code($m['arr']);
            } elseif (preg_match("/^(?<airline>.+?)\s+(?<flightNumber>\d+).+{$this->preg_implode($this->t(' to '))}/u", $sText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            if (empty($s->getDepCode()) && preg_match("/^.+\((?<dep>[A-Z]{3})\).+\((?<arr>[A-Z]{3})\)/u", $sText, $m)) {
                $s->departure()
                    ->code($m['dep']);

                $s->arrival()
                    ->code($m['arr']);
            }

            if (preg_match("/" . $this->preg_implode($this->t("Airline confirmation")) . "\s*[:：]\s*([A-Z\d]{6})\b/u", $sText, $m)) {
                $s->airline()
                    ->confirmation($m[1]);

                $this->airlinePrevConf = $airlineConf;
            }

            if (empty($airlineConf) && !empty($this->airlinePrevConf)) {
                $s->airline()
                    ->confirmation($this->airlinePrevConf);
            }

            if (preg_match("/\n\s*(?:\W\s*)?(\d+[\d hm]*[hm])\s+" . $this->preg_implode($this->t("flight duration")) . "/ui", $sText, $m)
                || preg_match("/\n\s*(?:\W\s*)?" . $this->preg_implode($this->t("flight duration")) . "[: ：]+(\d+[\d hm]*[hm]|( *\d+ *[시간분]+)+)\b/ui", $sText, $m)
            ) {
                $s->extra()
                    ->duration($m[1]);
            }

            if ((preg_match("/^\s*(?:\W\s*)?(.*" . $this->preg_implode($this->t("Coach")) . ".*)$/mu", $sText, $cabinText)
                && preg_match("/^(?<cabin>[^\d\/]+?)\s*(?:\/\D+)?[\(（](?<code>[A-Z]{1,2})[\)）]/u", $cabinText[1], $m))
            || preg_match("/^[·]\s*(?<cabin>.+)\s+\((?<code>[A-Z])\)$/mu", $sText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['code']);
            }

            // if (empty($cabinText)) {
            //     $cabinTexts = array_filter($this->http->FindNodes("./descendant::tr[contains(normalize-space(), '(') and contains(normalize-space(), '(')]", $root, "/.*\([A-Z]{1,2}\)\d*$/"));
            //
            //     if (count($cabinTexts) == 1) {
            //         $cabinText = array_shift($cabinTexts);
            //
            //         if (!empty($cabinText) && preg_match("/^(?<cabin>\D+)(?:\s+\/\D+)?\s*\((?<code>[A-Z]{1,2})\)/", $cabinText, $m)) {
            //             $s->extra()
            //                 ->cabin($m['cabin'])
            //                 ->bookingCode($m['code']);
            //         }
            //     }
            // }

            if (preg_match("/^\s*(?:\W\s*)?" . $this->preg_implode($this->t("Terminal")) . "\s+(.+)$/mu", $sText, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            if (preg_match("/\((\d+)\s+" . $this->preg_implode($this->t("stop")) . "\)/u", $sText, $m)) {
                $s->extra()
                    ->stops($m[1]);
            }

            if (preg_match("/" . $this->preg_implode($this->t("operated by")) . "\s+(.+)\n/u", $sText, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            $dateTime = null;

            if (preg_match("/\n.*" . $this->preg_implode($this->t("Coach")) . ".*\n\s*(?:\W\s*)?(.+)/u", $sText, $m)) {
                $dateTime = $m[1];
            }

            if (empty($dateTime) && !empty($s->getCabin())
                && preg_match("/\n.*" . $this->preg_implode($s->getCabin()) . ".*\n\s*(?:\W\s*)?(.+)/u", $sText, $m)
            ) {
                $dateTime = $m[1];
            }

            // Thu, Sep 3, 5:45pm - 7:11pm
            // Thu, Sep 3, 10:15pm - 6:41am (Nonstop) +1
//            sex, 20 de ago, 23h59 - 6h35 (sem escalas) +1
            // dim. 20 déc., 15 h 37 – 18 h 05
            //Di., 30. Nov., 13:05 Uhr–14:15 Uhr
            if (
                preg_match("/^(?<date>[-[:alpha:]]+[,.\s]+\s*(?:[[:alpha:]]+[.]?\s+\d{1,2}|\d{1,2}[\.]?\s+(?:de\s+)?[[:alpha:]]+[.]?)),\s+(?<depTime>{$patterns['time']})\s+[\-\–]\s+(?<arrTime>{$patterns['time']})(?:\s*\(\D+\))?\s*(?:[+](?<nextDay>\d{1,3}\b)|\(\D+\))?$/ui", $dateTime, $m)
                || preg_match("/^(?<date>[-[:alpha:]]+[,.\s]+\s*(?:[[:alpha:]]+[.]?\s+\d{1,2}|\d{1,2}[\.]?\s+(?:de\s+)?[[:alpha:]]+[.]?)),\s+(?<depTime>{$patterns['time']})\s*[\-\–]\s*(?<arrTime>{$patterns['time']})(?:\s*\(\D+\))?\s*(?:[+](?<nextDay>\d{1,3}\b)|\(\D+\))?$/ui", $dateTime, $m)
                // 9 月 21 日星期三，6:25 - 8:01; 11 月 8 日 (火) 16:50 ～ 9:15; 9월 16일(금) 13:35 ~ 15:35
                || preg_match("/^(?<date>\d{1,2}\s*[月월]\s*\d{1,2}\s*[日일]\s*[\(（]?[[:alpha:]]*[\)）]?)[，,\s]\s*(?<depTime>{$patterns['time']})\s*[\-\–～~]\s*(?<arrTime>{$patterns['time']})(?:\s*[\(（]\D+[\)）])?\s*(?:[+](?<nextDay>\d{1,3}\b)|[\(（]\D+[\)）])?$/ui", $dateTime, $m)
            ) {
                $m['date'] = $this->normalizeDate($m['date']);
                $s->departure()->date(strtotime($this->normalizeTime($m['depTime']), $m['date']));

                $arrDate = strtotime($this->normalizeTime($m['arrTime']), $m['date']);

                if (empty($m['nextDay'])) {
                    $s->arrival()->date($arrDate);
                } else {
                    $s->arrival()->date(strtotime("+{$m['nextDay']} days", $arrDate));
                }
            }

            // if (empty($s->getDepDate())) {
            //     $depDate = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t("Departs"))}]", $root);
            //
            //     if (preg_match("/^{$this->preg_implode($this->t("Departs"))}\s+(?<date>[-[:alpha:]]+,\s+[[:alpha:]]+\s+\d+),\s+(?<time>{$patterns['time']})/u", $depDate, $m)) {
            //         $s->departure()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            //     }
            //
            //     $arrDate = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t("Arrives"))}]", $root);
            //
            //     if (preg_match("/^{$this->preg_implode($this->t("Arrives"))}\s+(?<date>[-[:alpha:]]+,\s+[[:alpha:]]+\s+\d+),\s+(?<time>{$patterns['time']})/u", $arrDate, $m)) {
            //         $s->arrival()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            //     }
            // }
            if (empty($s->getDepCode()) && empty($s->getDepDate()) && empty($s->getAirlineName()) && empty($s->getFlightNumber())) {
                $f->removeSegment($s);
            }
        }

        // HOTEL (it-68445858.eml)

        $xpathHotelImg = "normalize-space(@width)='24' and (contains(@src,'hotel') or contains(@src,'Hotel')) or contains(@src,'Icon_hotel') or contains(@src,'icon__lob_hotel')";
        $hotels = $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::img[{$xpathHotelImg}] and normalize-space()=''] and *[2][normalize-space()] ]/*[2]"
            . " | //div[ count(div)=2 and div[1][descendant::img[{$xpathHotelImg}] and normalize-space()=''] and div[2][normalize-space()] ]/div[2]"
        );

        foreach ($hotels as $hRoot) {
            $h = $email->add()->hotel();

            $h->general()->noConfirmation();

            if (count($travellers) > 0) {
                $h->general()->travellers($travellers, true);
            }

            $hotelContent = '';
            $hotelRows = $this->http->XPath->query("descendant-or-self::*[count(tr[normalize-space()])>1][1]/tr"
                . " | descendant-or-self::*[count(p[normalize-space()])>1][1]/p"
                . " | descendant::ul/li", $hRoot);

            foreach ($hotelRows as $hRow) {
                $hotelContent .= $this->htmlToText($this->http->FindHTMLByXpath('.', null, $hRow)) . "\n";
            }

            $nextRows = $this->http->FindNodes("following::text()[normalize-space()][position()<10][{$this->contains($this->t('Check-in time starts at'))} or {$this->contains($this->t('Check-out time is'))} or {$this->contains(['cancel', 'Annulation'])}]", $hRoot);

            if (count($nextRows) > 0) {
                // it-352018310.eml
                $hotelContent .= implode("\n", $nextRows);
            }

//            $this->logger->debug('$hotelContent = '.print_r( $hotelContent,true));

            /*
                Hotel del Coronado, Curio Collection by Hilton
                Wed, Dec 2 - Mon, Dec 7, (2 travelers)
                Free cancellation until November 30 at 6:00 PM

                or

                Luxor Hotel and Casino
                3900 S. Las Vegas Blvd, Las Vegas, NV, 89119 United States of America
                Tue, Feb 16 - Fri, Feb 19
                Check-in time starts at 3:00 PM
                Check-out time is 11 AM
                Free cancellation until February 14 at 12:01 AM
            */

            $checkInValue = $checkOutValue = null;

            if (preg_match("/^(?<name>.{3,})\n+((?<address>.{3,})\n+)?(?<date1>.{4,}?)[ ]+(?:\-|−|–|al)[ ]+(?<date2>.{4,}?)(?:[, ]*\(|\n|$)/u", $hotelContent, $m)) {
                $h->hotel()->name($m['name']);

                if (!empty($m['address'])) {
                    $h->hotel()->address($m['address']);
                } else {
                    $h->hotel()->noAddress();
                }
                $checkInValue = $m['date1'];
                $checkOutValue = $m['date2'];
            }

            // Fri, Nov 20; Wed., Jun. 23; dom. 12 de sep
            $patterns['date1'] = '(?:Del )?(?<wday>[[:alpha:]]{2,})[,. ]+[ ]*(?<date>[[:alpha:]]{3,}\.? \d{1,2}(?: \d{4})?|\d{1,2}[.]?\s+(?:de\s+)?[[:alpha:]]{3,}\.?(?: \d{4})?)';

            if (preg_match("/^{$patterns['date1']}$/u", $checkInValue, $m)) {
                $h->booked()->checkIn($this->normalizeDate($checkInValue));
            }

            if (preg_match("/^{$patterns['date1']}$/u", $checkOutValue, $m)) {
                $h->booked()->checkOut($this->normalizeDate($checkOutValue));
            }

            if ($h->getCheckInDate()
                && preg_match("/{$this->preg_implode($this->t('Check-in time starts at'))}\s+({$patterns['time']})$/m", $hotelContent, $m)
            ) {
                $h->booked()->checkIn(strtotime($this->normalizeTime($m[1]), $h->getCheckInDate()));
            }

            if ($h->getCheckOutDate()
                && preg_match("/{$this->preg_implode($this->t('Check-out time is'))}\s+({$patterns['time']})/mu", $hotelContent, $m)
            ) {
                $h->booked()->checkOut(strtotime($this->normalizeTime($m[1]), $h->getCheckOutDate()));
            }

            if (preg_match("/\(\s*(\d{1,3})\s*travelers?\s*\)/i", $hotelContent, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match("/^(?:.+\n+){2,5}?(.*(?:cancel|Annulation).*)/", $hotelContent, $matches)) {
                $h->general()->cancellation($matches[1]);

                if (preg_match("/^Free (?i)cancell?ation until (?<date>[[:alpha:]]{3,} \d{1,2}) at (?<time>{$patterns['time']})(?:[ ]*[.;:(]|$)/u", $matches[1], $m) // en
                ) {
                    if (!preg_match('/\d{4}$/', $m['date']) && !empty($h->getCheckInDate())) {
                        $dateDeadline = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false);
                    } else {
                        $dateDeadline = strtotime($m['date']);
                    }
                    $h->booked()->deadline(strtotime($m['time'], $dateDeadline));
                } elseif (preg_match("/Annulation sans frais jusqu\’au\s*(?<date>.+\s[\d\:]+)\s*\(/u", $hotelContent, $m)) {
                    $h->booked()->deadline($this->normalizeDate($m['date']));
                }
            }

            $roomsCount = $this->http->FindSingleNode("//*[ (self::tr and not(.//tr) or self::p) and {$this->starts($this->t("Accommodation"))} and contains(.,'(') and {$this->contains($this->t("room"))} and preceding::text()[{$this->starts($this->t("Price summary"))}] and following::*[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total'))}]] ]", null, true, "/\b(\d{1,3})\s*{$this->preg_implode($this->t("room"))}/i");
            $h->booked()->rooms($roomsCount, false, true);
        }

        // RENTAL

        $xpathCarImg = "normalize-space(@width)='24' and (contains(@src,'car') or contains(@src,'Car')) or contains(@src,'icon__lob_car') or contains(@src,'icon__directions_car')";
        $rentals = $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::img[{$xpathCarImg}] and normalize-space()=''] and *[2][normalize-space()] ]/*[2]"
            . " | //div[ count(div)=2 and div[1][descendant::img[{$xpathCarImg}] and normalize-space()=''] and div[2][normalize-space()] ]/div[2][not(contains(normalize-space(), 'Save on select car rentals'))]"
        );

        $this->logger->debug("//tr[ count(*)=2 and *[1][descendant::img[{$xpathCarImg}] and normalize-space()=''] and *[2][normalize-space()] ]/*[2]"
            . " | //div[ count(div)=2 and div[1][descendant::img[{$xpathCarImg}] and normalize-space()=''] and div[2][normalize-space()] ]/div[2][not(contains(normalize-space(), 'Save on select car rentals'))]");

        foreach ($rentals as $rRoot) {
            if ($this->http->XPath->query("./following::text()[normalize-space()][4]/ancestor::div[1][contains(normalize-space(), 'Your ride')]", $rRoot)->length === 0) {
                $r = $email->add()->rental();

                if (count($travellers) > 0) {
                    $r->general()->travellers($travellers, true);
                }

                $rentalContent = '';
                $rentalRows = $this->http->XPath->query("descendant-or-self::*[count(tr[normalize-space()])>1][1]/tr"
                    . " | descendant-or-self::*[count(p[normalize-space()])>1][1]/p"
                    . " | descendant::ul/li", $rRoot);

                foreach ($rentalRows as $rRow) {
                    $rentalContent .= $this->htmlToText($this->http->FindHTMLByXpath('.', null, $rRow)) . "\n";
                }

                $nextRows = $this->http->FindNodes("following::text()[normalize-space()][position()<20][ {$this->contains($this->t('Hours of operation'))} or {$this->contains($this->t('Pick-up'))} or {$this->contains($this->t('Drop-off'))} or {$this->contains($this->t('or similar'))} or preceding::text()[normalize-space()][position()<6][{$this->contains($this->t('Pick-up'))} or {$this->contains($this->t('Drop-off'))}] ]", $rRoot);

                if (count($nextRows) > 0) {
                    // it-352018310.eml
                    $rentalContent .= implode("\n", $nextRows);
                }

                /*
                    National
                    Pick-up
                    300 Rodgers Blvd, Honolulu, Hawaii, USA
                    Tue, Aug 3 - 1:15pm
                    Hours of operation: 7:00am - 9:00pm
                    Drop-off
                    300 Rodgers Blvd, Honolulu, Hawaii, USA
                    Tue, Aug 10 - 12:30pm
                    Hours of operation: 5:30am - Midnight
                    Fullsize
                    • Jeep Wrangler Unlimited or similar
                    • Automatic transmission
                    • Unlimited mileage
                    View all car booking details
                */
//            $this->logger->debug('$rentalContent = '.print_r( $rentalContent,true));

                $regexp = "/^(?<company>.{2,})\n+\s*" . $this->preg_implode($this->t("Pick-up")) . "\s*\n\s*(?<pickup>[\s\S]+?)\s*\n+\s*"
                    . $this->preg_implode($this->t("Drop-off")) . "\s*\n\s*(?<dropoff>[\s\S]+?" . $this->preg_implode($this->t("Hours of operation")) . "[:：\s]+.+)\s*\n+\s*"
                    . "(?<type>.{4,})\s*\n\s*(?<model>.+" . $this->preg_implode($this->t("or similar")) . ")\b/u";
                $regexp2 = "/^(?<company>.{2,})\n+\s*" . $this->preg_implode($this->t("Pick-up")) . "\s*\n\s*(?<pickup>[\s\S]+?)\s*\n+\s*"
                    . $this->preg_implode($this->t("Drop-off")) . "\s*\n\s*(?<dropoff>[\s\S]+?" . $this->preg_implode($this->t("Hours of operation")) . "[:：\s]+.+\n.+\d+[:h]\d+.*)\s*\n+\s*"
                    . "\S+.+\s*\n\s*(?<model>.+" . $this->preg_implode($this->t("or similar")) . ")/u";

                $regexp3 = "/^(?<company>.{2,})\n+\s*(?:Pick\-up|Pick up)\s*\n\s*(?<pickup>[\s\S]+?\s*\n.{4,}?\s*-\s*\d{1,2}:\d{2}(?:\s*[apAP][mM])?\n(?:Hours of operation)[:：\s]+.+)\n+\s*(?:Drop\-off|Drop off)\s*\n\s*(?<dropoff>[\s\S\n]+?\n.{4,}?\s*-\s*\d{1,2}:\d{2}(?:\s*[apAP][mM])?\n(?:Hours of operation)[:：\s]+.+)\n\s*(?<model>.+(?:or similar))/";

                if (preg_match($regexp, $rentalContent, $m) || preg_match($regexp2, $rentalContent, $m) || preg_match($regexp3, $rentalContent, $m)) {
                    // Pick Up
                    $re = "/(?<address>[\s\S]+)\n\s*(?<date>.{4,}?)\s*-\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*\n\s*" . $this->preg_implode($this->t("Hours of operation")) . "[:：\s]+(?<hours>.+)/u";
                    $re2 = "/(?<address>[\s\S]+)\s*\n\s*" . $this->preg_implode($this->t("Hours of operation")) . "[:：\s]+(?<hours>.+)\n\s*(?<date>.{4,}?)\s*[,，]\s*(?<time>\d{1,2}[:h]\d{2}(?:\s*[apAP][mM])?)/u";

                    if (preg_match($re, $m['pickup'], $mat) || preg_match($re2, $m['pickup'], $mat)) {
                        $r->pickup()
                            ->location($mat['address'])
                            ->date(strtotime($this->normalizeTime($mat['time']), $this->normalizeDate($mat['date'])))
                            ->openingHours($mat['hours'])
                        ;
                    }

                    // DropOff
                    if (preg_match($re, $m['dropoff'], $mat) || preg_match($re2, $m['dropoff'], $mat)) {
                        $r->dropoff()
                            ->location(str_replace("\n", "", $mat['address']))
                            ->date(strtotime($this->normalizeTime($mat['time']), $this->normalizeDate($mat['date'])))
                            ->openingHours($mat['hours'])
                        ;
                    }

                    // Car
                    $r->car()
                        ->type($m['type'] ?? null, true, true)
                        ->model(preg_replace("/(^\W*|\W*$)/", '', $m['model']))
                    ;

                    // Company/Provider
                    $findedProvider = false;

                    foreach ($this->rentalProvider as $code => $names) {
                        foreach ($names as $name) {
                            if (strcasecmp($m['company'], $name) === 0 || preg_match("#^\s*" . $name . "\s+#", $m['company'])) {
                                $r->program()->code($code);
                                $findedProvider = true;

                                break 2;
                            }
                        }
                    }

                    if ($findedProvider == false) {
                        $r->extra()->company($m['company']);
                    }

                    // Confirmation
                    $phrases = array_map(function ($item) use ($m) {
                        return $m['company'] . ' ' . $item;
                    }, (array) $this->t('for special requests or questions about your car reservation'));
                    $confirmation = $this->http->FindSingleNode("//tr[{$this->contains($phrases)}]/following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Confirmation #'))}]", null, true, $pattern = "/^{$this->preg_implode($this->t('Confirmation #'))}\s*([-A-Z\d]{5,})$/")
                        ?? $this->http->FindSingleNode("//text()[{$this->contains($phrases)}]/following::text()[normalize-space()][1][{$this->starts($this->t('Confirmation #'))}]", null, true, $pattern)
                    ;

                    if ($confirmation) {
                        $r->general()->confirmation($confirmation);
                    } else {
                        $r->general()->noConfirmation();
                    }
                }
            } else {
                $t = $email->add()->transfer();
                $t->general()
                    ->noConfirmation();
                $rows = implode("\n", $this->http->FindNodes("./following::div/descendant::text()[normalize-space()]", $rRoot));
                $transferText = $this->re("/(Roundtrip\n(?:.+\n){15,25})View all ground transportation details/", $rows);

                if (preg_match_all("/(\w+\,\s*\w+\s*\d+\nPick\-up\:\n.+\nDrop-off\:\n.+)/", $transferText, $tMatch)) {
                    foreach ($tMatch[1] as $tM) {
                        if (preg_match("/(?<date>\w+\,\s*\w+\s*\d+)\nPick\-up\:\n(?<pickUp>.+)\nDrop-off\:\n(?<dropOff>.+)/", $tM, $m)) {
                            $s = $t->addSegment();

                            $s->departure()
                                ->date($this->normalizeDate($m['date']))
                                ->name($m['pickUp']);

                            $s->arrival()
                                ->noDate()
                                ->name($m['dropOff']);
                        }
                    }
                }
            }
        }

        //Price
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Price summary')) . "]/following::text()[" . $this->starts($this->t('Total')) . "]/following::text()[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $total, $m)
        ) {
            // $1,284.63    |    57,04 $ CA
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Price summary'))}]/following::*[ count(*[normalize-space()])=2 and descendant::*[normalize-space()][1][{$this->starts($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $feeCharge, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $feeCharge, $matches)
                ) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)\s*[*]*$/');
                    $email->price()->fee($feeName, PriceHelper::parse($matches['amount'], $currencyCode));
                }
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'CAD' => ['$ CA', 'CA $'],
            'BRL' => ['R$'],
            'AUD' => ['AU$'],
            'MXN' => ['MXN$'],
            'NZD' => ['NZ$'],
            'KRW' => ['₩'],
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // Sun., 10 Jan.    |    sex, 20 de ago    |    Sa., 4. Dez.
            // Del lun., 2 de ene.
            '/^(?:Del )?([-[:alpha:]]+)[.\s]*[,\s]+(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)[.\s]*$/u',
            // Wed, Dec 16
            '/^([-[:alpha:]]+)[.\s]*[,\s]+(?:de\s+)?([[:alpha:]]+)[.\s]+(\d{1,2})[.\s]*$/u',
            // 9 月 21 日星期三; 11 月 8 日 (火); 9월 16일(금)
            '/^\s*(\d{1,2})\s*[月월]\s*(\d{1,2})\s*[日일]\s*[\(（]?([[:alpha:]]+)[\)）]?\s*$/u',
            // 5 octobre 2023 à 18:00
            '/^(\d+)\s*(\w+)\s*(\d{4})\s*à\s*([\d\:]+)$/u',
        ];
        $out = [
            '$1, $2 $3 ' . $year,
            '$1, $3 $2 ' . $year,
            '$3, ' . $year . '-$1-$2',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('$date preg_replace = '.print_r( $date,true));

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)
            || preg_match("#^(?<week>\w+), (?<date>\d{4}-\d{1,2}-\d{1,2})#u", $date, $m)
        ) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if ($weeknum === null) {
                foreach (self::$dictionary as $lang => $dict) {
                    $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $lang));

                    if ($weeknum !== null) {
                        break;
                    }
                }
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeTime(?string $s): string
    {
//        $this->logger->debug('$time = '.print_r( $s,true));

        if (preg_match("/^(\d+)$/", $s)) {
            $s = $s . ':00';
        }

        if (preg_match('/^(?:12)?\s*noon\s*$/i', $s)) {
            return '12:00';
        }

        $s = preg_replace('/^(\d{1,2})[ ]*:[ ]*(\d{2})\s*([AaPp])\s*\.\s*([Mm])\s*\.$/', '$1:$2 $3$4', $s); // 8:10p. m.    ->    8:10 pm

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1];
        } // 21:51 PM    ->    21:51
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25
        $s = preg_replace('/^\s*(\d{1,2})[ ]*.[ ]*(\d{2})\s*$/', '$1:$2', $s); // 00.25    ->    00:25
        $s = preg_replace('/(\d)[ ]*[-h\.][ ]*(\d)/i', '$1:$2', $s); // 01-55 PM    ->    01:55 PM    |    23h59    ->    23:59
        $s = preg_replace('/\s*(?:Uhr|uur)\s*$/i', '', $s);
        $s = str_replace(['午前', '午後', '下午'], ['AM', 'PM', 'PM'], $s); // 10:36 午前    ->    10:36 AM

        return $s;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
