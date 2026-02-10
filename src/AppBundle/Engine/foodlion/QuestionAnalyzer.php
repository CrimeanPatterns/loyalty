<?php

namespace AwardWallet\Engine\foodlion;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return strpos($question, "We sent a secure code to your email") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
