<?php

namespace AwardWallet\Engine\agoda\Email;

class ConfirmationForBooking extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+booking\s+with\s+Agoda\s+is\s+confirmed|la\s+vostra\s+prenotazione\s+con\s+agoda\s+è\s+confermata|Đặt\s+phòng\s+của\s+bạn\s+với\s+Agoda\s+đã\s+được\s+xác\s+nhận|Ihre\s+Agoda-Buchung\s+wurde\s+bestätigt|Agoda网站上提交的酒店订单已确认预订成功#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, it, vi, de, fi, zh, ru, nl, da, ms";
    public $typesCount = "1";
    public $reFrom = "#no-reply@agoda\.com#i";
    public $reProvider = "#agoda\.com#i";
    public $caseReference = "7049";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "agoda/it-1603891.eml, agoda/it-1891332.eml, agoda/it-1918337.eml, agoda/it-1987262.eml, agoda/it-2050251.eml, agoda/it-2066771.eml, agoda/it-2066772.eml, agoda/it-2069127.eml, agoda/it-2069130.eml, agoda/it-2069137.eml, agoda/it-2072295.eml, agoda/it-2077178.eml, agoda/it-9181615.eml, agoda/it-9217214.eml, agoda/it-9560488.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:varausnumero|订单号|預訂編號|номер бронирования|booking number|Numero della prenotazione|mã số đặt phòng|Buchungsnummer|boekingsnummer|reservationsnummer|nombor tempahan)\s+([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Hotel Name:',
                            'Nome hotel:',
                            'Tên khách sạn:',
                            'Hotelname',
                            'Hotellin nimi:',
                            '酒店:',
                            '住宿名稱:',
                            'Название отеля:',
                            'Hotelnaam:',
                            'Hotelnavn:',
                            'Nama hotel:',
                        ];

                        return cell($variants, +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Arrival Date:',
                            'Data di arrivo:',
                            'Ngày Đến:',
                            'Anreisedatum:',
                            'Tulopäivä:',
                            '入住日期:',
                            'Дата заезда:',
                            'Aankomstdatum:',
                            'Ankomstdato:',
                            'Tarikh Tiba:',
                        ];

                        return strtotime(cell($variants, +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Departure Date:',
                            'Data di partenza:',
                            'Ngày Đi:',
                            'Abreisedatum:',
                            'Lähtöpäivä:',
                            '退房日期:',
                            'Дата выезда:',
                            'Vertrekdatum:',
                            'Afrejsedato:',
                            'Tarikh Berlepas:',
                        ];

                        return strtotime(cell($variants, +1));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $variants1 = [
                            'Address:',
                            'Indirizzo:',
                            'Địa Chỉ:',
                            'Adresse:',
                            'Osoite:',
                            '酒店地址:',
                            '住宿地址:',
                            'Адрес:',
                            'Adres:',
                            'Adresse:',
                            'Alamat:',
                        ];
                        $variants2 = [
                            'Area / City / Country:',
                            'Zona / Città / Paese:',
                            'Khu Vực / Thành Phố / Quốc Gia:',
                            'Gebiet / Stadt / Land:',
                            'Alue / Kaupunki / Maa:',
                            '区域 / 城市 / 国家:',
                            '區域 / 城市 / 國家:',
                            'Район / Город / Страна:',
                            'Gebied / Stad / Land:',
                            'Område / By / Land:',
                            'Kawasan / Bandar / Negara:',
                        ];

                        return nice(cell($variants1, +1) . ", " . cell($variants2, +1));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Lead Guest:',
                            'Ospite principale:',
                            'Khách Chính:',
                            'Hauptgast:',
                            'Varauksesta vastaava:',
                            '顾客姓名:',
                            '顧客姓名:',
                            'Имя гостя:',
                            'Aanhef Gast:',
                            'Hovedgæst:',
                            'Tetamu Utama:',
                        ];

                        return cell($variants, +1);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Aikuisten lukumäärä:',
                            '客房数:',
                            '成人入住人數:',
                            'Antal voksne:',
                        ];

                        return cell($variants, +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'No. of Rooms:',
                            'N. di camere:',
                            'Số Phòng:',
                            'Anzahl Zimmer:',
                            'Huoneiden määrä:',
                            '成人人数:',
                            '客房數:',
                            'Кол-во комнат:',
                            'Aantal Kamers:',
                            'Antal værelser:',
                            'Bil. Bilik:',
                        ];

                        return cell($variants, +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Cancellation and Change Policy',
                            'Termini di Cancellazione e Modifica',
                            'Chính Sách Hủy và Thay Đổi Đặt Phòng',
                            'Stornierungs-',
                            'Peruutus- ja vaihtokäytäntö',
                            '取消与修改政策',
                            '取消及修改政策',
                            'Политика отмены и внесения изменений',
                            'Annulerings- en Aanpassingsbeleid',
                            'Afbestillings- og ændringspolitik',
                            'Polisi Pembatalan dan Perubahan',
                        ];
                        array_walk($variants, function (&$value, $key) { $value = 'contains(normalize-space(.), "' . $value . '")'; });
                        $xpath = '//text()[' . implode(' or ', $variants) . ']/ancestor-or-self::tr[1]/following-sibling::tr';

                        return str_replace('..', '.', glue(filter(nodes($xpath)), ". "));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Room Type:',
                            'Tipo di camera:',
                            'Loại Phòng:',
                            'Zimmerkategorie:',
                            'Huonetyyppi:',
                            '房型',
                            'Тип номера:',
                            'Kamertype:',
                            'Værelsestype:',
                            'Jenis Bilik:',
                        ];

                        return cell($variants, +1);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Total / Room Charge:", "房價淨額:"], +1, 0));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Total Charge to Credit Card',
                            'Totale dovuto / Addebito su carta di credito',
                            'Tổng Chi Phí tính vào Thẻ Tín dụng:',
                            'Gesamter Abbuchungsbetrag von Ihrer Kreditkarte',
                            'Kokonaismaksu luottokortilla',
                            '从信用卡中扣费总额',
                            '信用卡應付總額',
                            'Всего будет списано',
                            'Итого/Плата за номер',
                            'Total Due / Charge to Credit Card:',
                            'Totaalbedrag afschrijving creditcard',
                            'Samlet beløb der opkræves fra dit kreditkort',
                            'Caj Keseluruhan ke Kad Kredit',
                        ];

                        $value = re("#^[^\(]+#", cell($variants, +1, 0, "//text()"));

                        // clear unused symbols
                        $value = trim(clear("#[^\dA-Za-z,. ]#", $value), '., ');

                        return total($value, 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#Redeemed Points\s*:\s*(\d+)#");
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#Accrued Points\s*:\s*(\d+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Your\s+booking\s+with\s+Agoda\s+is\s+(confirmed)#i'),
                            re('#la\s+vostra\s+prenotazione\s+con\s+agoda\s+è\s+(confermata)#i'),
                            re('#Đặt\s+phòng\s+của\s+bạn\s+với\s+Agoda\s+đã\s+được\s+(xác\s+nhận)#i'),
                            re('#Ihre\s+Agoda-Buchung\s+wurde\s+(bestätigt)#'),
                            re('#Varauksesi Agodalla on\s+([^\n,.]+)#'),
                            re('#确认预订#') ? 'Confirmed' : '',
                            re("#(\w+)\s+номер\s+бронирования#u"),
                            re("#Uw\s+boeking\s+bij\s+Agoda\s+is\s+(bevestigd)#u"),
                            re("#reservation hos Agoda er nu (bekræftet)#u"),
                            re("#dengan Agoda telah (disahkan) dan #u")
                        );
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en", "it", "vi", "de", "fi", "zh", "ru", "nl", "da", "ms"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
