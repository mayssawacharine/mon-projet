<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 *
 * @method Reservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reservation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reservation[]    findAll()
 * @method Reservation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    // ==================== NOUVELLES MÉTHODES (AJOUTEZ ÇA) ====================

    /**
     * Sauvegarder une réservation
     */
    public function save(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprimer une réservation
     */
    public function remove(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Réservations d'aujourd'hui
     */
    public function findTodayReservations(): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('r')
            ->where('DATE(r.dateReservation) = :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('r.heureReservation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de réservations aujourd'hui
     */
    public function countTodayReservations(): int
    {
        $today = new \DateTime();

        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('DATE(r.dateReservation) = :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Réservations récentes (pour dashboard)
     */
    public function findRecentReservations(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'client')
            ->addSelect('client')
            ->orderBy('r.dateReservation', 'DESC')
            ->addOrderBy('r.heureReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations par date
     */
    public function findByDate(\DateTime $date): array
    {
        return $this->createQueryBuilder('r')
            ->where('DATE(r.dateReservation) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('r.heureReservation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations avec filtres (pour page admin)
     */
    public function findByFilters(?string $date = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'client')
            ->addSelect('client')
            ->orderBy('r.dateReservation', 'DESC')
            ->addOrderBy('r.heureReservation', 'DESC');

        if ($date) {
            $dateObj = new \DateTime($date);
            $qb->andWhere('DATE(r.dateReservation) = :date')
                ->setParameter('date', $dateObj->format('Y-m-d'));
        }

        if ($statut && $statut !== 'tous') {
            $qb->andWhere('r.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Réservations en attente
     */
    public function findPendingReservations(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('r.dateReservation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter par statut
     */
    public function countByStatus(string $statut): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    // ==================== PARTIE ANCIENNE (NE TOUCHEZ PAS) ====================

//    /**
//     * @return Reservation[] Returns an array of Reservation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Reservation
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}