<?php

namespace App\Repository;

use App\Entity\ProfileSkills;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileSkills>
 *
 * @method ProfileSkills|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProfileSkills|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProfileSkills[]    findAll()
 * @method ProfileSkills[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfileSkillsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileSkills::class);
    }

//    /**
//     * @return ProfileSkills[] Returns an array of ProfileSkills objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProfileSkills
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
