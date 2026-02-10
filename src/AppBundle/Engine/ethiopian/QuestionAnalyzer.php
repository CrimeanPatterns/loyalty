<?php

namespace AwardWallet\Engine\ethiopian;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "We’ve sent a verification email to") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
