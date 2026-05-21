<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin= new User();
        $admin->setUsername('admin');
        $admin->setEmail('admin@example.com');
        $admin->setStatus('active');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'adminpass12345'
        );
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);


        // $staff= new User();
        // $staff->setUsername('staff');
        // $staff->setEmail('staff@example.com');
        // $staff->setStatus('active');
        // $staff->setRoles(['ROLE_STAFF']);
        // $hashedPassword = $this->passwordHasher->hashPassword(
        //     $staff,
        //     'staff1226'
        // );
        // $staff->setPassword($hashedPassword);
        // $manager->persist($staff);



        // $user= new User();
        // $user->setUsername('user');
        // $user->setRoles(['ROLE_USER']);
        // $hashedPassword = $this->passwordHasher->hashPassword(
        //     $user,
        //     'user123'
        // );
        // $user->setPassword($hashedPassword);
        // $manager->persist($user);


    

        $manager->flush();
    }
}
