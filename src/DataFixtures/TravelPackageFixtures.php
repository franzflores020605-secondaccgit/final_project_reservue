<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\TravelPackage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TravelPackageFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['travel_packages'];
    }

    public function load(ObjectManager $manager): void
    {
        if ($manager->getRepository(TravelPackage::class)->count([]) > 0) {
            return;
        }

        $category = $manager->getRepository(Category::class)->findOneBy(['name' => 'Tours / Activities'])
            ?? $manager->getRepository(Category::class)->findOneBy([])
            ?? null;

        if (!$category instanceof Category) {
            return;
        }

        $packages = [
            [
                'name' => 'Bali Getaway',
                'shortDescription' => 'Temples, rice terraces, and sunset beaches in one unforgettable escape.',
                'image' => 'advanture-2/images/destination-1.jpg',
                'duration' => '5 Days, 4 Nights',
                'price' => 1299.00,
            ],
            [
                'name' => 'Kyoto Cultural Immersion',
                'shortDescription' => 'Tea houses, historic districts, and guided heritage walks.',
                'image' => 'advanture-2/images/destination-2.jpg',
                'duration' => '4 Days, 3 Nights',
                'price' => 1899.50,
            ],
            [
                'name' => 'Swiss Alps Adventure',
                'shortDescription' => 'Hiking trails, alpine views, and crisp mountain air.',
                'image' => 'advanture-2/images/destination-3.jpg',
                'duration' => '6 Days, 5 Nights',
                'price' => 2799.00,
            ],
            [
                'name' => 'Santorini Sunset Retreat',
                'shortDescription' => 'Caldera views, slow mornings, and Aegean relaxation.',
                'image' => 'advanture-2/images/destination-4.jpg',
                'duration' => '3 Days, 2 Nights',
                'price' => 1599.00,
            ],
            [
                'name' => 'Barcelona City Explorer',
                'shortDescription' => 'Gaudí, Gothic Quarter tapas, and Mediterranean city life.',
                'image' => 'advanture-2/images/hotel-1.jpg',
                'duration' => '4 Days, 3 Nights',
                'price' => 1199.00,
            ],
            [
                'name' => 'Costa Rica Eco Trek',
                'shortDescription' => 'Rainforest canopy walks, wildlife spotting, and eco-lodges.',
                'image' => 'advanture-2/images/image_2.jpg',
                'duration' => '7 Days, 6 Nights',
                'price' => 2199.00,
            ],
            [
                'name' => 'Maldives Overwater Escape',
                'shortDescription' => 'Private lagoon, snorkeling, and barefoot luxury.',
                'image' => 'advanture-2/images/hotel-3.jpg',
                'duration' => '5 Days, 4 Nights',
                'price' => 3499.00,
            ],
            [
                'name' => 'Rome & Vatican Highlights',
                'shortDescription' => 'Skip-the-line classics, local trattorias, and ancient history.',
                'image' => 'advanture-2/images/restaurant-1.jpg',
                'duration' => '3 Days, 2 Nights',
                'price' => 899.00,
            ],
        ];

        foreach ($packages as $row) {
            $p = new TravelPackage();
            $p->setName($row['name']);
            $p->setShortDescription($row['shortDescription']);
            $p->setImagePath($row['image']);
            $p->setDurationLabel($row['duration']);
            $p->setPricePerPerson($row['price']);
            $p->setCategory($category);
            $p->setIsPublished(true);
            $manager->persist($p);
        }

        $manager->flush();
    }
}
