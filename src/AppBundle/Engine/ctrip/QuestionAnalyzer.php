<?php

namespace AwardWallet\Engine\ctrip;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, 'We have sent a verification code to') !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
