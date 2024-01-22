<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route("/api/article", name: "articles")]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'all_article', methods: ["GET"])]
    public function index(ArticleRepository $article, SerializerInterface $serializer): JsonResponse
    {

        $article = $article->findBy([], ["id" => "DESC"]);

        $jsonArticle = $serializer->serialize($article, 'json', ['groups' => 'article:collection']);

        return new JsonResponse($jsonArticle, JsonResponse::HTTP_OK, [], true);
    }
    #[Route('/auth/create', name: 'create_article', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN")]
    public function store(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $author = $userRepository->findOneBy(['id' => $user->getId()]);
        }

        $data = $request->request->all();
        $uploadedFile = $request->files->get('imageFile');

        $article = new Article();
        $article->setTitle($data['title']);
        $article->setContent($data['content']);
        $article->setUser($author);
        $article->setImageFile($uploadedFile);
        $entityManager->persist($article);
        $entityManager->flush();

        $articleData = [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'content' => $article->getContent(),
            "thumbnail" => $article->getThumbnail(),
            "slug" => $article->getSlug(),
            'createdAt' => $article->getCreatedAt(),
            'user' => [
                'id' => $article->getUser()->getId(),
                'firstname' => $article->getUser()->getFirstname(),
            ],

        ];

        return new JsonResponse(['article' => $articleData], JsonResponse::HTTP_CREATED);
    }

    #[Route("/{id}", name: 'show-article', methods: ["GET"])]
    public function show($id, SerializerInterface $serializer, ArticleRepository $article): JsonResponse
    {

        $showArticle = $article->findBy(['id' => $id]);

        if (!$showArticle) {
            return new JsonResponse([
                "success" => false,
                "message" => "Artice not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = $serializer->serialize([
            "success" => true,
            "message" => "Success data",
            'data' => [
                "article" => $showArticle
            ]
        ], 'json', [
            "groups" => ["article:show"]
        ]);

        return new JsonResponse($response, json: true);
    }

    #[Route('/auth/update/{id}', name: "update_article", methods: ['POST'])]
    #[IsGranted("ROLE_ADMIN")]
    public function update($id, Request $request, ArticleRepository $articleRepository, EntityManagerInterface $em): JsonResponse
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

        $article = $articleRepository->findOneBy(["id" => $id]);

        if (!$article) {
            return new JsonResponse([
                "success" => false,
                "message" => "Article not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $article->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $data = $request->request->all();

        if (isset($data['title'])) {
            $article->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $article->setContent($data['content']);
        }

        $newImageFile = $request->files->get('imageFile');

        if ($newImageFile) {
            // Supprimer l'image existante
            // Beug à corriger, delete image not march
            $oldImage = $article->getThumbnail(); // Assurez-vous de récupérer le chemin vers l'image existante
            if ($oldImage) {
                // Supprimez manuellement l'ancien fichie
                // Mettez à jour l'entité pour refléter la suppression
                $article->setImageFile(null);
                $article->setThumbnail("");
            }

            // Ajouter la nouvelle image
            $article->setImageFile($newImageFile);
        }
        $em->persist($article);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/auth/delete/{id}', name: "destroy_article", methods: ['DELETE'])]
    #[IsGranted("ROLE_ADMIN")]
    public function destroy($id, ArticleRepository $article, EntityManagerInterface $em): JsonResponse
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

        $article = $article->find($id);

        if (!$article) {
            return new JsonResponse([
                "success" => false,
                "message" => "Artice not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $article->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }


        $em->remove($article);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
