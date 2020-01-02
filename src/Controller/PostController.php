<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Remark;
use App\Form\Type\PostFormType;
use App\Repository\RemarkRepository;
use App\Repository\UserRepository;
use App\Utils\Slugger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Gedmo\Sluggable\Util\Urlizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use FOS\UserBundle\Controller\SecurityController;
use Aws\S3\S3Client;

/**
 * Controller for posts.
 */
class PostController extends SecurityController
{
    public function __construct()
    {
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => 'eu-west-3',
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ]
        ]);

        if (!$this->s3->doesBucketExist('hellototo')) {
            $result = $this->s3->createBucket([
                'Bucket'             => 'hellototo',
                'LocationConstraint' => 'eu-west-3',
            ]);
        }
    }

    protected function renderLogin(array $data)
    {
        return $this->render(
            'bundles/FOSUserBundle/Security/login_content_ext.html.twig',
             $data
         );
    }

    /**
     * @Route("/", name="default")
     */
    public function homepage(Request $request)
    {
        $user = $this->getUser();

        if ($user) {
            return $this->redirectToRoute('index', [
                'username' => $this->getUser()->getUsername()
            ]);
        }

        $users = $this->getDoctrine()
          ->getRepository(User::class)
          ->findAll();

        return $this->render('home/homepage.html.twig', [
           'users' => $users,
        ]);
    }

    /**
     * @Route("/{username}", name="index")
     */
    public function index($username)
    {
        $user = $this->getDoctrine()
        ->getRepository(User::class)
        ->findOneBy([ 'username' => $username ]);

        if ($user) {
            $posts = $this->getDoctrine()
            ->getRepository(Post::class)
            ->findPosts($user->getId());

            return $this->render('post/index.html.twig', [
              'posts' => $posts,
              'blogname' => $username
          ]);
        }

        return new Response('T0D0 No blog found');
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
            $post->setUserID($this->getUser()->getId());
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

                $result = $this->s3->putObject([
                    'Bucket' => 'hellototo',
                    'Key' => $newFilename,
                    'SourceFile' => $this->getParameter('images_directory')."/".$newFilename,
                    'Content-Type' => 'image/jpeg',
                    'ACL' => 'public-read',
                ]);

                $this->s3->waitUntil('ObjectExists', [
                    'Bucket' => 'hellototo',
                    'Key'    => $newFilename,
                ]);

                $post->setImage($result['ObjectURL']);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($post);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                return $this->render('post/create.html.twig', [
                    'form' => $form->createView(),
                    'error' => [
                        'message' => 'There is already a post with this title.'
                    ],
                ]);
            }

            return $this->redirectToRoute('index', ['username' => $this->getUser()->getUsername()]);
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

        return $this->redirectToRoute('index', ['username' => $this->getUser()->getUsername()]);
    }

    /**
     * @Route("/post/edit/{id}", name="post_edit")
     */
    public function editPost($id, Request $request): Response
    {
        $post = $this->getDoctrine()
            ->getRepository(Post::class)
            ->find($id);

        $user = $this->getDoctrine()
          ->getRepository(User::class)
          ->find($post->getUserID());

        $entityManager = $this->getDoctrine()->getManager();

        $form = $this->createFormBuilder($post)
            ->add('name', TextType::class, ['data' => $post->getName()])
            ->add('content', CKEditorType::class, ['data' => $post->getContent()])
            ->add('image', FileType::class, [
                'label' => 'Choose a new image…',
                'mapped' => false,
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
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

                $result = $this->s3->putObject([
                    'Bucket' => 'hellototo',
                    'Key' => $newFilename,
                    'SourceFile' => $this->getParameter('images_directory')."/".$newFilename,
                    'Content-Type' => 'image/jpeg',
                    'ACL' => 'public-read',
                ]);

                $this->s3->waitUntil('ObjectExists', [
                    'Bucket' => 'hellototo',
                    'Key'    => $newFilename
                ]);

                $post->setImage($result['ObjectURL']);
            }

            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('index', [
                'username' => $this->getUser()->getUsername()
            ]);
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post_image' => $post->getImage(),
            'username' => $user->getUsername()
        ]);
    }

    /**
     * @Route("/post/{slug}", name="post_show")
     */
    public function showPost($slug, Request $request): Response
    {
        $user=$this->getUser();

        if ($user != null) {
            $idUser = $user->getId();
        }

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
            ->findBy(['userID' => $post->getUserID()]);

        $users = $this->getDoctrine()
            ->getRepository(User::class)
            ->findBy([], ['id' => 'ASC']);

        $blogname = $this->getDoctrine()
            ->getRepository(User::class)
            ->find($post->getUserID())->getUsername();

        foreach ($posts as $key => $post) {
            if ($post->getId() === $id) {
                $slug_previous = ($key - 1 >= 0) ? $posts[$key - 1]->getSlug() : null;
                $slug_next = ($key + 1 < count($posts)) ? $posts[$key + 1]->getSlug() : null;
                break;
            }
        }

        $remark = new Remark();

        $form = $this->createFormBuilder($remark)
            ->add('message', TextareaType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $remark->setPostID($id);
            $remark->setUserID($idUser);
            $remark->setPublicationDate(new \DateTime());
            $remark->setMessage($form['message']->getData());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($remark);
            $entityManager->flush();

            return $this->redirectToRoute('post_show', ['slug' => $slug]);
        }

        $repository = $this->getDoctrine()->getRepository(Remark::class);

        $remarks = $repository->findBy(
            ['postID' => $id],
            ['publicationDate' => 'DESC'],
        );

        return $this->render('post/show.html.twig', [
            'id' => $id,
            'post_name' => $post->getName(),
            'post_slug' => $post->getSlug(),
            'post_pub_date' => $post->getPublicationDate()->format('l jS F Y \a\t H:i'),
            'post_content' => $post->getContent(),
            'post_image' => $post->getImage(),
            'form' => $form->createView(),
            'remarks' => $remarks,
            'users' => $users,
            'slug_previous' => $slug_previous,
            'slug_next' => $slug_next,
            'blogname' => $blogname
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

        return $this->redirectToRoute('index', [
            'username' => $this->getUser()->getUsername()
        ]);
    }

    public function searchAction(Request $request)
    {
        $form = $this->createFormBuilder(null)
          ->add('search', TextType::class)
          ->add('submit', SubmitType::class)
          ->getForm();

        return $this->render('home/searchBar.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function handleSearch(Request $request)
    {
        return $this->redirectToRoute('index', [
            'username' => $request->get('form')['search']
        ]);
    }
}
