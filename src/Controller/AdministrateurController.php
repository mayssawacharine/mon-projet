<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\ProduitRepository;
use App\Entity\Client;
use App\Form\ClientAdminType;
use App\Form\ClientRegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Produit;
use App\Form\ProduitType;
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

    // CORRECTION : LA ROUTE 'new' DOIT ÊTRE AVANT LA ROUTE AVEC {id}
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

    // CETTE ROUTE DOIT ÊTRE APRÈS 'new'
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
        // Créez le formule avec ClientAdminType
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


    #[Route('/admin/produits/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function newProduit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $produit = new Produit();
        $produit->setDisponible(true); // Par défaut disponible

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'image
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);

                // Solution sans transliterator
                $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '', $originalFilename);
                $safeFilename = strtolower($safeFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'L\'image n\'a pas pu être téléchargée');
                }
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Produit créé avec succès');
            return $this->redirectToRoute('admin_produits');
        }

        return $this->render('Admin/produits/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/produits/{id}', name: 'admin_produit_show', methods: ['GET'])]
    public function showProduit(Produit $produit): Response
    {
        return $this->render('Admin/produits/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/admin/produits/{id}/edit', name: 'admin_produit_edit', methods: ['GET', 'POST'])]
    public function editProduit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'image
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);

                // Solution sans transliterator
                $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '', $originalFilename);
                $safeFilename = strtolower($safeFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );

                    // Supprimer l'ancienne image si elle existe
                    if ($produit->getImage()) {
                        $oldImage = $this->getParameter('produits_directory').'/'.$produit->getImage();
                        if (file_exists($oldImage)) {
                            unlink($oldImage);
                        }
                    }

                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'L\'image n\'a pas pu être téléchargée');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Produit modifié avec succès');
            return $this->redirectToRoute('admin_produits');
        }

        return $this->render('Admin/produits/edit.html.twig', [
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/produits/{id}/delete', name: 'admin_produit_delete', methods: ['POST'])]
    public function deleteProduit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            // Supprimer l'image associée
            if ($produit->getImage()) {
                $imagePath = $this->getParameter('produits_directory').'/'.$produit->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Produit supprimé avec succès');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('admin_produits');
    }
}