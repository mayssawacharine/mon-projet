<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\Reservation;

class ClientController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(
        Request $request,
        SessionInterface $session,
        ProduitRepository $produitRepo
    ): Response {
        // 1. Get Query Parameters
        $q = $request->query->get('q');
        $category = $request->query->get('category');
        $alphabetical = $request->query->get('alphabetical');
        $price = $request->query->get('price');

        // 2. Fetch Filtered Results from Database
        $results = $produitRepo->findFilteredProducts($q, $category, $alphabetical, $price);

        // 3. Fetch separate lists
        $foods = $produitRepo->findFilteredProducts(null, 'Food', null, null);
        $drinks = $produitRepo->findFilteredProducts(null, 'Drinks', null, null);

        // 🛒 CART SESSION
        $cart = $session->get('cart', []);

        return $this->render('client/home.html.twig', [
            'foods' => $foods,
            'drinks' => $drinks,
            'results' => $results,
            'cart' => $cart,
        ]);
    }

    // ============ MÉTHODE CORRIGÉE ============
    #[Route('/client/dashboard', name: 'app_client_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est connecté
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour accéder au dashboard.');
            return $this->redirectToRoute('app_login');
        }

        $client = $this->getUser();

        // Récupérer les commandes - CORRECTION ICI
        $commandes = $entityManager->getRepository(Commande::class)
            ->findBy(['client' => $client], ['dateCommande' => 'DESC']);

        // Récupérer les réservations - CORRECTION ICI
        $reservations = $entityManager->getRepository(Reservation::class)
            ->findBy(['client' => $client], ['dateReservation' => 'DESC']);

        return $this->render('client/dashboard.html.twig', [
            'client' => $client,
            'commandes' => $commandes,
            'reservations' => $reservations,
        ]);
    }

    #[Route('/product/{id}', name: 'product_show')]
    public function show(Produit $produit): Response
    {
        return $this->render('client/show.html.twig', [
            'produit' => $produit,
        ]);
    }
}