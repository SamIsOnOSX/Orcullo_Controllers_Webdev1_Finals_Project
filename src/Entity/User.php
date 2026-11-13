<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Maps the legacy `users` table 1:1. The role column is a MySQL
 * ENUM('customer','admin') in the legacy schema; Doctrine reads/writes it
 * as a plain string so no custom type is required.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: 'string', length: 50)]
    private string $username = '';

    #[ORM\Column(name: 'email', type: 'string', length: 255, unique: true)]
    private string $email = '';

    #[ORM\Column(name: 'address', type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'password', type: 'string', length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'role', type: 'string', length: 10, options: ['default' => 'customer'])]
    private string $role = self::ROLE_CUSTOMER;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /* ----------------------------------------------------------------
     * Security\UserInterface
     * ---------------------------------------------------------------- */

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->role === self::ROLE_ADMIN
            ? ['ROLE_ADMIN', 'ROLE_USER']
            : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // Nothing sensitive is stored beyond the hashed password.
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
