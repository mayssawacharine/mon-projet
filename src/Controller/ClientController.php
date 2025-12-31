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
    #[Route('/', name: 'app_home')]
    public function home(
        Request $request,
        SessionInterface $session,
        ProduitRepository $produitRepo
    ): Response {
        // 1. Get Query Parameters
        $q = $request->query->get('q');
        $category = $request->query->get('category');
        $price = $request->query->get('price'); // e.g., "8"
        $sort = $request->query->get('sort');

        // 2. Map Sort Parameter
        $repoSortPrice = null;
        if ($sort === 'price_asc') {
            $repoSortPrice = 'low';
        } elseif ($sort === 'price_desc') {
            $repoSortPrice = 'high';
        }

        // 3. Fetch Filtered Results
        // ▼▼▼ CHANGED THIS LINE: Added $price as the 4th argument ▼▼▼
        $products = $produitRepo->findFilteredProducts($q, $category, $repoSortPrice, $price);

        // 4. Randomize only if no filters are active
        if (!$q && !$category && !$sort && !$price) {
            shuffle($products);
        }

        // 5. Cart Session
        $cart = $session->get('cart', []);

        return $this->render('client/home.html.twig', [
            'products' => $products,
            'cart' => $cart,
            'currentCategory' => $category,
            'currentSort' => $sort
        ]);
    }

    #[Route('/client/dashboard', name: 'app_client_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $client = $this->getUser();

        $commandes = $entityManager->getRepository(Commande::class)
            ->findBy(['client' => $client], ['dateCommande' => 'DESC']);

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