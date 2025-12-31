<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ClientRegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Prénom']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom']
            ])
            ->add('adresse', TextareaType::class, [ // or TextType
                'label' => 'Adresse de livraison',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre adresse complète (Rue, Ville, Code postal)',
                    'rows' => 3
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer votre adresse'])
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'attr' => [
                    'placeholder' => 'Numéro de téléphone',
                    'class' => 'form-control' // Ensures standard styling
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un numéro de téléphone'])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => ['class' => 'form-control', 'placeholder' => 'email@exemple.com']
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control', 'placeholder' => 'Mot de passe'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un mot de passe']),
                    new Length(['min' => 6, 'minMessage' => 'Au moins {{ limit }} caractères']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}