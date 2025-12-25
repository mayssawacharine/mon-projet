<?php
// src/Form/ProduitType.php

namespace App\Form;

use App\Entity\Produit;
use App\Entity\Categorie;
use App\Entity\Administrateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Burger Deluxe',
                    'autofocus' => true
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du produit est obligatoire'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée du produit...'
                ]
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix *',
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 12.99'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire']),
                    new Positive(['message' => 'Le prix doit être positif'])
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WebP)',
                        'maxSizeMessage' => 'L\'image est trop lourde (max 2Mo)'
                    ])
                ]
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Produit disponible à la vente',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label']
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie *',
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-control'],
                'placeholder' => '-- Choisir une catégorie --',
                'constraints' => [
                    new NotBlank(['message' => 'La catégorie est obligatoire'])
                ]
            ])
            ->add('administrateur', EntityType::class, [
                'label' => 'Administrateur responsable',
                'class' => Administrateur::class,
                'choice_label' => function (Administrateur $administrateur) {
                    return $administrateur->getNom() . ' ' . $administrateur->getPrenom();
                },
                'attr' => ['class' => 'form-control'],
                'required' => true,
                'placeholder' => '-- Sélectionner un administrateur --'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}