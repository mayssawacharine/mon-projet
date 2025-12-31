<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    // RENAMED to 'app_cart' to match base.html.twig
    #[Route('/', name: 'app_cart')]
    public function index(SessionInterface $session, ProduitRepository $produitRepo): Response
    {
        $cart = $session->get('cart', []);
        $cartData = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $produit = $produitRepo->find($id);
            if ($produit) {
                $cartData[] = [
                    'produit' => $produit, // Note: Key is 'produit'
                    'quantity' => $quantity
                ];
                $total += $produit->getPrix() * $quantity;
            }
        }

        return $this->render('cart/index.html.twig', [
            'items' => $cartData,
            'total' => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'cart_add')]
    public function add(Produit $produit, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $id = $produit->getId();

        if (!empty($cart[$id])) {
            $cart[$id]++;
        } else {
            $cart[$id] = 1;
        }

        $session->set('cart', $cart);

        // FIXED: Redirects to 'app_home' instead of 'home'
        return $this->redirectToRoute('app_home');
    }

    #[Route('/remove/{id}', name: 'cart_remove')]
    public function remove($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (!empty($cart[$id])) {
            unset($cart[$id]);
        }

        $session->set('cart', $cart);

        // FIXED: Redirects to 'app_cart'
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'cart_checkout')]
    public function checkout(SessionInterface $session, EntityManagerInterface $em, ProduitRepository $produitRepo): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Please login to complete your order.');
            return $this->redirectToRoute('app_login');
        }

        $cart = $session->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('warning', 'Your cart is empty.');
            return $this->redirectToRoute('app_cart');
        }

        $client = $this->getUser();
        $commande = new Commande();
        $commande->setClient($client);
        $commande->setDateCommande(new \DateTime());
        $commande->setStatus('Pending'); // Consider using 'En attente' if your Enum expects French
        $commande->setModePaiement('Cash on delivery');

        $total = 0;

        foreach ($cart as $id => $quantity) {
            $produit = $produitRepo->find($id);
            if ($produit) {
                $ligne = new LigneCommande();
                $ligne->setProduit($produit);
                $ligne->setQuantite($quantity);
                $ligne->setPrixUnitaire($produit->getPrix());
                $ligne->setCommande($commande);

                $total += $produit->getPrix() * $quantity;
                $em->persist($ligne);
            }
        }

        $commande->setMontantTotal((string)$total);
        $em->persist($commande);
        $em->flush();

        $session->set('cart', []);
        $this->addFlash('success', 'Order confirmed!');

        return $this->redirectToRoute('app_client_dashboard');
    }

    #[Route('/increase/{id}', name: 'cart_increase')]
    public function increase($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        if (!empty($cart[$id])) {
            $cart[$id]++;
        }
        $session->set('cart', $cart);
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/decrease/{id}', name: 'cart_decrease')]
    public function decrease($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        if (!empty($cart[$id])) {
            if ($cart[$id] > 1) {
                $cart[$id]--;
            } else {
                unset($cart[$id]);
            }
        }
        $session->set('cart', $cart);
        return $this->redirectToRoute('app_cart');
    }
}