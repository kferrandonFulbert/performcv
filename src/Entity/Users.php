<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
#[ORM\Entity(repositoryClass: UsersRepository::class)]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    public function __serialize(): array
    {
        // Access a mapped property first: associations may expose a native lazy user.
        $passwordHash = $this->password_hash;
        $data = (array) $this;
        // Symfony 7.4 can detect password changes without persisting the password hash in sessions.
        $data["\0".self::class."\0password_hash"] = $passwordHash === null ? null : hash('crc32c', $passwordHash);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $password_hash = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $first_name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $last_name = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(length: 50, nullable: true, options: ["default" => 'free'])]
    private ?string $subscription_type = 'free';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(string $password_hash): static
    {
        $this->password_hash = $password_hash;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): static
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): static
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getSubscriptionType(): ?string
    {
        return $this->subscription_type;
    }

    public function setSubscriptionType(?string $subscription_type): static
    {
        $this->subscription_type = $subscription_type;

        return $this;
    }
}
