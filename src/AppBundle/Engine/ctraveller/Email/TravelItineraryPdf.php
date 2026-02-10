<?php

namespace AwardWallet\Engine\ctraveller\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// for parse HTML have `travelinc/Ticketed::parseEmail2`

// instead of `parsePdf` have similar method `travelinc/Ticketed::parseEmailPDF`

class TravelItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "ctraveller/it-119057906.eml, ctraveller/it-119735761.eml, ctraveller/it-154537248.eml, ctraveller/it-154673585.eml, ctraveller/it-156533193.eml, ctraveller/it-246701658.eml, ctraveller/it-558631489.eml";

    public $lang = '';
    public $provider = '';

    public static $dictionary = [
        'en' => [
            'Passenger Information' => ['Passenger Information'],
            'Agency Locator'        => ['Agency Locator'],
            // FLIGHS
            'Carrier Locator' => ['Carrier Locator', 'Carrier'],
            'Frequent Flyer'  => ['Frequent Flyer', 'Frequent'],
            // TRAINS
            // HOTELS
            'Frequent Stay ID' => ['Frequent Stay ID', 'Frequent Stay', 'Frequent'],
            'HotelEnd'         => ['Note', 'Special Information'],
            // CARS
            'Frequent Renter ID' => ['Frequent Renter ID', 'Frequent Renter', 'Frequent'],

            // Invoice Pdf
            'Invoice'            => ['Invoice', 'Credit note'],
            'DESCRIPTION'        => ['DESCRIPTION'],
        ],
    ];

    private $patterns = [
        'date'  => '[[:alpha:]]{3,}, [[:alpha:]]{3,} \d{1,2}, \d{4}', // WED, OCT 6, 2021
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        'phone' => '[+(\d][-. \d)(]{5,}[\d)]',
    ];

    private $body = [
        'ctraveller' => ['@corporatetraveler.us', 'Thank you for booking with Corporate Traveler', 'STAGE & SCREEN TRAVEL SERVICES'],
        'fcmtravel'  => ['us.fcm.travel', 'FCM Travel'],
    ];

    private static $headers = [
        'ctraveller' => [
            'from' => ['@corporatetraveler.us'],
        ],

        'fcmtravel' => [
            'from' => ['@us.fcm.travel'],
        ],
    ];

    public static function getEmailProviders()
    {
        return ['ctraveller', 'fcmtravel'];
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $prov) {
            foreach ($prov['from'] as $emailProv) {
                if (stripos($from, $emailProv) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach ($this->body as $provider => $provArray) {
                foreach ($provArray as $wordProv) {
                    if (stripos($textPdf, $wordProv) !== false) {
                        $this->provider = $provider;

                        if ($this->assignLang($textPdf)) {
                            return true;
                        }

                        if ($this->assignLangInvoice($textPdf)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->provider)) {
            $email->setProviderCode($this->provider);
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        $detectedItinerary = false;
        $invoiceText = null;

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $detectedItinerary = true;
                $this->parsePdf($email, $textPdf);
            }

            if (empty($invoiceText) && $this->assignLangInvoice($textPdf)) {
                $invoiceText = $textPdf;
            }
        }

        if ($detectedItinerary === false && !empty($invoiceText)) {
            $this->parsePdfInvoice($email, $invoiceText);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('TravelItineraryPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2; // itinarary and invoice
    }

    private function parsePdf(Email $email, $text)
    {
        if (preg_match("/[ ]{2}({$this->opt($this->t('Agency Locator'))})\s*:\s*([A-Z\d]{5,})$/m", $text, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $travellers = [];

        if (preg_match("/\n[ ]{6,}{$this->opt($this->t('Passenger Information'))}.*\n+{$this->opt($this->t('Traveler(s)'))}.*\n+[ ]*([\S\s]+?)\n+[ ]{6,}{$this->opt($this->t('Travel Summary'))}\n/", $text, $m)) {
            $travellerRows = preg_split("/[ ]*\n+[ ]*/", $m[1]);

            foreach ($travellerRows as $tRow) {
                $tRow = preg_replace("/^(.{20,}) {5,}.+/m", '$1', $tRow);

                if (preg_match("/^[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+$/u", $tRow)) {
                    $travellers[] = preg_replace("/^(.{2,}?)\s+(?:MISS|MRS|MR|MS)$/i", '$1', $tRow);
                } elseif (preg_match("/^[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+/u", $tRow)) {
                    $travellers[] = preg_replace("/^(.{2,}?)\s+(?:MISS|MRS|MR|MS)[ ]{2,}.*$/i", '$1', $tRow);
                }
            }
        }

        if (preg_match("/^.+?\n+([ ]{6,}{$this->opt($this->t('Travel Summary'))}\n.+)$/s", $text, $m)) {
            $text = $m[1];
        }

        if (preg_match("/^(.+?)\n+[ ]{6,}{$this->opt($this->t('General Remarks'))}\n/s", $text, $m)) {
            $text = $m[1];
        }

        $year = null;

        // FLIGHTS
        $ffNumbers = [];

        $fRegexp = "/^[ ]{6,}.+ \d+\n[ ]{6,}{$this->patterns['date']}\n+[ ]*[A-Z]{3}[ ]{2,}[A-Z]{3}(?:[ ]{2}.+)?(?:\n.*){3,18}\n[ ]*{$this->opt($this->t('Estimated Time'))}[ ]*:.*/m";
//        $this->logger->debug('flight: = '.print_r( $fRegexp,true));
        if (preg_match_all($fRegexp, $text, $flightMatches)) {
            $f = $email->add()->flight();

            if (count($travellers) > 0) {
                $f->general()->travellers($travellers, true);
            }

            $f->general()->noConfirmation();
        }

        foreach ($flightMatches[0] as $fText) {
            $s = $f->addSegment();

            if (preg_match("/^(?<top>[ ]{6,}.{3,}\n[ ]{6,}{$this->patterns['date']})\n+(?<middle>[\S\s]+)\n+(?<bottom>[ ]*{$this->opt($this->t('Seat'))}[ ]*:[\s\S]*)$/", $fText, $m)) {
                $topText = $m['top'];
                $middleText = $m['middle'];
                $bottomText = $m['bottom'];
            } else {
                break;
            }

            $date = 0;

            if (preg_match("/^\s*(?<name>.{2,})[ ]+(?<number>\d+)\n+[ ]*(?<date>{$this->patterns['date']})$/", $topText, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
                $date = strtotime($m['date']);

                $year = date('Y', $date);
            }

            $tablePos = [0];

            if (preg_match("/\n([ ]*{$this->opt($this->t('DEPARTURE'))}[ ]{2,}){$this->opt($this->t('ARRIVAL'))}(?:[ ]{2}|\n)/", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Status'))}[ ]*:/m", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $middleTable = $this->splitCols($middleText, $tablePos);

            if (count($middleTable) !== 3) {
                $this->logger->debug('Wrong table flight!');

                break;
            }

            if (preg_match("/^\s*(?<code>[A-Z]{3})\n+(?<name>[\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('DEPARTURE'))}\n+[ ]*(?<time>{$this->patterns['time']})\s*(?<terminal>.*\bTERMINAL\b.*|.+ TERM)?\s*$/", $middleTable[0], $m)) {
                $s->departure()
                    ->code($m['code'])
                    //->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->date(strtotime($m['time'], $date));

                if (!empty($m['terminal'])) {
                    $s->departure()
                        ->terminal(preg_replace("/\s*\bTerminal\b\s*/i", '', trim($m['terminal'])));
                }
            }

            if (preg_match("/^\s*(?<code>[A-Z]{3})\n+(?<name>[\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('ARRIVAL'))}\n+[ ]*(?<time>{$this->patterns['time']})(?:\s*[+]\s*(?<overnight>\d{1,3}))?\s*(?<terminal>.*\bTERMINAL\b.*|.+ TERM|AEROGARE\b.*)?\s*$/", $middleTable[1], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    //->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->date(strtotime($m['time'] . (empty($m['overnight']) ? '' : ' +' . $m['overnight'] . ' days'), $date))
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal(preg_replace("/\s*\b(?:Terminal|AEROGARE)\b\s*/i", '', trim($m['terminal'])));
                }
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Status'))}.{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Carrier Locator'))}|$)/s", $middleTable[2], $m)) {
                $s->extra()->status($this->getField('Status', $m[1])['value']);
            }

            if (preg_match("/(?:^|\n)([ ]*({$this->opt($this->t('Carrier Locator'))}).{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Frequent Flyer'))}|$)/s", $middleTable[2], $m)) {
                $s->airline()->confirmation($this->getField('Carrier Locator', $m[1])['value'], $m[2]);
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Frequent Flyer'))}.{2,}?)(?:\n\n|$)/s", $middleTable[2], $m)) {
                $ff = $this->getField('Frequent Flyer', $m[1])['value'];

                if (preg_match("/^[-A-Z\d]{5,}$/", $ff)) {
                    $ffNumbers[] = $ff;
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Seat'))}[ ]*:[ ]*(\d+[A-Z])(?:\s*{$this->opt($this->t('Confirmed'))})?$/m", $bottomText, $m)) {
                $s->extra()->seat($m[1]);
            }

            $class = preg_match("/^[ ]*{$this->opt($this->t('Class'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;

            if (preg_match("/^(.{2,}?)\s*\/\s*([A-Z]{1,2})$/", $class, $m)) {
                // Economy/S
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            } elseif (preg_match("/^[A-Z]{1,2}$/", $class)) {
                // S
                $s->extra()->bookingCode($class);
            } elseif ($class) {
                // Economy
                $s->extra()->cabin($class);
            }

            $equipment = preg_match("/^[ ]*{$this->opt($this->t('Equipment'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;
            $s->extra()->aircraft($equipment, false, true);

            $estimatedTime = preg_match("/^[ ]*{$this->opt($this->t('Estimated Time'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;

            if (preg_match("/^(?<duration>[^\/]+?)\s*\/\s*(?<stops>[^\/]+)$/", $estimatedTime, $matches)) {
                // 1 hour(s) and 34 minute(s)/Non-stop
                $s->extra()->duration($matches['duration']);

                if (preg_match("/^Non[-\s]*stop$/i", $matches['stops'])) {
                    // Non-stop
                    $s->extra()->stops(0);
                } elseif (preg_match("/^(\d{1,3})\s*stop/i", $matches['stops'], $m)) {
                    // 1 stops
                    $s->extra()->stops($m[1]);
                }
            }
        }
        $ffNumbers = array_unique($ffNumbers);

        foreach ($ffNumbers as $ffN) {
            $f->program()->account($ffN, preg_match("/XXX/i", $ffN) > 0);
        }

        // TRAINS
        $tRegexp = "/^[ ]{6,}.{3,}\n[ ]{6,}{$this->patterns['date']}(?:\n.*){1,7}\n+.+[ ]{2}{$this->opt($this->t('Status'))}[ ]*:.*(?:\n.*){1,8}\n[ ]*{$this->opt($this->t('DEPARTURE'))}[ ]{2,}{$this->opt($this->t('ARRIVAL'))}(?:[ ]{2}.+)?(?:\n.*){1,10}\n[ ]*{$this->opt($this->t('Estimated Duration'))}[ ]*:.*/m";
        //$this->logger->debug('train: '.print_r( $tRegexp,true));
        preg_match_all($tRegexp, $text, $trainMatches);

        foreach ($trainMatches[0] as $trainText) {
            $train = $email->add()->train();

            if (count($travellers) > 0) {
                $train->general()->travellers($travellers, true);
            }

            $train->general()->noConfirmation();

            if (preg_match("/^(?<top>[ ]{6,}.{3,}\n[ ]{6,}{$this->patterns['date']})\n+(?<middle>[\S\s]+)\n+(?<bottom>[ ]*{$this->opt($this->t('Estimated Duration'))}[ ]*:[\s\S]*)$/", $trainText, $m)) {
                $topText = $m['top'];
                $middleText = $m['middle'];
                $bottomText = $m['bottom'];
            } else {
                break;
            }

            $s = $train->addSegment();

            $date = 0;

            if (preg_match("/^\s*.{2,}\n+[ ]*(?<date>{$this->patterns['date']})$/", $topText, $m)) {
                $date = strtotime($m['date']);
                $year = date('Y', $date);
            }

            $tablePos = [0];

            if (preg_match("/\n([ ]*{$this->opt($this->t('DEPARTURE'))}[ ]{2,}){$this->opt($this->t('ARRIVAL'))}(?:[ ]{2}|\n)/", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Status'))}[ ]*:/m", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $middleTable = $this->splitCols($middleText, $tablePos);

            if (count($middleTable) !== 3) {
                $this->logger->debug('Wrong table train!');

                break;
            }

            if (preg_match("/^\s*(?<name>[\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('DEPARTURE'))}\n+[ ]*(?<time>{$this->patterns['time']})\s+(?<date>.{4,}?)\s*\s*(?:\n|$)/", $middleTable[0], $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']));

                if (preg_match("/^(?<wday>[[:alpha:]]{3})\s*,\s*(?<date>[[:alpha:]]{3}\s+\d{1,2})$/u", $m['date'], $m2)) {
                    $weekDateNumber = WeekTranslate::number1($m2['wday']);
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m2['date']) . ' ' . $year, $weekDateNumber);
                    $s->departure()->date(strtotime($m['time'], $dateDep));
                }
                $s->extra()->noNumber();
            }

            if (preg_match("/^\s*(?<name>[\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('ARRIVAL'))}\n+[ ]*(?<time>{$this->patterns['time']})\s+(?<date>.{4,}?)\s*$/", $middleTable[1], $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']));

                if (preg_match("/^(?<wday>[[:alpha:]]{3})\s*,\s*(?<date>[[:alpha:]]{3}\s+\d{1,2})$/u", $m['date'], $m2)) {
                    $weekDateNumber = WeekTranslate::number1($m2['wday']);
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m2['date']) . ' ' . $year, $weekDateNumber);
                    $s->arrival()->date(strtotime($m['time'], $dateArr));
                }
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Status'))}.{2,}?)(?:\n\n|$)/s", $middleTable[2], $m)) {
                $s->extra()->status($this->getField('Status', $m[1])['value']);
            }

            $estimatedDuration = preg_match("/^[ ]*{$this->opt($this->t('Estimated Duration'))}[ ]*:[ ]*(\d.+)$/m", $bottomText, $m) ? $m[1] : null;
            $s->extra()->duration($estimatedDuration);
        }

        // HOTELS
        $hRegexp = "/^[ ]{6,}.{3,}\n[ ]{6,}{$this->patterns['date']}\n+.+[ ]{2}{$this->opt($this->t('Status'))}[ ]*:.*(?:\n.*){0,5}\n[ ]*{$this->opt($this->t('CHECK IN'))}[ ]{2,}{$this->opt($this->t('CHECK OUT'))}(?:[ ]{2}.+)?(?:\n.*){1,18}\n[ ]*(?:{$this->opt($this->t('Cancellation Policy'))}|{$this->opt($this->t('HotelEnd'))})[ ]*:.*/m";
        //$this->logger->debug('hotel: = '.print_r( $hRegexp,true));
        preg_match_all($hRegexp, $text, $hotelMatches);

        foreach ($hotelMatches[0] as $hText) {
            $h = $email->add()->hotel();

            if (count($travellers) > 0) {
                $h->general()->travellers($travellers, true);
            }

            if (preg_match("/^(?<top>[ ]{6,}.{3,}\n[ ]{6,}{$this->patterns['date']})\n+(?<middle>[\S\s]+)\n+(?<bottom>[ ]*{$this->opt($this->t('Number of Nights'))}[ ]*:[\s\S]*)$/", $hText, $m)) {
                $topText = $m['top'];
                $middleText = $m['middle'];
                $bottomText = $m['bottom'];
            } else {
                break;
            }

            $hotelName = preg_match("/^\s*([\s\S]{3,})\n+[ ]*{$this->patterns['date']}$/", $topText, $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
            $h->hotel()->name($hotelName);

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Status'))}[ ]*:/m", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $middleTable = $this->splitCols($middleText, $tablePos);

            if (count($middleTable) !== 2) {
                $this->logger->debug('Wrong table hotel!');

                break;
            }

            if (preg_match("/^([\S\s]+?)\n+([ ]*{$this->opt($this->t('CHECK IN'))}[ ]{2,}{$this->opt($this->t('CHECK OUT'))}[\S\s]+)$/", $middleTable[0], $m)) {
                $addressPhones = $m[1];
                $dates = $m[2];
            } else {
                break;
            }

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->patterns['phone']}$/m", $addressPhones, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($addressPhones, $tablePos);

            if (count($table) === 2) {
                $address = preg_replace('/\s+/', ' ', $table[0]);
                $phone = preg_match("/^\s*({$this->patterns['phone']})(?:\n|$)/", $table[1], $m) ? $m[1] : null;
            } else {
                $address = preg_replace('/\s+/', ' ', $addressPhones);
                $phone = null;
            }

            if (empty($address) && !empty($phone)) {
                $h->hotel()
                    ->noAddress()
                    ->phone($phone, false, true);
            } else {
                $h->hotel()
                    ->address($address)
                    ->phone($phone, false, true);
            }

            $tablePos = [0];

            if (preg_match("/{$this->opt($this->t('CHECK OUT'))}\n+(\w+\,\s*\w+\s*\d+\,\s*\d{4}\s*)/", $dates, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            } elseif (preg_match("/^(.+[ ]{2}){$this->opt($this->t('CHECK OUT'))}/m", $dates, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($dates, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong table hotel dates!');

                break;
            }
            $checkIn = preg_match("/{$this->opt($this->t('CHECK IN'))}\s+([\s\S]{6,})$/", $table[0], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
            $checkOut = preg_match("/{$this->opt($this->t('CHECK OUT'))}\s+([\s\S]{6,})$/", $table[1], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;

            if (preg_match("/ ID(\n|$)/", $checkOut)) {
                $checkOut = trim(preg_replace('/ID:?(\n|$)/', '\n', $checkOut));
            }

            $h->booked()->checkIn2($checkIn)->checkOut2($checkOut);
            $year = date('Y', $h->getCheckOutDate());

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Status'))}.{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Confirmation'))}|$)/s", $middleTable[1], $m)) {
                $h->general()->status($this->getField('Status', $m[1])['value']);
            }

            if (preg_match("/(?:^|\n)([ ]*({$this->opt($this->t('Confirmation'))}).{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Frequent Stay ID'))}|$)/s", $middleTable[1], $m)) {
                $h->general()->confirmation($this->getField('Confirmation', $m[1])['value'], $m[2]);
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Frequent Stay ID'))}.{2,}?)(?:\n\n|$)/s", $middleTable[1], $m)) {
                $fsID = $this->getField('Frequent Stay ID', $m[1])['value'];

                if (preg_match("/^[-A-Z\d]{5,}$/", $fsID)) {
                    $h->program()->account($fsID, preg_match("/XXX/i", $fsID) > 0);
                }
            }

            $roomType = preg_match("/^[ ]*{$this->opt($this->t('Room Type'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;
            $guestCount = preg_match("/^[ ]*{$this->opt($this->t('Number of Occupants'))}[ ]*:[ ]*(\d{1,3})$/m", $bottomText, $m) ? $m[1] : null;
            $roomRate = preg_match("/^[ ]*{$this->opt($this->t('Rate'))}[ ]*:[ ]*(.*?\d.*?(?:\n {6,}.*\d.*?){0,5})\s+{$this->opt($this->t('per night'))}/m", $bottomText, $m) ? $m[1] : null;
            $cancellation = preg_match("/^[ ]*{$this->opt($this->t('Cancellation Policy'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;

            $room = $h->addRoom();
            $room->setType($roomType);
            $room->setRate($roomRate);

            $h->booked()->guests($guestCount);

            if ($cancellation) {
                $h->general()->cancellation($cancellation);

                if (preg_match("/^\d{1,3}D CANCELL? (?<prior>\d{1,3} DAYS?) PRIOR TO ARRIVAL$/", $cancellation, $m)
                ) {
                    $h->booked()->deadlineRelative($m['prior'], '00:00');
                } elseif (preg_match("/^\d{1,2}P CANCELL? BY (?<hour>{$this->patterns['time']}) DAY OF ARRIVAL$/", $cancellation, $m)
                ) {
                    $h->booked()->deadlineRelative('0 days', $m['hour']);
                }
            }
        }

        // CARS
        $cRegexp = "/^[ ]{6,}.{2,}\n+.+[ ]{2}{$this->opt($this->t('Status'))}[ ]*:.*(?:\n.*){1,12}\n[ ]*{$this->opt($this->t('PICK UP'))}[ ]{2,}{$this->opt($this->t('DROP OFF'))}(?:[ ]{2}.+)?(?:\n.*){1,10}\n[ ]*{$this->opt($this->t('Rate'))}[ ]*:.*/m";
        //$this->logger->debug('car: = '.print_r( $cRegexp,true));
        preg_match_all($cRegexp, $text, $carMatches);

        foreach ($carMatches[0] as $carText) {
            $car = $email->add()->rental();

            if (count($travellers) > 0) {
                $car->general()->travellers($travellers, true);
            }

            if (preg_match("/^(?<top>[ ]{6,}.{2,}?)\n+(?<middle>.+[ ]{2}{$this->opt($this->t('Status'))}[ ]*:[\S\s]+)\n+(?<bottom>[ ]*{$this->opt($this->t('Duration'))}[ ]*:[\s\S]*)$/", $carText, $m)) {
                $topText = $m['top'];
                $middleText = $m['middle'];
                $bottomText = $m['bottom'];
            } else {
                break;
            }

            $company = trim($topText);

            if (($code = $this->normalizeProvider($company))) {
                $car->program()->code($code);
            } else {
                $car->extra()->company($company);
            }

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Status'))}[ ]*:/m", $middleText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $middleTable = $this->splitCols($middleText, $tablePos);

            if (count($middleTable) !== 2) {
                $this->logger->debug('Wrong table car!');

                break;
            }

            if (preg_match("/^(?:[ ]*[A-Z]{3}\n+)?([\S\s]+?)\n+([ ]*{$this->opt($this->t('PICK UP'))}[ ]{2,}{$this->opt($this->t('DROP OFF'))}[\S\s]+)$/", $middleTable[0], $m)) {
                $addressPhones = $m[1];
                $dates = $m[2];
            } else {
                break;
            }

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->patterns['phone']}$/m", $addressPhones, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(?<address>.+)\;\n*\s*Tel\:\s*(?<phone>[+][\(\)\s\d\-]+)/su", $addressPhones, $m)) {
                $address = str_replace("\n", "", $m['address']);
                $phone = $m['phone'];
            } else {
                $table = $this->splitCols($addressPhones, $tablePos);

                if (count($table) === 2) {
                    $address = preg_replace('/\s+/', ' ', $table[0]);
                    $phone = preg_match("/^\s*({$this->patterns['phone']})(?:\n|$)/", $table[1], $m) ? $m[1] : null;
                } else {
                    $address = preg_replace('/\s+/', ' ', $addressPhones);
                    $phone = null;
                }
            }

            $car->pickup()->location($address)->phone($phone, false, true);
            $car->dropoff()->same();

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('DROP OFF'))}/m", $dates, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($dates, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong table car dates!');

                break;
            }

            if (preg_match("/{$this->opt($this->t('PICK UP'))}\s+([\s\S]{6,})$/", $table[0], $matches)
                && preg_match("/^(?<time>{$this->patterns['time']})\s+(?<date>.{4,})$/", trim($matches[1]), $m)
                && preg_match("/^(?<wday>[[:alpha:]]{3})\s*,\s*(?<date>[[:alpha:]]{3}\s+\d{1,2})$/u", $m['date'], $m2)
            ) {
                // 7:55 PM WED, OCT 27
                $weekDateNumber = WeekTranslate::number1($m2['wday']);
                $pickUp = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m2['date']) . ' ' . $year, $weekDateNumber);
                $car->pickup()->date(strtotime($m['time'], $pickUp));
            }

            if (preg_match("/{$this->opt($this->t('DROP OFF'))}\s+([\s\S]{6,})$/", $table[1], $matches)
                && preg_match("/^(?<time>{$this->patterns['time']})\s+(?<date>.{4,})$/", trim($matches[1]), $m)
                && preg_match("/^(?<wday>[[:alpha:]]{3})\s*,\s*(?<date>[[:alpha:]]{3}\s+\d{1,2})$/u", $m['date'], $m2)
            ) {
                $weekDateNumber = WeekTranslate::number1($m2['wday']);
                $dropOff = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m2['date']) . ' ' . $year, $weekDateNumber);
                $car->dropoff()->date(strtotime($m['time'], $dropOff));
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Status'))}.{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Confirmation'))}|$)/s", $middleTable[1], $m)) {
                $car->general()->status($this->getField('Status', $m[1])['value']);
            }

            if (preg_match("/(?:^|\n)([ ]*({$this->opt($this->t('Confirmation'))}).{2,}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Frequent Renter ID'))}|$)/s", $middleTable[1], $m)) {
                $car->general()->confirmation($this->getField('Confirmation', $m[1])['value'], $m[2]);
            }

            if (preg_match("/(?:^|\n)([ ]*{$this->opt($this->t('Frequent Renter ID'))}.{2,}?)(?:\n\n|$)/s", $middleTable[1], $m)) {
                $frID = $this->getField('Frequent Renter ID', $m[1])['value'];

                if (preg_match("/^[-A-Z\d]{5,}$/", $frID)) {
                    $car->program()->account($frID, preg_match("/XXX/i", $frID) > 0);
                }
            }

            $roomType = preg_match("/^[ ]*{$this->opt($this->t('Type'))}[ ]*:[ ]*(.+)$/m", $bottomText, $m) ? $m[1] : null;
            $car->car()->type($roomType);
        }
    }

    private function parsePdfInvoice(Email $email, $text)
    {
        if (preg_match("/[ ]{2}({$this->opt($this->t('BOOKING CODE'))})\s*[: ]\s*([A-Z\d]{5,})$/m", $text, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $mainText = '';

        if (preg_match("/\n[ ]+{$this->opt($this->t('DESCRIPTION'))} {2,}{$this->opt($this->t('AMOUNT'))}\n([\s\S]*)\n +{$this->opt($this->t('INVOICE TOTAL'))}/", $text, $m)) {
            $mainText = $m[1];
        }

        $segments = array_filter(preg_split("/\n{2,}/", $mainText));
//        $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $stext = preg_replace("/^\n*( *\S.*?) {10,}-?\d[\d., ]*\n/", '$1' . "\n", $stext);
            $stext = preg_replace("/^ *\S.*? {10,}-?\d[\d., ]*\$/m", '', $stext);

            $detectSegmentType = false;

            // Flights
            $fRegexp = "/^(.+)\n.+(?:\n\D*){0,2}\n *([A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5}, *[A-Z]{3} *- *[A-Z]{3}, *.*\d{4}/";
//            $this->logger->debug('Flight Regexp =  = ' . print_r($fRegexp, true));
            if (preg_match($fRegexp, $stext, $flSeg)) {
                $detectSegmentType = true;

                if (!isset($f)) {
                    $f = $email->add()->flight();

                    $f->general()
                        ->noConfirmation();
                }

                if (!in_array($flSeg[1], array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($flSeg[1], true);
                }

                if (preg_match_all("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}), *(?<dcode>[A-Z]{3}) *- *(?<acode>[A-Z]{3}), *(?<date>.*\d{4}.*)\s*$/m", $stext, $mat)) {
                    foreach ($mat[0] as $i => $fm) {
                        foreach ($f->getSegments() as $seg) {
                            if ($seg->getAirlineName() == $mat['al'][$i] && $seg->getFlightNumber() == $mat['fn'][$i] && $seg->getDepCode() == $mat['dcode'][$i] && $seg->getArrCode() == $mat['acode'][$i]) {
                                continue 2;
                            }
                        }
                        $s = $f->addSegment();

                        // Airline
                        $s->airline()
                            ->name($mat['al'][$i])
                            ->number($mat['fn'][$i]);

                        // Departure
                        $s->departure()
                            ->code($mat['dcode'][$i])
                            ->noDate()
                            ->day(strtotime($this->normalizeDate($mat['date'][$i])));

                        // Arrival
                        $s->arrival()
                            ->code($mat['acode'][$i])
                            ->noDate();
                    }
                }

                continue;
            }

            // Hotels
            $hRegexp = "/\n *{$this->opt($this->t('CHECK IN'))} +(.+?) *- *{$this->opt($this->t('CHECK OUT'))} +.+/";
//            $this->logger->debug('Hotel Regexp =  = ' . print_r($hRegexp, true));
            if (preg_match($hRegexp, $stext, $m)) {
//                $this->logger->debug('Hotel Segment:' . "\n" .  print_r($stext, true));
                $detectSegmentType = true;

                $h = $email->add()->hotel();

                // General
                $h->general()
                    ->confirmation($this->re("/\n\s*{$this->opt($this->t('CONFIRMATION NUMBER'))} *([A-Z\d]+)\s*\n/", $stext))
                    ->travellers(preg_split("/\s*,\s*/", $this->re("/^\s*(.+)/", $stext)))
                ;

                // Hotel
                $h->hotel()
                    ->name($this->re("/{$this->opt($this->t('CONFIRMATION NUMBER'))}.*\n(.+)/", $stext))
                    ->address(preg_replace("/\s*\n\s*/", ', ', $this->re("/^\s*([\s\S]+?){$this->opt($this->t('PHONE'))}/", $stext)))
                    ->phone($this->re("/\n\s*{$this->opt($this->t('PHONE'))}: *(.+)/", $stext))
                ;

                // Booked
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK IN'))} *(.+?)[\- ]+{$this->opt($this->t('CHECK OUT'))}/", $stext))))
                    ->checkOut(strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK IN'))} *.+?[\- ]+{$this->opt($this->t('CHECK OUT'))} *(.+)/", $stext))))
                ;

                continue;
            }

            // Rentals
            $rRegexp = "/\n *{$this->opt($this->t('PICK UP'))} +[[:alpha:]\d\- ]+- *{$this->opt($this->t('DROP OFF'))} +[[:alpha:]\d\- ]+\s*\n/";
//            $this->logger->debug('Rental Regexp = ' . print_r($rRegexp, true));
            if (preg_match($rRegexp, $stext, $renSeg)) {
//                $this->logger->debug('Rental Segment :' . "\n" .  print_r($stext, true));
                $detectSegmentType = true;
                // no locations
                continue;
            }

            if ($detectSegmentType === false) {
                $email->add()->flight();
                $this->logger->debug('Segment Type undetected: ' . $stext);
            }
        }

        return true;
    }

    private function getField(string $name, string $source): array
    {
        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t($name))}(?:[ ]*:[ ]*|[ ]{2,})).+$/m", $source, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($source, $tablePos);

        return count($table) === 2 ? [
            'name'  => preg_replace('/\s+/', ' ', trim($table[0], ":\n ")),
            'value' => preg_replace('/\s+/', ' ', trim($table[1])),
        ] : [
            'name'  => null,
            'value' => null,
        ];
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Passenger Information']) || empty($phrases['Agency Locator'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Passenger Information']) !== false
                && $this->strposArray($text, $phrases['Agency Locator']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangInvoice(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Invoice']) || empty($phrases['DESCRIPTION'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Invoice']) !== false
                && $this->strposArray($text, $phrases['DESCRIPTION']) !== false
            ) {
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'hertz' => ['Hertz', 'Hertz Rent-A-Car'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
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
            // OCT 25
            "/^([[:alpha:]]{3})\s+(\d{1,2})$/u",
            // 22-APR-2022
            "/^\s*(\d{1,2})\s*-\s*([[:alpha:]]{3})\s*-\s*(\d{4})\s*$/u",
        ];
        $out = [
            '$2 $1',
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
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
