<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["read:User:collection"])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[NotBlank(message: "This email is required.")]
    #[Email(message: "This email is not a valid email.")]
    #[Groups(["user:read"])]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255)]
    #[NotBlank(message: 'This password is required.')]
    #[Length(
        min: 12,
        max: 255,
        minMessage: 'The password must be at least {{ limit }} characters long.',
        maxMessage: 'The password cannot be longer than {{ limit }} characters.'
    )]
    #[Regex(
        pattern: '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[#@.\/+-])/',
        message: 'Password must contain at least one minuscule, one majuscule, one number and one special char (#@./+-)'
    )]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[NotBlank(message: 'This firstname is required.')]
    #[Length(
        max: 255,
        maxMessage: 'The firstname cannot be longer than {{ limit }} characters.'
    )]
    #[Groups(["user:read"])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[NotBlank(message: 'This lastname is required.')]
    #[Length(
        max: 255,
        maxMessage: 'The lastname cannot be longer than {{ limit }} characters.'
    )]
    #[Groups(["user:read"])]
    private ?string $lastname = null;

    public function __construct()
    {

        $this->roles = ['ROLE_USER'];
    }

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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }
}
