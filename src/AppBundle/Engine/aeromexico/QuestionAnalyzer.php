<?php

namespace AwardWallet\Engine\aeromexico;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, "In order to validate your Account, enter the security code") === 0 || strpos($question, "Para ingresar a tu cuenta es necesario ingresar el código de seguridad que hemos enviado a tu cuenta de correo") === 0;
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
