<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientRegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new Client();

        // FIX 1: You must set the date manually, otherwise the database will reject it.
        $user->setDateInscription(new \DateTime());

        $form = $this->createForm(ClientRegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Hash the password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // FIX 2: Removed $user->setRoles(['ROLE_CLIENT']);
            // Your Entity logic handles roles automatically using "instanceof Client".

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Compte créé avec succès !');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}