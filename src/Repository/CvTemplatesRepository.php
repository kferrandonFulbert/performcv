<?php

namespace App\Repository;

use App\Entity\CvTemplates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CvTemplates>
 *
 * @method CvTemplates|null find($id, $lockMode = null, $lockVersion = null)
 * @method CvTemplates|null findOneBy(array $criteria, array $orderBy = null)
 * @method CvTemplates[]    findAll()
 * @method CvTemplates[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CvTemplatesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvTemplates::class);
    }

//    /**
//     * @return CvTemplates[] Returns an array of CvTemplates objects
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

//    public function findOneBySomeField($value): ?CvTemplates
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
