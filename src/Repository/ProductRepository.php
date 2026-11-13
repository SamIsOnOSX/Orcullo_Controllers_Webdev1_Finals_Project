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
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** Legacy shop ordering: newest first. @return list<Product> */
    public function findAllNewestFirst(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search products by name or description (used by the admin/staff
     * tables and the storefront header search).
     *
     * @return list<Product>
     */
    public function searchProducts(string $term): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC');

        if ($term !== '') {
            $qb->andWhere($qb->expr()->orX(
                'p.name LIKE :term',
                'p.description LIKE :term'
            ))->setParameter('term', '%' . $term . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** Products at or below the low-stock threshold (out of stock included). @return list<Product> */
    public function findLowStock(int $threshold = self::LOW_STOCK_THRESHOLD): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
