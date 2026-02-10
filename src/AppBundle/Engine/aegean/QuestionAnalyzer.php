<?php

namespace AwardWallet\Engine\aegean;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "Please enter the one-time password you have received in your registered email") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
