<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Remark;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
* Controller for remarks.
*/
class RemarkController extends AbstractController
{
    /**
    * @Route("/remark/delete/{id}", name="remark_delete")
    */
    public function deleteRemark($id)
    {
        $entityManager = $this->getDoctrine()->getManager();

        $remark = $this->getDoctrine()
        ->getRepository(Remark::class)
        ->find($id);

        $post = $this->getDoctrine()
        ->getRepository(Post::class)
        ->find($remark->getPostID());

        if (!$remark) {
            throw $this->createNotFoundException(
                'No post found for id '.$id
            );
        }

        $entityManager->remove($remark);
        $entityManager->flush();

        return $this->redirectToRoute('post_show', [
            'slug' => $post->getSlug(),
        ]);
    }

    /**
    * @Route("/remark/delete/{id}", name="remark_delete")
    */
    public function editRemark($id): Response
    {
        return new Response('T0D0 editRemark');
    }
}
