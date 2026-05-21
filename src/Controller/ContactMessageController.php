<?php

namespace App\Controller;

use App\Dto\ContactFormSubmission;
use App\Service\BrevoContactFormService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactMessageController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'brevo_contact';

    #[Route('/contact/api/submit', name: 'app_contact_brevo_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        ValidatorInterface $validator,
        BrevoContactFormService $brevoContactFormService,
    ): JsonResponse {
        $raw = $request->getContent();
        $data = \is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!\is_array($data)) {
            $data = [];
        }

        $tokenValue = $request->headers->get('X-CSRF-TOKEN')
            ?? $request->headers->get('X-Csrf-Token')
            ?? ($data['_token'] ?? '');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $tokenValue))) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Security token expired. Please reload the page and try again.',
            ], Response::HTTP_FORBIDDEN);
        }

        $submission = new ContactFormSubmission(
            name: trim((string) ($data['name'] ?? '')),
            email: trim((string) ($data['email'] ?? '')),
            message: trim((string) ($data['message'] ?? '')),
        );

        $violations = $validator->validate($submission);
        if (\count($violations) > 0) {
            $fieldErrors = [];
            foreach ($violations as $v) {
                $path = $v->getPropertyPath();
                if ($path !== '' && !isset($fieldErrors[$path])) {
                    $fieldErrors[$path] = $v->getMessage();
                }
            }

            return new JsonResponse([
                'ok' => false,
                'error' => 'Please check the form for errors.',
                'fieldErrors' => $fieldErrors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $brevoContactFormService->submit($submission);
        } catch (ServiceUnavailableHttpException $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Thank you! Your message has been sent. We will get back to you soon.',
        ]);
    }
}
