<?php

namespace App\Repository;

use App\Entity\CustomerPackageBooking;
use App\Entity\User;
use App\Enum\CustomerBookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPackageBooking>
 */
class CustomerPackageBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPackageBooking::class);
    }

    public function generateUniqueReferenceCode(): string
    {
        do {
            $code = 'RV-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (null !== $this->findOneBy(['referenceCode' => $code]));

        return $code;
    }

    /**
     * Bookings visible to this customer: submitted while logged in, owned traveler, or same contact email.
     *
     * @return list<CustomerPackageBooking>
     */
    public function findForCustomerUser(User $user): array
    {
        $qb = $this->createCustomerProfileQueryBuilder();

        $this->andWhereVisibleToCustomer($qb, $user);

        return $qb->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdForCustomerUser(int $id, User $user): ?CustomerPackageBooking
    {
        $qb = $this->createCustomerProfileQueryBuilder();
        $this->andWhereVisibleToCustomer($qb, $user);
        $qb->andWhere('b.id = :id')->setParameter('id', $id);

        $booking = $qb->getQuery()->getOneOrNullResult();

        return $booking instanceof CustomerPackageBooking ? $booking : null;
    }

    /**
     * Base query: joins needed for titles, images, and access checks.
     */
    private function createCustomerProfileQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.leadTraveler', 'lt')->addSelect('lt')
            ->leftJoin('b.travelPackage', 'tp')->addSelect('tp')
            ->leftJoin('tp.category', 'cat')->addSelect('cat')
            ->leftJoin('b.bookedProduct', 'bp')->addSelect('bp')
            ->leftJoin('bp.category', 'bpc')->addSelect('bpc');
    }

    /**
     * User can see rows they submitted, traveler rows they own, or rows matching their identity emails
     * (account email, username-as-email, contact email, or lead traveler email).
     */
    private function andWhereVisibleToCustomer(QueryBuilder $qb, User $user): void
    {
        $or = $qb->expr()->orX('b.submittedBy = :cu_u', 'lt.owner = :cu_u');

        $identityEmails = $this->normalizedIdentityEmails($user);
        if ($identityEmails !== []) {
            $or->add('LOWER(TRIM(b.contactEmail)) IN (:cu_emails)');
            $or->add('LOWER(TRIM(lt.email)) IN (:cu_emails)');
            $qb->setParameter('cu_emails', $identityEmails);
        }

        $qb->andWhere($or)->setParameter('cu_u', $user);
    }

    /**
     * Emails to match against booking.contactEmail and leadTraveler.email (API users often have username = email while `email` is unset).
     *
     * @return list<string>
     */
    private function normalizedIdentityEmails(User $user): array
    {
        $out = [];
        $e = $user->getEmail();
        if ($e !== null && trim($e) !== '') {
            $out[] = mb_strtolower(trim($e));
        }
        $ident = (string) $user->getUserIdentifier();
        if ($ident !== '' && str_contains($ident, '@')) {
            $out[] = mb_strtolower(trim($ident));
        }

        return array_values(array_unique($out, SORT_STRING));
    }

    /**
     * Total traveler seats counted as “sold” for the given status (e.g. completed trips).
     */
    public function sumTravelersForStatus(CustomerBookingStatus $status): int
    {
        $v = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.numberOfTravelers), 0)')
            ->andWhere('b.status = :s')
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $v;
    }

    /**
     * Lightweight snapshot for the admin bookings list live-update check.
     *
     * @return array{total: int, latestId: int, latestAt: string|null}
     */
    public function getAdminListSyncSnapshot(): array
    {
        $row = $this->createQueryBuilder('b')
            ->select('COUNT(b.id) AS total', 'MAX(b.id) AS latestId', 'MAX(b.createdAt) AS latestAt')
            ->getQuery()
            ->getSingleResult();

        $latestAt = $row['latestAt'] ?? null;

        return [
            'total' => (int) ($row['total'] ?? 0),
            'latestId' => (int) ($row['latestId'] ?? 0),
            'latestAt' => $latestAt instanceof \DateTimeInterface
                ? $latestAt->format(\DateTimeInterface::ATOM)
                : null,
            'fingerprint' => ((int) ($row['total'] ?? 0)).':'.((int) ($row['latestId'] ?? 0)),
        ];
    }
}
