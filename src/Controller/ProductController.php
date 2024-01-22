<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route("/api/product", name: "produits")]
class ProductController extends AbstractController
{
    #[Route('/', name: 'all_produits', methods: ["GET"])]
    public function index(ProductRepository $productRepository, SerializerInterface $serializerInterface): JsonResponse
    {

        $product = $productRepository->findBy([], ["id" => "DESC"]);

        $jsonProduct = $serializerInterface->serialize($product, 'json', ['groups' => "product:collection"]);

        return new JsonResponse($jsonProduct, JsonResponse::HTTP_OK, [], true);
    }

    #[Route('/auth/create', name: 'create_product', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN")]
    public function store(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {

        $user = $this->getUser();

        if ($user instanceof User) {
            $author = $userRepository->findOneBy(['id' => $user->getId()]);
        }

        $data = $request->request->all();

        $product = new Product();
        $product->setName($data['name']);
        $product->setPrice($data['price']);
        $product->setContent($data['content']);
        $product->setUser($author);
        $product->setStock($data["stock"]);
        $entityManager->persist($product);
        $entityManager->flush();

        $productData = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'content' => $product->getContent(),
            'price' => $product->getPrice(),
            "stock" => $product->getStock(),
            'user' => [
                'id' => $product->getUser()->getId(),
                'firstname' => $product->getUser()->getFirstname(),
            ],
        ];

        return new JsonResponse(['product' => $productData], JsonResponse::HTTP_CREATED);
    }

    #[Route("/{id}", name: 'show_product', methods: ["GET"])]
    public function show($id, SerializerInterface $serializer, ProductRepository $product): JsonResponse
    {
        $showProduct = $product->findBy(['id' => $id]);

        if (!$showProduct) {
            return new JsonResponse([
                "success" => false,
                "message" => "Product not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = $serializer->serialize([
            "success" => true,
            "message" => "Success data",
            'data' => [
                "products" => $showProduct
            ]
        ], 'json', [
            "groups" => ["product:show"]
        ]);

        return new JsonResponse($response, json: true);
    }

    #[Route('/auth/update/{id}', name: "update_product", methods: ['POST'])]
    #[IsGranted("ROLE_ADMIN")]
    public function update($id, Request $request, ProductRepository $productRepository, EntityManagerInterface $em): JsonResponse
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

        $product = $productRepository->findOneBy(["id" => $id]);

        if (!$product) {
            return new JsonResponse([
                "success" => false,
                "message" => "Product not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $product->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $data = $request->request->all();

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['content'])) {
            $product->setContent($data['content']);
        }

        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }

        if (isset($data['stock'])) {
            $product->setStock($data['stock']);
        }

        $em->persist($product);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/auth/delete/{id}', name: "destroy_product", methods: ['DELETE'])]
    #[IsGranted("ROLE_ADMIN")]
    public function destroy($id, ProductRepository $product, EntityManagerInterface $em): JsonResponse
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

        $product = $product->find($id);

        if (!$product) {
            return new JsonResponse([
                "success" => false,
                "message" => "Artice not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $product->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }


        $em->remove($product);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
