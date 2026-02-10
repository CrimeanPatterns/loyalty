<?php

namespace AwardWallet\Engine\expedia;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            strpos($question, "secure code we sent to") !== false
            || strpos($question, "confirmar o seu e-mail") !== false
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
