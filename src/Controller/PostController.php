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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Gedmo\Sluggable\Util\Urlizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

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
    public function createPost(Request $request)
    {
        $slugger = new Slugger();

        $post = new Post();
        $post->setPublicationDate(new \DateTime());

        $form = $this->createFormBuilder($post)
        ->add('name', TextType::class)
        ->add('content', TextareaType::class)
        ->add('slug', TextType::class)
        ->add('submit', SubmitType::class)
        ->add('image', FileType::class, [
                'label' => 'Choose a file..',
                // unmapped means that this field is not associated to any entity property
                'mapped' => false,

                // make it optional so you don't have to re-upload the PDF file
                // everytime you edit the Product details
                'required' => false,

                // unmapped fields can't define their validation using annotations
                // in the associated entity, so you can use the PHP constraint classes

            ])
        ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();
            $image = $form['image']->getData();

            if ($image) {
                // this is needed to safely include the file name as part of the URL
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = Urlizer::urlize($originalFilename).'-'.uniqid().'.'.$image->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $image->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $post->setImage($newFilename);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('index');
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

        $posts = $this->getDoctrine()
        ->getRepository(Post::class)
        ->findAll();

        return $this->redirectToRoute('index', [
            'posts' => $posts,
        ]);
    }

    /**
     * @Route("/post/edit/{id}", name="post_edit")
     */
    public function editPost($id, Request $request): Response
    {
        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->find($id);

        $entityManager = $this->getDoctrine()->getManager();

        $form = $this->createFormBuilder($post)
            ->add('name', TextType::class, ['data' => $post->getName()])
            ->add('content', TextareaType::class, ['data' => $post->getContent()])
            ->add('slug', TextType::class, ['data' => $post->getSlug()])
            ->add('submit', SubmitType::class)
            ->add('image', FileType::class, [

                    'label' => 'Choose a file..',

                    // unmapped means that this field is not associated to any entity property
                    'mapped' => false,

                    // make it optional so you don't have to re-upload the PDF file
                    // everytime you edit the Product details
                    'required' => false,

                    // unmapped fields can't define their validation using annotations
                    // in the associated entity, so you can use the PHP constraint classes

                ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $editedPost = $form->getData();
            $post->setName($editedPost->getName());
            $post->setSlug($editedPost->getSlug());
            $post->setContent($editedPost->getContent());
            $image = $form['image']->getData();

            if ($image) {
                // this is needed to safely include the file name as part of the URL
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = Urlizer::urlize($originalFilename).'-'.uniqid().'.'.$image->guessExtension();
                // Move the file to the directory where brochures are stored
                try {
                    $image->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }


                $post->setImage($newFilename);
            }

            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('index');
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post_image' => $post->getImage()
        ]);
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
            'post_image' => $post->getImage(),
            'id_previous' => $id_previous ,
            'id_next' => $id_next
        ]);
    }

    /**
     * @Route("/post/pin/{id}", name="post_pinned")
     */
    public function pinPost($id)
    {
        $posts = $this->getDoctrine()
        ->getRepository(Post::class)
        ->findAll();

        $entityManager = $this->getDoctrine()->getManager();

        foreach ($posts as $post) {
            if (!$post->isPinned()) {
                $post->setIsPinned(true);
            } else {
                $post->setIsPinned(false);
            }

            $entityManager->persist($post);
        }

        $entityManager->flush();

        return $this->redirectToRoute('index');
    }
}
//
