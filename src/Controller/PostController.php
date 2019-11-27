<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Post;
use App\Utils\Slugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for posts.
 */
class PostController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        $posts = $this->getDoctrine()
        ->getRepository(Post::class)
        ->findAll();

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * @Route("/post/delete", name="post_delete_all")
     */
    public function deleteAllPost()
    {
        $entityManager = $this->getDoctrine()->getManager();

        $posts = $this->getDoctrine()
        ->getRepository(Post::class)
        ->findAll();

        if (!$posts) {
            return new Response('No post to delete');
        }

        foreach ($posts as $key => $post) {
            $entityManager->remove($post);
        }

        $entityManager->flush();

        return new Response('Deleted all product ');
    }

    /**
     * @Route("/post/new", name="post_create")
     */
    public function showForm(Request $request)
    {
        $slugger = new Slugger();

        $post = new Post();
        $post->setPublicationDate(new \DateTime());


        $form = $this->createFormBuilder($post)
        ->add('name', TextType::class)
        ->add('content', TextareaType::class)
        ->add('slug', TextType::class)
        ->add('submit', SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('post');
        }

        return $this->render('post/create.html.twig', [
          'form' => $form->createView(),
      ]);
    }

    /**
     * @Route("/post/delete/{id}", name="post_delete")
     */
    public function deletePost($id): Response
    {
        $entityManager = $this->getDoctrine()->getManager();

        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->find($id);

        if (!$post) {
            throw $this->createNotFoundException(
                'No post found for id '.$id
            );
        }

        $entityManager->remove($post);
        $entityManager->flush();

        return new Response('Deleted product with id '.$post->getId());
    }

    /**
     * @Route("/post/edit/{id}", name="post_edit")
     */
    public function editPost($id): Response
    {
        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->find($id);

        if (!$post) {
            throw $this->createNotFoundException(
                'No post found for id '.$id
            );
        }

        $entityManager->persist($post);
        $entityManager->flush();

        return new Response('Deleted product with id '.$post->getId());
    }

    /**
     * @Route("/post/{id}", name="post_show")
     */
    public function showPost($id)
    {
        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->find($id);

        $posts = $this->getDoctrine()
            ->getRepository(Post::class)
            ->findAll();

        foreach ($posts as $key => $post) {
            if ($post->getId() == $id) {
                $id_previous = ($key-1 >= 0) ? $posts[$key-1]->getId() : null;
                $id_next = ($key+1 < count($posts)) ? $posts[$key+1]->getId() : null;
                break;
            }
        }

        if (!$post) {
            throw $this->createNotFoundException(
                'No post found for id '.$id
            );
        }

        return $this->render('post/show.html.twig', [
            'id' => $id,
            'post_name' => $post->getName(),
            'post_slug' => $post->getSlug(),
            'post_pub_date' => $post->getPublicationDate()->format('d-m-Y H:i:s'),
            'post_content' => $post->getContent(),
            'id_previous' => $id_previous ,
            'id_next' => $id_next
        ]);
    }
}
