<?php

namespace AppBundle\Controller;

use AwardWallet\Engine\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PacController
{

    /**
     * @Route("/pac", name="pac_file")
     */
    public function pacFileAction(Request $request)
    {
        $default = 'DIRECT';
        if (!empty($request->query->get("proxy"))) {
            if(!preg_match('#^[a-z\-\.\d]+:\d+$#ims', $request->query->get("proxy")))
                die("invalid request");
            $default = 'PROXY ' . $request->query->get("proxy");
        }
        
        $cache = 'DIRECT';
        if (!empty($request->query->get("cache"))) {
            $cache = $request->query->get("cache");
            if(!preg_match('#^[a-z\-\.\d]+:\d+$#ims', $cache))
                die("invalid request");
            $cache = 'PROXY ' . $cache;
        }

        $filters = "";

        if (!empty($request->query->get("filterAds"))) {
            $filters .= "	if(" . implode("\n\t || ", array_map(function($host){ return "shExpMatch(host, '{$host}')"; }, Settings::getExcludedHosts()))  . ") {
            		return \"SOCKS localhost:4443\"; // non existent
                }
                ";
        }

        if (!empty($request->query->get("directImages"))) {
            $filters .= "	if(shExpMatch(url, 'http:') && (shExpMatch(url, '*.jpg') || shExpMatch(url, '*.png') || shExpMatch(url, '*.jpeg') || shExpMatch(url, '*.gif') || shExpMatch(url, '*.css') || shExpMatch(url, '*.js') || shExpMatch(url, '*.woff') || shExpMatch(url, '*.ico'))) {
            		return '$cache';
                }
            ";
        }
        
        return new Response(
            "
function FindProxyForURL(url, host)
{
	if (host === 'awardwallet-browser-control.s3.amazonaws.com' || host === 'localhost') {
		return \"DIRECT\";
    }
    
    $filters

	return \"$default\";
}
", 
            200, 
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * @Route("/simple-pac", name="simple_pac_file")
     */
    public function simplePacFileAction(Request $request)
    {
        $default = 'DIRECT';
        if(!empty($request->query->get("proxy"))) {
            if(!preg_match('#^[a-z\-\.\d]+:\d+$#ims', $request->query->get("proxy")))
                die("invalid request");
            $default = 'PROXY ' . $request->query->get("proxy");
        }

        return new Response(
            "
function FindProxyForURL(url, host)
{
	if (shExpMatch(host, 's3.amazonaws.com')) {
		return \"DIRECT\";
    }

	return \"$default\";
}
",
            200,
            ['Content-Type' => 'text/plain']
        );

    }

}