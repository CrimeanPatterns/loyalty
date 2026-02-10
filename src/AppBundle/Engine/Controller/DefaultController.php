<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
//         replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    /**
     * @Route("/spec", name="editor_spec")
     */
    public function specAction(Request $request)
    {
        $rootDir = $this->getParameter("kernel.root_dir");
        /** @var \Memcached $mem */
        $mem = $this->container->get('aw.memcached');
        $memKeys = $mem->getAllKeys() === false ? [] : $mem->getAllKeys();
        if(in_array('documents['.$rootDir.'/config/swagger.yml][1]', $memKeys))
            $mem->delete('documents['.$rootDir.'/config/swagger.yml][1]');

        $host = $request->getHost();
        $scheme = $request->isSecure() ? 'https' : 'http';
        if(!in_array($request->getPort(), [80, 443]))
            $host .= ":" . $request->getPort();

        $swaggerSpec = file_get_contents($rootDir . '/config/swagger.yml');
        $swaggerSpec = str_replace("host: loyalty.awardwallet.com", "host: {$host}", $swaggerSpec);
        $swaggerSpec = str_replace("- https", "- {$scheme}", $swaggerSpec);

        return new Response($swaggerSpec, 200, ['Content-Type' => 'text/plain']);
    }

}
