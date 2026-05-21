<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LandingPageController extends AbstractController
{
    #[Route(path: ['/', '/landing_page'], name: 'app_landing')]
    public function index(Request $request, AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        return $this->render('landing_page/index.html.twig', \array_merge(
            $this->landingAuthContext($request, $authenticationUtils),
            $this->contactFormContext($csrfTokenManager),
            [
                'landing_inner' => false,
                'landing_enable_scroll_spy' => true,
                'team_members' => $this->meetTheTeamMembers(),
            ]
        ));
    }

    #[Route('/about', name: 'app_landing_about')]
    public function about(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('landing_page/about.html.twig', \array_merge(
            $this->landingAuthContext($request, $authenticationUtils),
            [
                'landing_inner' => true,
                'landing_enable_scroll_spy' => false,
                'landing_nav' => 'about',
                'hero_title' => 'About ReserVue',
                'hero_subtitle' => 'Your trusted travel partner',
                'team_members' => $this->meetTheTeamMembers(),
            ]
        ));
    }

    #[Route('/contact', name: 'app_landing_contact')]
    public function contact(Request $request, AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        return $this->render('landing_page/contact.html.twig', \array_merge(
            $this->landingAuthContext($request, $authenticationUtils),
            $this->contactFormContext($csrfTokenManager),
            [
                'landing_inner' => true,
                'landing_enable_scroll_spy' => false,
                'landing_nav' => 'contact',
                'hero_title' => 'Contact us',
                'hero_subtitle' => 'We are here to help with your travel plans',
            ]
        ));
    }

    /**
     * Meet the Team (max 3). Replace name, role, and `photo` (path under `public/`) as you add people.
     * Upload new headshots to `public/images/team/`.
     *
     * @return list<array{name: string, role: string, photo: string|null}>
     */
    private function meetTheTeamMembers(): array
    {
        return [
            [
                'name' => 'Franz Ylienn F. Flores',
                'role' => 'Developer',
                'photo' => 'images/team/member-1.png',
            ],
            [
                'name' => 'Franz Ylienn F. Flores',
                'role' => 'Owner & Travel Consultant',
                'photo' => 'images/team/member-2.jpg',
            ],
            [
                'name' => 'Franz Ylienn F. Flores',
                'role' => 'Admin',
                'photo' => 'images/team/member-3.jpg',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactFormContext(CsrfTokenManagerInterface $csrfTokenManager): array
    {
        return [
            'contact_form_csrf' => $csrfTokenManager->getToken(ContactMessageController::CSRF_TOKEN_ID)->getValue(),
            'load_contact_form_assets' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function landingAuthContext(Request $request, AuthenticationUtils $authenticationUtils): array
    {
        $registrationForm = $this->createForm(RegistrationFormType::class, new User());

        return [
            'last_username' => $authenticationUtils->getLastUsername(),
            'login_error' => $authenticationUtils->getLastAuthenticationError(),
            'open_login_modal' => $request->query->getBoolean('signin'),
            'open_register_modal' => $request->query->getBoolean('register'),
            'registrationForm' => $registrationForm->createView(),
        ];
    }
}
