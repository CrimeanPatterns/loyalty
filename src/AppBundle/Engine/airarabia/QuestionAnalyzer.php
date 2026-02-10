<?php

namespace AwardWallet\Engine\airarabia;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, 'Please enter the One-Time Password (OTP) sent to your registered email address') !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
