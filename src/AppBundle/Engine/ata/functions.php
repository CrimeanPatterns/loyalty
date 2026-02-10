<?php

//  $arg = array(
//        "URL"=>"https://www.ata.com" . $match[1],
//        "RequestMethod"=>"POST",
//        "PostValues"=>array(
//      "userId"=>$sLogin,
//      "password"=>$sPassword,
//      "method"=>"login"
//          )
//  );
//
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//
//  $http->open( $arg );
//
//  if( preg_match("/invalid\s+username\s+or\s+password/i", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "An incorrect username or password.";
//    return;
//  }
//
//  if(preg_match("/Available\s+Points\:\&nbsp\;([^\>]+)\</ims", $http->body, $match) ) {
//    $nBalance = $match[1];
//    $nErrorCode = ACCOUNT_CHECKED;
//    return;
//  }
//
//  return;
//}
