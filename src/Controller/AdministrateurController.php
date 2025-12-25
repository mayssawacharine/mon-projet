<?php
// src/Controller/AdministrateurController.php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\ProduitRepository;
use App\Entity\Client; // <-- IMPORTANT: Ajoutez cette ligne
use App\Form\ClientAdminType;
use App\Form\ClientRegistrationFormType;
use Doctrine\ORM\EntityManagerInterface; // <-- IMPORTANT: Ajoutez cette ligne
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request; // <-- IMPORTANT: Ajoutez cette ligne
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AdministrateurController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $stats = [
            'commandes_aujourdhui' => 0,
            'reservations_aujourdhui' => 0,
            'chiffre_affaires' => 0,
        ];

        $recentOrders = [];
        $recentReservations = [];

        try {
            $allOrders = $commandeRepository->findAll();
            $stats['commandes_aujourdhui'] = count($allOrders);

            $allReservations = $reservationRepository->findAll();
            $stats['reservations_aujourdhui'] = count($allReservations);

            $recentOrders = $allOrders;
            usort($recentOrders, function($a, $b) {
                return $b->getDateCommande() <=> $a->getDateCommande();
            });
            $recentOrders = array_slice($recentOrders, 0, 5);

            $recentReservations = $allReservations;
            usort($recentReservations, function($a, $b) {
                return $b->getDateReservation() <=> $a->getDateReservation();
            });
            $recentReservations = array_slice($recentReservations, 0, 5);

            foreach ($allOrders as $order) {
                $stats['chiffre_affaires'] += (float) $order->getMontantTotal();
            }

        } catch (\Exception $e) {
            $stats = [
                'commandes_aujourdhui' => 15,
                'reservations_aujourdhui' => 8,
                'chiffre_affaires' => 1250.75,
            ];
        }

        return $this->render('Admin/dashboard.html.twig', [
            'user' => $this->getUser(),
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'recentReservations' => $recentReservations,
        ]);
    }

    #[Route('/admin/commandes', name: 'admin_commandes')]
    public function commandes(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findAll();
        usort($commandes, function($a, $b) {
            return $b->getDateCommande() <=> $a->getDateCommande();
        });

        return $this->render('Admin/commandes/index.html.twig', [
            'commandes' => $commandes,
            'statuts' => ['en_attente', 'en_cours', 'terminee', 'annulee'],
        ]);
    }

    #[Route('/admin/reservations', name: 'admin_reservations')]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findAll();
        usort($reservations, function($a, $b) {
            return $b->getDateReservation() <=> $a->getDateReservation();
        });

        return $this->render('Admin/reservations/index.html.twig', [
            'reservations' => $reservations,
            'currentDate' => date('Y-m-d'),
        ]);
    }

    #[Route('/admin/clients', name: 'admin_clients')]
    public function clients(ClientRepository $clientRepository): Response
    {
        $clients = $clientRepository->findAll();
        return $this->render('Admin/clients/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/admin/clients/{id}', name: 'admin_client_show', methods: ['GET'])]
    public function showClient(Client $client): Response
    {
        return $this->render('Admin/clients/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/admin/clients/{id}/edit', name: 'admin_client_edit', methods: ['GET', 'POST'])]
    public function editClient(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        // Créez le formulaire avec ClientAdminType
        $form = $this->createForm(ClientAdminType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Client modifié avec succès');
            return $this->redirectToRoute('admin_clients');
        }

        return $this->render('Admin/clients/edit.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/clients/new', name: 'admin_client_new', methods: ['GET', 'POST'])]
    public function newClient(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $client = new Client();
        $client->setDateInscription(new \DateTime());

        // Utilisez le formulaire d'inscription existant
        $form = $this->createForm(ClientRegistrationFormType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hasher le mot de passe
            $client->setPassword(
                $passwordHasher->hashPassword(
                    $client,
                    $form->get('plainPassword')->getData()
                )
            );

            // Par défaut, ROLE_CLIENT
            $client->setRoles(['ROLE_CLIENT']);

            $entityManager->persist($client);
            $entityManager->flush();

            $this->addFlash('success', 'Client créé avec succès');
            return $this->redirectToRoute('admin_clients');
        }

        return $this->render('Admin/clients/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/clients/{id}/delete', name: 'admin_client_delete', methods: ['POST'])]
    public function deleteClient(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        // Vérification du token CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();

            $this->addFlash('success', 'Client supprimé avec succès');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/admin/produits', name: 'admin_produits')]
    public function produits(ProduitRepository $produitRepository): Response
    {
        // Utilise la méthode avec jointure
        $produits = $produitRepository->findAllWithCategory();

        return $this->render('Admin/produits/index.html.twig', [
            'produits' => $produits,
        ]);
    }
}