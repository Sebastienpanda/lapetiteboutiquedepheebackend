<?php

namespace App\Controller;

use App\Entity\Token;
use App\Entity\User;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;


#[Route("/api/auth", name: "users")]
class UserController extends AbstractController
{

    #[Route("/users", name: "users", methods: ["GET"])]
    #[IsGranted("ROLE_ADMIN")]
    public function index(UserRepository $userRepository, MailerInterface $mailer)
    {

        $usersList = $userRepository->findAll();

        return new JsonResponse([
            "count" => count($usersList)
        ]);
    }

    #[Route('/signin', name: 'signin', methods: ["POST"])]
    public function store(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {

        $newUser = $serializer->deserialize(
            $serializer->serialize(
                [
                    ...$request->toArray()
                ],
                'json'
            ),
            User::class,
            'json'
        );

        $errors = $validator->validate($newUser);

        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => 'There was an error creating your account.',
                'errors' => array_map(static function ($error) {
                    return [
                        'field' => $error->getPropertyPath(),
                        'message' => $error->getMessage()
                    ];
                }, iterator_to_array($errors))
            ]);
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $newUser->getEmail()]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Compte existant'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $newUser
            ->setPassword(
                $passwordHasher->hashPassword(
                    $newUser,
                    $newUser->getPassword()
                )
            )
            ->setStatus(User::STATUS_INACTIVE);

        $token = uniqid("tk_", false);
        $hash = sha1($token);
        $createToken = (new Token())
            ->setToken($hash)
            ->setExp(new \DateTimeImmutable('+10 minutes'))
            ->setUser($newUser);


        $em->persist($createToken);
        $em->persist($newUser);
        $em->flush();

        $email = (new TemplatedEmail())
            ->from('lapetiteboutiquedephee@gmail.com')
            ->to($newUser->getEmail())
            ->subject('Vérification de l\'adresse mail')
            ->html("<p>Hello {$newUser->getFirstname()},</p>"
                . "<p>Merci de votre inscription sur La Petite Boutique D'Ephée !</p>"
                . "<p>S'il vous plait, cliquez sur le lien pour vérifier votre adresse mail:</p>"
                . "<p><a href='http://127.0.0.1:8000/api/auth/confirm-email/" . $newUser->getFirstname() . "_" . $token . "'>"
                . "Activer mon compte</a>"
                . "</p>"
                . "<p>A plus tard sur la Petite Boutique D'Ephée !</p>");

        $mailer->send($email);

        return new JsonResponse([
            "success" => true,
            'message' => 'Your account has been created. Please check your emails to activate it.'
        ], JsonResponse::HTTP_CREATED);
    }



    #[Route("/me", name: "me", methods: ["GET"])]
    #[IsGranted("ROLE_USER")]
    public function show(SerializerInterface $serializer)
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $response = $serializer->serialize(
            [
                'success' => true,
                'message' => 'You are logged in.',
                'data' => [
                    'user' => $user
                ]
            ],
            'json',
            [
                'groups' => 'user:read'
            ]
        );

        return new JsonResponse($response, json: true);
    }

    #[Route('/user/{id}', name: "updateUser", methods: ['PUT'])]
    #[IsGranted("ROLE_USER")]
    public function update($id, Request $request, SerializerInterface $serializer, User $currentUser, EntityManagerInterface $em): JsonResponse
    {

        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }
        if ($user instanceof User) {
            if ($user->getId() !== intval($id)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $updatedUser = $serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]
        );

        $em->persist($updatedUser);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/user/{id}', name: "destroyUser", methods: ['DELETE'])]
    #[IsGranted("ROLE_USER")]
    public function destroy($id, User $user, EntityManagerInterface $em): JsonResponse
    {

        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }
        if ($user instanceof User) {
            if ($user->getId() !== intval($id)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }


        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
    #[Route(
        '/confirm-email/{firstname}_{token}',
        name: 'confirm-email',
        requirements: ["firstname" => "[a-zA-Z0-9_\-]+", "token" => "(tk_){1}[a-z0-9]+"],
        methods: ['GET']
    )]
    public function confirmEmail(
        string $firstname,
        string $token,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        TokenRepository $tokenRepository,
        UserRepository $userRepository
    ) {
        $user = $userRepository->findOneBy(["firstname" => $firstname]);
        $tokenId = $tokenRepository->findOneBy(["token" => sha1($token)]);

        if (!$user || !$tokenId) {
            return $this->json([
                'success' => false
            ], Response::HTTP_NOT_FOUND);
        }

        $user->setStatus(User::STATUS_ACTIVE);
        //recuperer status
        $em->remove($tokenId);
        $em->persist($user);
        $em->flush();

        $email = (new TemplatedEmail())
            ->from('lapetiteboutiquedephee@gmail.com')
            ->to($user->getEmail())
            ->subject('Your account has been activated !')
            ->html(
                "<p>Hello {$user->getFirstname()},</p>"
                    . "<p>
                      Votre compte est bien activé !
                    </p>"
                    . "<p>A plus tard sur La Petite Boutique D'Ephée !</p>"
            );

        $mailer->send($email);

        return $this->json([
            'success' => true,
            'message' => 'Your account has been activated !'
        ], Response::HTTP_OK);
    }
}
