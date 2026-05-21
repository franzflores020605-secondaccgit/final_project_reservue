<?php

namespace App\Repository;

use App\Entity\TravelPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelPackage>
 */
class TravelPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelPackage::class);
    }

    /**
     * Published packages with products and category loaded (for live pricing and filters).
     *
     * @return TravelPackage[]
     */
    public function findPublishedForCatalog(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.products', 'p')->addSelect('p')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->andWhere('t.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Admin list: stable order by primary key (newest IDs follow older ones in sequence).
     *
     * @return TravelPackage[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.products', 'p')->addSelect('p')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Package with linked products (for inventory checks / receipts).
     */
    public function findWithProductsById(int $id): ?TravelPackage
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.products', 'p')->addSelect('p')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Sum of each package’s listed price per person (admin-set catalog value, independent of included products).
     */
    public function sumListedPricePerPerson(): float
    {
        $v = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.pricePerPerson), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $v;
    }
}
