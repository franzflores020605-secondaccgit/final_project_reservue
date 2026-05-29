<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    private const ORDER_COLUMNS = [
        0 => 'l.createdAt',
        1 => 'l.username',
        2 => 'l.createdAt',
        3 => 'l.action',
        4 => 'l.details',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function filter(?string $user, ?string $action, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit);

        $this->applyFilters($qb, $user, $action, $from, $to, null);

        return $qb->getQuery()->getResult();
    }

    /**
     * Latest entries from the same audit log store as Admin → Audit Logs.
     *
     * @return AuditLog[]
     */
    public function findRecent(int $limit = 8): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countFiltered(
        ?string $user,
        ?string $action,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?string $globalSearch
    ): int {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        $this->applyFilters($qb, $user, $action, $from, $to, $globalSearch);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return AuditLog[]
     */
    public function findForDataTable(
        ?string $user,
        ?string $action,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?string $globalSearch,
        int $start,
        int $length,
        int $orderColumnIndex,
        string $orderDir
    ): array {
        $column = self::ORDER_COLUMNS[$orderColumnIndex] ?? 'l.createdAt';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('l')
            ->orderBy($column, $orderDir)
            ->setFirstResult($start)
            ->setMaxResults($length);

        $this->applyFilters($qb, $user, $action, $from, $to, $globalSearch);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(
        QueryBuilder $qb,
        ?string $user,
        ?string $action,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?string $globalSearch
    ): void {
        if ($user !== null && $user !== '') {
            $qb->andWhere('l.username LIKE :filterUser')->setParameter('filterUser', '%'.$user.'%');
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('l.action = :filterAction')->setParameter('filterAction', $action);
        }

        if ($from !== null) {
            $qb->andWhere('l.createdAt >= :filterFrom')->setParameter('filterFrom', $from);
        }

        if ($to !== null) {
            $qb->andWhere('l.createdAt <= :filterTo')->setParameter('filterTo', $to);
        }

        if ($globalSearch !== null && $globalSearch !== '') {
            $qb->andWhere($qb->expr()->orX(
                'l.username LIKE :gSearch',
                'l.action LIKE :gSearch',
                'l.details LIKE :gSearch'
            ))->setParameter('gSearch', '%'.$globalSearch.'%');
        }
    }

    /**
     * @return array{fingerprint: string, total: int, latestId: int}
     */
    public function getSyncSnapshot(): array
    {
        $row = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) AS total', 'MAX(l.id) AS latestId')
            ->getQuery()
            ->getSingleResult();

        $total = (int) ($row['total'] ?? 0);
        $latestId = (int) ($row['latestId'] ?? 0);

        return [
            'total' => $total,
            'latestId' => $latestId,
            'fingerprint' => $total.':'.$latestId,
        ];
    }
}

