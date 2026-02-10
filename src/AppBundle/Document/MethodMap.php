<?php

namespace AppBundle\Document;

class MethodMap
{

    public const KEY_TO_CLASS = [
        AutoLogin::METHOD_KEY => AutoLogin::class,
        AutoLoginWithExtension::METHOD_KEY => AutoLoginWithExtension::class,
        ChangePassword::METHOD_KEY => ChangePassword::class,
        CheckAccount::METHOD_KEY => CheckAccount::class,
        CheckConfirmation::METHOD_KEY => CheckConfirmation::class,
        RaHotel::METHOD_KEY => RaHotel::class,
        RegisterAccount::METHOD_KEY => RegisterAccount::class,
        RewardAvailability::METHOD_KEY => RewardAvailability::class,
    ];

}