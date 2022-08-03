<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorController extends AbstractController
{
    //retourne l'ensemble des auteurs
    #[Route('/api/authors', name: 'author', methods:['GET'])]
    public function index(AuthorRepository $authorRepository, SerializerInterface $serializerInterface, Request $request): JsonResponse
    {

        //$authorList = $authorRepository->findAll();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $authorList = $authorRepository->findAllWithPagination($page, $limit);  
        
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
   #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un auteur')]
   public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse 
   {

       $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

       // On vérifie les erreurs
       $errors = $validator->validate($author);

       if ($errors->count() > 0) {
           return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
       }

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
        Author $currentAuthor, EntityManagerInterface $em, ValidatorInterface $validatorInterface): JsonResponse {
        
        // Gestion des erreurs
        $errors = $validatorInterface->validate($currentAuthor);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        
        $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

    }
}
