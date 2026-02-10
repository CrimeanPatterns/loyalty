<?php

//function CheckBalanceAmericawest( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties)
//{
//  $http=new TCurlBrowser();
//  //$http->debug = true;
//
//  $arg = array(
//        "URL"=>"http://www2.usairways.com/awa/default.aspx",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  $foo = implode( "; ", $http->cookies );
//  unset( $http->cookies );
//  $http->cookies[] = $foo;
//
//  $ff = get_form_field( $http->body, "frmMain" );
//
//
//  $arg = array(
//    "URL"=>"http://www2.usairways.com/awa/default.aspx",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//		"__EVENTTARGET"=>"",
//		"__EVENTARGUMENT"=>"",
//		"__VIEWSTATE"=>$ff[ 0 ][ "__VIEWSTATE" ],
//		"loginTextBox"=>$sLogin,
//		"zipCodeTextBox"=>$sPassword,
//		"TopBarControl:profilesEntry:userNameTextBox"=>$sLogin,
//		"TopBarControl:profilesEntry:passwordTextBox"=>$sPassword,
//		"TopBarControl:search:spa"=>"00031a39-sp00000000",
//		"TopBarControl:search:spq"=>"Search",
//		"TopBarControl:profilesEntry:imageButtonLogin.x"=>"15",
//		"TopBarControl:profilesEntry:imageButtonLogin.y"=>"6"
//    )
//  );
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//  $http->open( $arg );
//
//
//  if(preg_match("/due to system maintenance the user-profile section of our site is unavailable at this time/ims", $http->body, $match) ) {
//		$nErrorCode = ACCOUNT_ENGINE_ERROR;
//		$sErrorMessage = "We're sorry, due to system maintenance the user-profile section of our site is unavailable at this time";
//		return;
//  }
//
//  if(preg_match("/Welcome[^\d]*(\d+[\.\,]?\d*[\.\,]?\d*)/ims", $http->body, $match) ) {
//    //var_dump( $match[1] );
//    $nBalance = $match[1];
//    $nErrorCode = ACCOUNT_CHECKED;
//    return;
//  }
//
//  return;
//
//}
