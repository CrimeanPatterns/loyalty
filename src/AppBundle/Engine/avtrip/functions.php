<?php

///*---------------------------------------------------------------------------*/
//function CheckBalanceavtrip( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties )
//{
//  $http=new TCurlBrowser();
//  //  $http->debug=true;
//  $http->redirect = true;
//
//  $arg = array(
//        "URL"=>"https://www.avfuel.com/avtrip/checkPoints.asp",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  $arg = array(
//    "URL"=>"https://www.avfuel.com/avtrip/checkPointsProcess.asp",
//    "RequestMethod"=>"POST",
//    "PostValues"=> array(
//    	"msid" => $sLogin,
//    	"code" => $sPassword,
//    ),
//  );
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//
//  $http->open( $arg );
//
//  if(preg_match("/<span\s*class=\"redText\"><b>([^<]+)<\/b><\/span><\/td><\/tr>/ims", $http->body, $match) ) {
//	$nErrorCode = ACCOUNT_INVALID_PASSWORD;
//	$sErrorMessage = $match[1];
//	return;
//  }
//
//  $arg = array(
//        "URL"=>"https://www.avfuel.com/avtrip/reviewTransactions.asp",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  if(preg_match("/<td colspan=\"3\" align=\"right\"><span class=\"tdList\">Current points<\/span><\/td>\s*<TD align=\"right\"><span class=\"tdList\">([^<]+)<\/span><\/TD>/ims", $http->body, $match) ) {
//    $nBalance = $match[1];
//    $nErrorCode = ACCOUNT_CHECKED;
//  }
//
//  // ext props
//  if( preg_match( "/<span class=\"tdList\">Last statement date:([^<]+)<\/span>/ims", $http->body, $arMatches ) )
//  	$arProperties["LastDate"] = $arMatches[1];
//  if( preg_match( "/<span class=\"tdList\">AVTRIP ID:([^<]+)<\/span>/ims", $http->body, $arMatches ) )
//  	$arProperties["Number"] = $arMatches[1];
//  if( preg_match( "/<span class=\"tdList\">Tail:([^<]+)<\/span>/ims", $http->body, $arMatches ) )
//  	$arProperties["Tail"] = $arMatches[1];
//  if( preg_match( "/<span class=\"tdList\">Total points since last statement<\/span><\/td>\s*<TD align=\"right\"><span class=\"tdList\">([^<]+)<\/span>/ims", $http->body, $arMatches ) )
//  	$arProperties["TotalPoints"] = $arMatches[1];
//}
