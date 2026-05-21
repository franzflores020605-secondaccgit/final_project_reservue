<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use App\Service\RoleDisplayFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminAuditLogController extends AbstractController
{
    private const MANILA_TZ = 'Asia/Manila';

    #[Route('/admin/logs', name: 'app_admin_logs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin_logs/index.html.twig');
    }

    #[Route('/admin/logs/data', name: 'app_admin_logs_data', methods: ['GET'])]
    public function data(Request $request, AuditLogRepository $repo, RoleDisplayFormatter $roleDisplayFormatter): JsonResponse
    {
        $draw = (int) $request->query->get('draw', 1);
        $start = max(0, (int) $request->query->get('start', 0));
        $length = (int) $request->query->get('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min(100, $length);

        $query = $request->query->all();
        $searchBlock = $query['search'] ?? [];
        $globalSearch = \is_array($searchBlock)
            ? trim((string) ($searchBlock['value'] ?? ''))
            : '';

        $filterUser = $request->query->get('filter_user');
        $filterUser = \is_string($filterUser) ? $filterUser : '';
        $filterAction = $request->query->get('filter_action');
        $filterAction = \is_string($filterAction) ? $filterAction : '';

        $from = $this->parseDateStart($request->query->get('filter_from'));
        $to = $this->parseDateEnd($request->query->get('filter_to'));

        $order = \is_array($query['order'] ?? null) ? $query['order'] : [];
        $orderCol = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDir = isset($order[0]['dir']) ? (string) $order[0]['dir'] : 'desc';

        $recordsTotal = $repo->countAll();
        $recordsFiltered = $repo->countFiltered(
            $filterUser !== '' ? $filterUser : null,
            $filterAction !== '' ? $filterAction : null,
            $from,
            $to,
            $globalSearch !== '' ? $globalSearch : null
        );

        $logs = $repo->findForDataTable(
            $filterUser !== '' ? $filterUser : null,
            $filterAction !== '' ? $filterAction : null,
            $from,
            $to,
            $globalSearch !== '' ? $globalSearch : null,
            $start,
            $length,
            $orderCol,
            $orderDir
        );

        $data = [];
        foreach ($logs as $log) {
            $createdAtManila = \DateTimeImmutable::createFromInterface($log->getCreatedAt())
                ->setTimezone(new \DateTimeZone(self::MANILA_TZ));

            $data[] = [
                'createdAt' => $createdAtManila->format('Y-m-d H:i:s'),
                'username' => $log->getUsername() ?? 'N/A',
                'roleLabel' => $roleDisplayFormatter->primaryLabel($log->getRoles()),
                'action' => $log->getAction(),
                'affectedData' => $log->getDetails() ?? '—',
            ];
        }

        return $this->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function parseDateStart(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value.' 00:00:00', new \DateTimeZone(self::MANILA_TZ)));
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateEnd(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value.' 23:59:59', new \DateTimeZone(self::MANILA_TZ)));
        } catch (\Exception) {
            return null;
        }
    }
}

