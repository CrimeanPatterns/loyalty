<?php

namespace AwardWallet\Engine\aeroplan;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, "We have sent a verification code to the email address") === 0;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
