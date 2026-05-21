<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Products staff/admin marked for the book page, with an image and available quantity.
     *
     * @return Product[]
     */
    public function findEligibleForBookPage(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->andWhere('p.showOnBookPage = :vis')
            ->setParameter('vis', true)
            ->andWhere('p.quantity > 0')
            ->andWhere('p.imagePath IS NOT NULL')
            ->andWhere('p.imagePath != :empty')
            ->setParameter('empty', '')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sum of (unit price × quantity) across all product SKUs — stock line value.
     */
    public function sumStockLineValue(): float
    {
        $v = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.price * p.quantity), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $v;
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
