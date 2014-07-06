<?php

namespace Maidmaid\WebotBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('MaidmaidWebotBundle:Default:index.html.twig', array('name' => $name));
    }
}
