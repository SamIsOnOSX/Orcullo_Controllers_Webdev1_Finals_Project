<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** All orders with the customer and items joined in, newest first (legacy admin view). @return list<Order> */
    public function findAllNewestFirst(): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('u', 'i')
            ->leftJoin('o.user', 'u')
            ->leftJoin('o.items', 'i')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** A customer's own order history, newest first (legacy user dashboard). @return list<Order> */
    public function findByUserNewestFirst(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalAmount(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Orders grouped by status, sorted by count descending — feeds the
     * dashboard bar chart.
     *
     * @return list<array{status: string, count: int}>
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS count')
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_map(
            static fn (array $row): array => [
                'status' => (string) $row['status'],
                'count' => (int) $row['count'],
            ],
            $rows
        );
    }
}
