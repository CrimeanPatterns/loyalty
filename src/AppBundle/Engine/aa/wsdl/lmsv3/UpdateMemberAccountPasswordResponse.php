<?php

include_once 'ListResponseHeaderType.php';

class UpdateMemberAccountPasswordResponse extends ListResponseHeaderType
{
    /**
     * @var UpdateMemberAccountPasswordResult[]
     */
    public $UpdateMemberAccountPasswordResult = null;

    /**
     * @param UpdateMemberAccountPasswordResult[] $UpdateMemberAccountPasswordResult
     */
    public function __construct($UpdateMemberAccountPasswordResult)
    {
        parent::__construct();
        $this->UpdateMemberAccountPasswordResult = $UpdateMemberAccountPasswordResult;
    }
}
