<?php

namespace App\Controller;

use App\Entity\Book;

use JMS\Serializer\Serializer;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
//use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use  Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'book', methods:['GET'])]
    public function getBookList(BookRepository $bookRepository, SerializerInterface $serializerInterface, Request $request, TagAwareCacheInterface $tagAwareCacheInterface): JsonResponse
    {
        
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllBooks-" . $page . "-" . $limit;
        
        $jsonBookList = $tagAwareCacheInterface->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializerInterface) {
            echo ("Mise en cache \n");
            $item->tag("booksCache");
            
            // Pour éviter de mettre en cache les autres information comme l'auteur etc...
            // return $bookRepository->findAllWithPagination($page, $limit);

            // Je stocke dans une variable mon find
            $bookList = $bookRepository->findAllWithPagination($page, $limit);

            // Je return maintenant le json avec toutes les infos
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializerInterface->serialize($bookList, 'json', $context); 
        });
        

        //$bookList = $bookRepository->findAll();
        //$jsonBookList = $serializerInterface->serialize($bookList, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    // Récupération avec GET
    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    //public function getDetailBook(int $id, SerializerInterface $serializer, BookRepository $bookRepository): JsonResponse {
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse {

        /*
        $book = $bookRepository->find($id);
        if ($book) {
            $jsonBook = $serializer->serialize($book, 'json');
            return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        */

        // ici grace au param converter j'injecte direct l'entité au lieu de l'id
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

   // Delete avec la methode DELETE
   #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
   #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $tagAwareCacheInterface): JsonResponse 
    {
        $tagAwareCacheInterface->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // Création avec POST
    #[Route('/api/books', name:"createBook", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse 
    {

        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        // On vérifie les erreurs
        $errors = $validator->validate($book);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($book);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        
        //Création de l'url
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    // Modifie avec PUT
    /*
    #[Route('/api/books/{id}', name:"updateBook", methods:['PUT'])]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository): JsonResponse 
    {
        $updatedBook = $serializer->deserialize($request->getContent(), 
                Book::class, 
                'json', 
                [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updatedBook->setAuthor($authorRepository->find($idAuthor));
        
        $em->persist($updatedBook);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
   }
   */

  #[Route('/api/books/{id}', name:"updateBook", methods:['PUT'])]
  #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un livre')]
  public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse 
  {
      $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
      $currentBook->setTitle($newBook->getTitle());
      $currentBook->setCoverText($newBook->getCoverText());

      // On vérifie les erreurs
      $errors = $validator->validate($currentBook);
      if ($errors->count() > 0) {
          return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
      }

      $content = $request->toArray();
      $idAuthor = $content['idAuthor'] ?? -1;
  
      $currentBook->setAuthor($authorRepository->find($idAuthor));

      $em->persist($currentBook);
      $em->flush();

      // On vide le cache.
      $cache->invalidateTags(["booksCache"]);

      return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
  }
}
