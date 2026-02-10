<?php

namespace AwardWallet\Engine\bing;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please input security code which you should receive by");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
