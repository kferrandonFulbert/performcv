<?php

namespace App\Repository;

use App\Entity\Certifications;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Certifications>
 *
 * @method Certifications|null find($id, $lockMode = null, $lockVersion = null)
 * @method Certifications|null findOneBy(array $criteria, array $orderBy = null)
 * @method Certifications[]    findAll()
 * @method Certifications[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CertificationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certifications::class);
    }

//    /**
//     * @return Certifications[] Returns an array of Certifications objects
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

//    public function findOneBySomeField($value): ?Certifications
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
