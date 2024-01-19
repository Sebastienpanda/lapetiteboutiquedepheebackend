<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(UserRepository $userRepository, SerializerInterface $serializer)
    {
        $usersList = $userRepository->findAll();

        return new JsonResponse([
            "count" => count($usersList)
        ]);
    }

    #[Route('/signin', name: 'signin', methods: ["POST"])]
    public function store(Request $request, SerializerInterface $serializer, JWTTokenManagerInterface $JWTManager, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
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
            );

        $em->persist($newUser);
        $em->flush();

        $token = $JWTManager->create($newUser);

        return new JsonResponse([
            'token' => $token,
            "success" => true
        ], JsonResponse::HTTP_OK, []);
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
}
