<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class ReservationController extends AbstractController
{
    #[Route('/reservation/nouvelle', name: 'app_reservation_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // 1. Security Check: Ensure user is logged in AND is a Client
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        // If you are using inheritance, ensure the user is a Client object
        if (!$user instanceof Client) {
            $this->addFlash('error', 'Seuls les clients peuvent réserver.');
            return $this->redirectToRoute('app_home');
        }

        $reservation = new Reservation();
        $reservation->setClient($user);
        $reservation->setDateCreation(new \DateTime());

        // 2. FORCE STATUS: Client cannot choose "Confirmée"
        $reservation->setStatut('en_attente');

        // 3. Form: Removed 'statut' field
        $form = $this->createFormBuilder($reservation)
            ->add('dateReservation', DateTimeType::class, [
                'label' => 'Date et heure souhaitées',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d\TH:i'), // Prevent past dates
                ],
                'data' => new \DateTime('+1 hour'),
            ])
            ->add('nbPersonnes', IntegerType::class, [
                'label' => 'Nombre de personnes',
                'attr' => [
                    'min' => 1,
                    'max' => 20,
                    'class' => 'form-control',
                ],
                'data' => 2,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande de réservation a été envoyée ! Elle est en attente de validation.');
            return $this->redirectToRoute('app_client_dashboard'); // Ensure this route exists
        }

        return $this->render('reservation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reservation/{id}/edit', name: 'app_reservation_edit')]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // 1. Security Check: Verify ownership
        if ($reservation->getClient() !== $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette réservation.');
            return $this->redirectToRoute('app_client_dashboard');
        }

        // 2. Logic: Prevent editing if already Confirmed or Cancelled
        if ($reservation->getStatut() !== 'en_attente') {
            $this->addFlash('warning', 'Cette réservation a déjà été traitée. Veuillez nous contacter pour la modifier.');
            return $this->redirectToRoute('app_client_dashboard');
        }

        $form = $this->createFormBuilder($reservation)
            ->add('dateReservation', DateTimeType::class, [
                'label' => 'Modifier la date',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('nbPersonnes', IntegerType::class, [
                'label' => 'Nombre de personnes',
                'attr' => ['min' => 1, 'max' => 20, 'class' => 'form-control'],
            ])
            // Removed 'statut': Client cannot confirm their own update
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Réservation modifiée avec succès !');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('reservation/edit.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    #[Route('/reservation/{id}/annuler', name: 'app_reservation_cancel')]
    public function cancel(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // Allow client to cancel their own reservation
        if ($reservation->getClient() !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $reservation->setStatut('annulee');
        $entityManager->flush();

        $this->addFlash('success', 'La réservation a été annulée.');
        return $this->redirectToRoute('app_client_dashboard');
    }
}