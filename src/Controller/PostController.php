<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Post;
use App\Form\Type\PostFormType;
use App\Entity\Comment;
use App\Utils\Slugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Gedmo\Sluggable\Util\Urlizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use App\Repository\CommentRepository;

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

        return new Response('Deleted all posts');
    }

    /**
     * @Route("/post/new", name="post_create")
     */
    public function createPost(Request $request)
    {
        $post = new Post();

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            // create slug based on post name
            $slugger = new Slugger();
            $post->setSlug($slugger->slugify($post->getName()));

            $post->setPublicationDate(new \DateTime());
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
                    // handle exception if something happens during file upload
                }

                $post->setImage($newFilename);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($post);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                return $this->render('post/create.html.twig', [
                    'form' => $form->createView(),
                    'error' => ['message' => 'There is already a post with this title.'],
                ]);
            }

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
            ->add('submit', SubmitType::class)
            ->add('image', FileType::class, [
                'label' => 'Choose a file..',
                'mapped' => false,
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $editedPost = $form->getData();
            $post->setName($editedPost->getName());

            // create slug based on post name
            $slugger = new Slugger();
            $post->setSlug($slugger->slugify($post->getName()));

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
                    // handle exception if something happens during file upload
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
     * @Route("/post/{slug}", name="post_show")
     */
    public function showPost($id, Request $request): Response
    {
        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->findOneBy(['slug' => $slug]);

        if (!$post) {
            throw $this->createNotFoundException(
                'No post found for slug "'.$slug.'".'
            );
        }

        $id = $post->getId();

        $posts = $this->getDoctrine()
            ->getRepository(Post::class)
            ->findAll();

        foreach ($posts as $key => $post) {
            if ($post->getId() === $id) {
                $slug_previous = ($key - 1 >= 0) ? $posts[$key - 1]->getSlug() : null;
                $slug_next = ($key + 1 < count($posts)) ? $posts[$key + 1]->getSlug() : null;
                break;
            }
        }

        $comment = new Comment();

        $form = $this->createFormBuilder($comment)
            ->add('message', TextareaType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setPostId($id);
            $comment->setUser(100);
            $comment->setPublicationDate(new \DateTime());
            $comment->setMessage($form['message']->getData());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('post_show', ['id' => $id]);
        }

        $repository = $this->getDoctrine()->getRepository(Comment::class);

        $comments = $repository->findBy(
            array('postId' => $id),
            array('publicationDate' => 'DESC')
        );

        return $this->render('post/show.html.twig', [
            'id' => $id,
            'post_name' => $post->getName(),
            'post_slug' => $post->getSlug(),
            'post_pub_date' => $post->getPublicationDate()->format('H:i:s \o\n l jS F Y'),
            'post_content' => $post->getContent(),
            'post_image' => $post->getImage(),
            'form' => $form->createView(),
            'comments' => $comments
            'slug_previous' => $slug_previous,
            'slug_next' => $slug_next
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
            if (!$post->isPinned() && $id == $post->getId()) {
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
