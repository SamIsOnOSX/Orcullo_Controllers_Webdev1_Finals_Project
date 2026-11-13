<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Legacy login accepts either the username or the email as identifier.
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :identifier OR u.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * True when another account already owns the username or email.
     */
    public function existsByUsernameOrEmailExcluding(string $username, string $email, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.id')
            ->andWhere('u.username = :username OR u.email = :email')
            ->setParameter('username', $username)
            ->setParameter('email', $email);

        if ($excludeId !== null) {
            $qb->andWhere('u.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /** Legacy admin listing: newest accounts first. @return list<User> */
    public function findAllNewestFirst(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Admin search & filter: matches username, email or role, optionally
     * narrowed to one exact role.
     *
     * @return list<User>
     */
    public function searchUsers(string $term, string $role = ''): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        if ($term !== '') {
            $qb->andWhere($qb->expr()->orX(
                'u.username LIKE :term',
                'u.email LIKE :term',
                'u.role LIKE :term'
            ))->setParameter('term', '%' . $term . '%');
        }

        if ($role !== '') {
            $qb->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        return $qb->getQuery()->getResult();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
