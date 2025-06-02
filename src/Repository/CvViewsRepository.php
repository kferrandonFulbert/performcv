<?php

namespace App\Repository;

use App\Entity\CvViews;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CvViews>
 *
 * @method CvViews|null find($id, $lockMode = null, $lockVersion = null)
 * @method CvViews|null findOneBy(array $criteria, array $orderBy = null)
 * @method CvViews[]    findAll()
 * @method CvViews[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CvViewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvViews::class);
    }

//    /**
//     * @return CvViews[] Returns an array of CvViews objects
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

//    public function findOneBySomeField($value): ?CvViews
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
