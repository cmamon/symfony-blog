<?php

namespace App\Form\Type;

use App\Entity\Post;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false
            ])
            ->add('content', CKEditorType::class, [
              'constraints' => new Callback(array($this, 'validate')),
              'required' => false,
                'attr' => ['class' => 'ckeditor'],
            ])
            ->add('submit', SubmitType::class)
            ->add('image', FileType::class, [
                'label' => 'Choose an image…',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }

    public function validate($value, ExecutionContextInterface $context)
    {
        $form = $context->getRoot();
        $data = $form->getData();

        if (empty($data->getContent()) && empty($data->getImage())) {
            $context->buildViolation('This post need at least a description or image')
            ->atPath('content')
            ->addViolation();
        }
    }
}
