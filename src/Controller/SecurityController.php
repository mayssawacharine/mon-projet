<?php

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
        // Redirect if already logged in
        if ($this->getUser()) {
            // Check roles for redirection
            if (in_array("ROLE_ADMIN", $this->getUser()->getRoles(), true)) {
                // Make sure you have a route named 'admin_dashboard'
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('app_client_dashboard');
        }

        // Get login error if any
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Render fallback page (in case Modal isn't used)
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}