<?php
// src/Controller/BlogController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BlogController extends AbstractController
{
    /**
      * @Route("/", name="index")
      */
    public function index()
    {
        return $this->render('blog/index.html.twig');
    }

    /**
      * @Route("/post/{id}", name="post_id")
      */
    public function postDetail($id)
    {
        return $this->render('blog/postDetail.html.twig', [
            'id' => $id,
        ]);
    }
}
