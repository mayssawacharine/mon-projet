<?php
// src/Controller/SecurityController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // 1. Rediriger les utilisateurs déjà connectés
        if ($this->getUser()) {
            $user = $this->getUser();

            if (in_array("ROLE_ADMIN", $user->getRoles(), strict: true)) {
                // CORRIGEZ ICI : 'admin_dashboard' au lieu de 'app_admin_dashboard'
                return $this->redirectToRoute('admin_dashboard');
            }

            // Redirection par défaut pour les autres rôles
            return $this->redirectToRoute('app_client_dashboard');
        }

        // Récupérer l'erreur de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        // Dernier nom d'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}