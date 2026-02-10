<?php

namespace AwardWallet\Engine\flybuys;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the authorization link which was sent to your email");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
