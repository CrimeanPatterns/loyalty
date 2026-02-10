<?php

namespace Tests\Functional\RewardAvailability;


/**
 * @ignore
 */
class RaHotelParser extends \TAccountChecker
{

    const noWarningEmpty = 0;
    const warningEmpty = 1;
    const wrongPoints = 2;
    const wrongDate = 3;
    const preview = 4;
    const currency = 5;
    const noCash = 6;

    public static $checkState;

    public static function reset(): void
    {
        self::$checkState = null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        switch (self::$checkState) {
            case self::noWarningEmpty;
                return ['hotels' => []];
            case self::warningEmpty;
            {
                $this->SetWarning('no hotels');
                return [];
            }
            case self::wrongPoints;
            {
                $routes = $this->hotels($fields);
                $routes[0]['pointsPerNight'] = 0;
                return ['hotels' => $routes];
            }
            case self::wrongDate;
            {
                $routes = $this->hotels($fields);
                $routes[0]['checkInDate'] .= ':';
                return ['hotels' => $routes];
            }
            case self::preview;
            {
                $routes = $this->hotels($fields);
                return ['hotels' => $routes];
            }
            case self::currency;
            {
                $routes = $this->hotels($fields);
                $routes[0]['currency'] = $routes[1]['currency'] = 'AUD';
                return ['hotels' => $routes];
            }
            case self::noCash;
            {
                $routes = $this->hotels($fields);
                unset($routes[1]['cashPerNight']);
                return ['hotels' => $routes];
            }
            default:
                throw new \CheckException('bad params', ACCOUNT_ENGINE_ERROR);
        }
    }

    private function hotels($fields): array
    {
        return [
            [
                'name' => 'Sheraton Philadelphia Downtown Hotel',
                'checkInDate' => date('Y-m-d', $fields['CheckIn']) . '14:00',
                'checkOutDate' => date('Y-m-d', $fields['CheckOut']),
                'roomType' => 'Guest room, 2 Double',
                'hotelDescription' => 'Pets Not Allowed. Hotel is no longer pet-friendly',
                'numberOfNights' => 1,
                'pointsPerNight' => 12000,
                'cashPerNight' => 12.5,
                'distance' => 123123,
                'rating' => 3.6,
                'numberOfReviews' => 12343,
                'address' => '201 North 17th Street, Philadelphia, Pennsylvania 19103 United States',
                'detailedAddress' => [],
                'phone' => '+1-22-3333',
                'url' => '',
                'preview' => $fields['DownloadPreview'] ? 'some_base64_string' : null
            ],
            [
                'name' => 'Aloft Philadelphia Downtown',
                'checkInDate' => date('Y-m-d', $fields['CheckIn']),
                'checkOutDate' => date('Y-m-d', $fields['CheckOut']),
                'roomType' => 'King room, 2 Double',
                'hotelDescription' => 'Centrally located near the Met in Center City, Aloft Philadelphia Downtown hotel is within walking distance from City Hall and the Liberty Bell at Independence Hall.',
                'numberOfNights' => 1,
                'pointsPerNight' => 15000,
                'cashPerNight' => 12.5,
                'distance' => 123127,
                'rating' => 4.3,
                'numberOfReviews' => 1233,
                'address' => '101 N Broad St, Philadelphia, PA 19107 United States',
                'detailedAddress' => [],
                'phone' => '+1-22-3343',
                'url' => 'https://www.marriott.com/en-us/hotels/phlad-aloft-philadelphia-downtown/overview/',
                'preview' => $fields['DownloadPreview'] ? 'some_base64_string' : null
            ],
        ];
    }
}