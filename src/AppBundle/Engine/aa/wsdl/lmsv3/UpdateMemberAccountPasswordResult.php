<?php

class UpdateMemberAccountPasswordResult
{
    /**
     * @var UpdateMemberAccountPasswordStatus
     */
    public $UpdateMemberAccountPasswordStatus = null;

    /**
     * @var int
     */
    public $PasswordFailCount = null;

    /**
     * @var UpdateMemberAccountPasswordRequestItem
     */
    public $UpdateMemberAccountPasswordRequestItem = null;

    /**
     * @param UpdateMemberAccountPasswordStatus $UpdateMemberAccountPasswordStatus
     * @param int $PasswordFailCount
     * @param UpdateMemberAccountPasswordRequestItem $UpdateMemberAccountPasswordRequestItem
     */
    public function __construct($UpdateMemberAccountPasswordStatus, $PasswordFailCount, $UpdateMemberAccountPasswordRequestItem)
    {
        $this->UpdateMemberAccountPasswordStatus = $UpdateMemberAccountPasswordStatus;
        $this->PasswordFailCount = $PasswordFailCount;
        $this->UpdateMemberAccountPasswordRequestItem = $UpdateMemberAccountPasswordRequestItem;
    }
}
