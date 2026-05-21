<?php

namespace App\Repository;

use App\Entity\Traveler;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Traveler>
 */
class TravelerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Traveler::class);
    }

    /**
     * Admin: all travelers. Staff: own + web submissions (owner null). Others: own only.
     *
     * @return Traveler[]
     */
    public function findForDashboardUser(User $user, bool $isAdmin, bool $isStaff): array
    {
        if ($isAdmin) {
            return $this->createQueryBuilder('t')
                ->orderBy('t.id', 'ASC')
                ->getQuery()
                ->getResult();
        }

        if ($isStaff) {
            return $this->createQueryBuilder('t')
                ->orderBy('t.id', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->findBy(['owner' => $user], ['id' => 'ASC']);
    }

    //    /**
    //     * @return Traveler[] Returns an array of Traveler objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Traveler
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
