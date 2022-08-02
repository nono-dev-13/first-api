<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class AuthorController extends AbstractController
{
    //retourne l'ensemble des auteurs
    #[Route('/api/authors', name: 'author', methods:['GET'])]
    public function index(AuthorRepository $authorRepository, SerializerInterface $serializerInterface): JsonResponse
    {
        $authorList = $authorRepository->findAll();
        $jsonAuthorList = $serializerInterface->serialize($authorList, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    //retourne le détail d'un auteur
    #[Route('/api/authors/{id}', name: 'datailAuthor', methods:['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializerInterface): JsonResponse
    {
        $jsonAuthor = $serializerInterface->serialize($author, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    // Delete avec la methode DELETE
   #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
   public function deleteBook(Author $author, EntityManagerInterface $em): JsonResponse 
   {
       $em->remove($author);
       $em->flush();

       return new JsonResponse(null, Response::HTTP_NO_CONTENT);
   }

   // Création avec POST
   #[Route('/api/authors', name:"createAuthors", methods: ['POST'])]
   public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository): JsonResponse 
   {

       $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

       $em->persist($author);
       $em->flush();

       $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
       
       //Création de l'url
       $location = $urlGenerator->generate('datailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

       return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);
   }

   // modifie un author avec PUT
   #[Route('/api/authors/{id}', name:"updateAuthors", methods:['PUT'])]
    public function updateAuthor(Request $request, SerializerInterface $serializer,
        Author $currentAuthor, EntityManagerInterface $em): JsonResponse {

        $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);
        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

    }
}
