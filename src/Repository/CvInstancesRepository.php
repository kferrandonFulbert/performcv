<?php

namespace App\Repository;

use App\Entity\CvInstances;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CvInstances>
 *
 * @method CvInstances|null find($id, $lockMode = null, $lockVersion = null)
 * @method CvInstances|null findOneBy(array $criteria, array $orderBy = null)
 * @method CvInstances[]    findAll()
 * @method CvInstances[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CvInstancesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvInstances::class);
    }

//    /**
//     * @return CvInstances[] Returns an array of CvInstances objects
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

//    public function findOneBySomeField($value): ?CvInstances
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
