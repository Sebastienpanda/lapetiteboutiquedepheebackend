<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    #[Groups(["user:read", "article:show"])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[NotBlank(message: 'This lastname is required.')]
    #[Length(
        max: 255,
        maxMessage: 'The lastname cannot be longer than {{ limit }} characters.'
    )]
    #[Groups(["user:read"])]
    private ?string $lastname = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Article::class, orphanRemoval: true)]
    #[Groups(["user:read"])]
    private Collection $articles;

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BANNED = 'banned';
    public const STATUS_ACTIVE = 'active';
    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Product::class, orphanRemoval: true)]
    #[Groups(["user:read"])]
    private Collection $products;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->articles = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setUser($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getUser() === $this) {
                $article->setUser(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_INACTIVE, self::STATUS_BANNED, self::STATUS_ACTIVE])) {
            throw new \InvalidArgumentException("Invalid status value: $status");
        }

        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setUser($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getUser() === $this) {
                $product->setUser(null);
            }
        }

        return $this;
    }
}
