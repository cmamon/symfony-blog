<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Post;
use App\Utils\Slugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for posts.
 */
class PostController extends AbstractController
{
    /**
     * @Route("/", name="post")
     */
    public function index()
    {
        $posts = $this->getDoctrine()
        ->getRepository(Post::class)
        ->findAll();

        if (!$posts) {
            return new Response('No post to show.');
        }

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
    public function createPost()
    {
        // you can fetch the EntityManager via $this->getDoctrine()
        // or you can add an argument to the action: createProduct(EntityManagerInterface $entityManager)
        $entityManager = $this->getDoctrine()->getManager();

        $slugger = new Slugger();

        $post = new Post();
        $post->setName('Keyboard');
        $post->setPublicationDate(new \DateTime());
        $post->setContent('Ergonomic and stylish!');
        $post->setSlug($slugger->slugify($post->getName()));

        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        $entityManager->persist($post);

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        $posts = $this->getDoctrine()
          ->getRepository(Post::class)
          ->findAll();

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
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
            'post_content' => $post->getContent()
        ]);
    }
}
