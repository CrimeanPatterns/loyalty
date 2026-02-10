<?php

namespace AwardWallet\Engine\aerlingus;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, "We've sent an email with your code to") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
