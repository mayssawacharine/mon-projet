<?php

namespace App\Controller;

use App\Entity\Administrateur;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\ProduitRepository;
use App\Entity\Client;
use App\Entity\Produit;
use App\Form\ClientAdminType;
use App\Form\ClientRegistrationFormType;
use App\Form\ProduitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
// Import the Slugger for safe filename handling
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
class AdministrateurController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $stats = [
            'commandes_aujourdhui' => 0,
            'reservations_aujourdhui' => 0,
            'chiffre_affaires' => 0,
        ];

        try {
            $recentOrders = $commandeRepository->findBy([], ['dateCommande' => 'DESC'], 5);
            $recentReservations = $reservationRepository->findBy([], ['dateReservation' => 'DESC'], 5);

            $allOrders = $commandeRepository->findAll();
            $stats['commandes_aujourdhui'] = count($allOrders);

            $allReservations = $reservationRepository->findAll();
            $stats['reservations_aujourdhui'] = count($allReservations);

            foreach ($allOrders as $order) {
                $stats['chiffre_affaires'] += (float) $order->getMontantTotal();
            }

        } catch (\Exception $e) {
            $stats = [
                'commandes_aujourdhui' => 0,
                'reservations_aujourdhui' => 0,
                'chiffre_affaires' => 0,
            ];
            $recentOrders = [];
            $recentReservations = [];
        }

        return $this->render('Admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'recentReservations' => $recentReservations,
        ]);
    }

    #[Route('/commandes', name: 'admin_commandes')]
    public function commandes(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findBy([], ['dateCommande' => 'DESC']);

        return $this->render('Admin/commandes/index.html.twig', [
            'commandes' => $commandes,
            'statuts' => ['en_attente', 'en_cours', 'terminee', 'annulee'],
        ]);
    }
    #[Route('/commandes/{id}/update-status', name: 'admin_commande_update_status', methods: ['POST'])]
    public function updateCommandeStatus(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('update_status_' . $commande->getId(), $request->request->get('_token'))) {

            $newStatus = $request->request->get('statut');
            $allowedStatuses = ['en_attente', 'en_cours', 'terminee', 'annulee'];

            if (in_array($newStatus, $allowedStatuses)) {

                // 1. Update the Status
                $commande->setStatus($newStatus);

                // 2. === SAVE THE ADMIN ID ===
                // Get the current logged in user
                $user = $this->getUser();

                // Check if the user is actually an Administrator
                if ($user instanceof Administrateur) {
                    $commande->setAdministrateur($user);
                }
                // ============================

                $entityManager->flush();

                $this->addFlash('success', 'Statut mis à jour par ' . $user->getPrenom() . '.');
            } else {
                $this->addFlash('error', 'Statut invalide.');
            }
        } else {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_commandes');
    }

    #[Route('/reservations', name: 'admin_reservations')]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findBy([], ['dateReservation' => 'DESC']);

        return $this->render('Admin/reservations/index.html.twig', [
            'reservations' => $reservations,
            'currentDate' => date('Y-m-d'),
        ]);
    }

    #[Route('/clients', name: 'admin_clients')]
    public function clients(ClientRepository $clientRepository): Response
    {
        $clients = $clientRepository->findAll();
        return $this->render('Admin/clients/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/clients/new', name: 'admin_client_new', methods: ['GET', 'POST'])]
    public function newClient(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $client = new Client();
        $client->setDateInscription(new \DateTime());

        $form = $this->createForm(ClientRegistrationFormType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client->setPassword(
                $passwordHasher->hashPassword(
                    $client,
                    $form->get('plainPassword')->getData()
                )
            );

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

    #[Route('/clients/{id}', name: 'admin_client_show', methods: ['GET'])]
    public function showClient(Client $client): Response
    {
        return $this->render('Admin/clients/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/clients/{id}/edit', name: 'admin_client_edit', methods: ['GET', 'POST'])]
    public function editClient(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
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

    #[Route('/clients/{id}/delete', name: 'admin_client_delete', methods: ['POST'])]
    public function deleteClient(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
            $this->addFlash('success', 'Client supprimé avec succès');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/produits', name: 'admin_produits')]
    public function produits(ProduitRepository $produitRepository): Response
    {
        // If 'findAllWithCategory' is a custom method you created, keep it.
        // Otherwise, revert to: $produits = $produitRepository->findAll();
        $produits = $produitRepository->findAll();
        // $produits = $produitRepository->findAllWithCategory();

        return $this->render('Admin/produits/index.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/produits/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function newProduit(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $produit->setDisponible(true);

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Image Upload
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Erreur upload image: ' . $e->getMessage());
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

    #[Route('/produits/{id}', name: 'admin_produit_show', methods: ['GET'])]
    public function showProduit(Produit $produit): Response
    {
        return $this->render('Admin/produits/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/produits/{id}/edit', name: 'admin_produit_edit', methods: ['GET', 'POST'])]
    public function editProduit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Image Upload
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );

                    // Delete old image if it exists
                    if ($produit->getImage()) {
                        $oldImagePath = $this->getParameter('produits_directory').'/'.$produit->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
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

    #[Route('/produits/{id}/delete', name: 'admin_produit_delete', methods: ['POST'])]
    public function deleteProduit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            // Remove image from folder upon deletion
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