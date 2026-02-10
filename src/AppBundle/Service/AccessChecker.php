<?php

namespace AppBundle\Service;


use AppBundle\Security\ApiUser;

class AccessChecker
{

    const DEFAULT_ACCESS_ERROR_MESSAGE = 'API Access error';

    static public function getRoleDbMappedField(string $role) :string
    {
        $mapping = [
            ApiUser::ROLE_ACCOUNT_INFO => 'LoyaltyAccountInfo',
            ApiUser::ROLE_RESERVATIONS_INFO => 'LoyaltyReservationsInfo',
            ApiUser::ROLE_RESERVATIONS_CONF_NO => 'LoyaltyReservationByConfirmation',
            ApiUser::ROLE_ACCOUNT_HISTORY => 'LoyaltyAccountHistory',
            ApiUser::ROLE_CHANGE_PASSWORD => 'CanChangePassword',
            ApiUser::ROLE_DEBUG => 'CanDebug',
            ApiUser::ROLE_REWARD_AVAILABILITY => 'CanChangePassword',
            ApiUser::ROLE_REWARD_AVAILABILITY_HOTEL => 'CanCheckRaHotels',
        ];

        return $mapping[$role];
    }

    static public function isGranted(ApiUser $user, string $role) :bool
    {
        if (in_array($role, $user->getRoles())) {
            return true;
        }

        return false;
    }

    static public function getAccessDeniedMessage(string $role) :string
    {
        $errors = [
            ApiUser::ROLE_ACCOUNT_INFO => 'You are trying to retrieve basic loyalty info, however, it is currently not enabled on your account. Please contact AwardWallet support (https://awardwallet.com/contact) to get it enabled.',
            ApiUser::ROLE_RESERVATIONS_INFO => 'You are trying to retrieve travel itineraries, however, travel itineraries retrieval is currently not enabled on your account. Please contact AwardWallet support (https://awardwallet.com/contact) to get it enabled.',
            ApiUser::ROLE_RESERVATIONS_CONF_NO => 'You are trying to retrieve travel itineraries via confirmation number, however, this feature is currently not enabled on your account. Please contact AwardWallet support (https://awardwallet.com/contact) to get it enabled.',
            ApiUser::ROLE_ACCOUNT_HISTORY => 'You are trying to retrieve loyalty account history, however, it is currently not enabled on your account. Please contact AwardWallet support (https://awardwallet.com/contact) to get it enabled.',
            ApiUser::ROLE_CHANGE_PASSWORD => 'You are trying to call change account password method, however, it is currently not enabled on your account. Please contact AwardWallet support (https://awardwallet.com/contact) to get it enabled.',
        ];

        return $errors[$role] ?? self::DEFAULT_ACCESS_ERROR_MESSAGE;
    }

}