<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Book;
use App\Entity\Author;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        //création de 5 auteurs
        $listAuthor = []; 
        for ($i = 0; $i < 5; $i++) {
            $author = new Author;
            $author->setFirstName($faker->firstName());
            $author->setLastName($faker->lastName());
            $listAuthor[] = $author;
            
            $manager->persist($author); 
        }
        
        // Création d'une vingtaine de livres ayant pour titre
        for ($i = 0; $i < 20; $i++) {
            $livre = new Book;
            $livre->setTitle('Livre ' . $i);
            $livre->setCoverText('Quatrième de couverture numéro : ' . $i);
            $livre->setAuthor($listAuthor[array_rand($listAuthor)]);
            
            $manager->persist($livre);
        }

        

        $manager->flush();
    }
}
