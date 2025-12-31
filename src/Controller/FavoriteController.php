<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/favorite')]
class FavoriteController extends AbstractController
{
    #[Route('/', name: 'app_favorite')]
    public function index(SessionInterface $session, ProduitRepository $produitRepo): Response
    {
        // Get favorite IDs from session
        $favorites = $session->get('favorites', []);

        // Fetch products based on IDs
        $products = [];
        if (!empty($favorites)) {
            $products = $produitRepo->findBy(['id' => array_keys($favorites)]);
        }

        return $this->render('favorite/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/toggle/{id}', name: 'favorite_toggle')]
    public function toggle($id, SessionInterface $session, Request $request): Response
    {
        $favorites = $session->get('favorites', []);

        if (!empty($favorites[$id])) {
            // If already in favorites, remove it
            unset($favorites[$id]);
        } else {
            // Add to favorites (store as ID => true)
            $favorites[$id] = true;
        }

        $session->set('favorites', $favorites);

        // Redirect back to the page the user came from
        return $this->redirect($request->headers->get('referer'));
    }
}