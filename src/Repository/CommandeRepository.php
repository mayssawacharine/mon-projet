<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 *
 * @method Commande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commande[]    findAll()
 * @method Commande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function save(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouver les commandes avec filtres multiples (pour l'admin)
     */
    public function findByFilters(?string $statut = null, ?\DateTime $date = null, ?string $clientSearch = null, ?string $periode = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.client', 'client')
            ->orderBy('c.dateCreation', 'DESC');

        // Filtre par statut
        if ($statut && $statut !== 'tous') {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', $statut);
        }

        // Filtre par date spécifique
        if ($date) {
            $qb->andWhere('DATE(c.dateCreation) = :date')
                ->setParameter('date', $date->format('Y-m-d'));
        }

        // Filtre par période (aujourd'hui, cette semaine, ce mois)
        if ($periode) {
            $now = new \DateTime();
            switch ($periode) {
                case 'today':
                    $qb->andWhere('DATE(c.dateCreation) = :today')
                        ->setParameter('today', $now->format('Y-m-d'));
                    break;
                case 'week':
                    $startOfWeek = clone $now;
                    $startOfWeek->modify('Monday this week');
                    $qb->andWhere('c.dateCreation >= :weekStart')
                        ->setParameter('weekStart', $startOfWeek);
                    break;
                case 'month':
                    $startOfMonth = clone $now;
                    $startOfMonth->modify('first day of this month');
                    $qb->andWhere('c.dateCreation >= :monthStart')
                        ->setParameter('monthStart', $startOfMonth);
                    break;
            }
        }

        // Filtre par recherche client
        if ($clientSearch) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(client.nom)', 'LOWER(:search)'),
                $qb->expr()->like('LOWER(client.prenom)', 'LOWER(:search)'),
                $qb->expr()->like('LOWER(client.email)', 'LOWER(:search)')
            ))
                ->setParameter('search', '%' . $clientSearch . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Commandes d'aujourd'hui
     */
    public function findTodayOrders(): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('c')
            ->where('DATE(c.dateCreation) = :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de commandes aujourd'hui
     */
    // Dans countTodayOrders() :
    public function countTodayOrders(): int
    {
        $today = new \DateTime();
        $todayStart = (clone $today)->setTime(0, 0, 0);
        $todayEnd = (clone $today)->setTime(23, 59, 59);

        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.dateCreation BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Chiffre d'affaires d'aujourd'hui
     */
    public function getTodayRevenue(): float
    {
        $today = new \DateTime();

        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantTotal)')
            ->where('DATE(c.dateCreation) = :today')
            ->andWhere('c.statut != :annulee')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Chiffre d'affaires du mois
     */
    public function getMonthRevenue(): float
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantTotal)')
            ->where('c.dateCreation BETWEEN :start AND :end')
            ->andWhere('c.statut != :annulee')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Commandes récentes (pour dashboard)
     */
    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.client', 'client')
            ->addSelect('client')
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les commandes par statut
     */
    public function countByStatus(string $statut): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques des commandes par statut
     */
    public function getStatsByStatus(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as count')
            ->groupBy('c.statut');

        $results = $qb->getQuery()->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['statut']] = $result['count'];
        }

        return $stats;
    }

    /**
     * Commandes par client
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.client = :client')
            ->setParameter('client', $client)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dépenses totales d'un client
     */
    public function getClientTotalSpent(Client $client): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantTotal)')
            ->where('c.client = :client')
            ->andWhere('c.statut != :annulee')
            ->setParameter('client', $client)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Nombre moyen de commandes par jour
     */
    public function getAverageOrdersPerDay(int $lastDays = 30): float
    {
        $startDate = new \DateTime("-$lastDays days");

        $result = $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT DATE(c.dateCreation)) as days, COUNT(c.id) as total')
            ->where('c.dateCreation >= :start')
            ->setParameter('start', $startDate)
            ->getQuery()
            ->getSingleResult();

        if ($result['days'] > 0) {
            return $result['total'] / $result['days'];
        }

        return 0;
    }

    /**
     * Statistiques de ventes par période
     */
    public function getSalesStatsByPeriod(\DateTime $start, \DateTime $end, string $groupBy = 'day'): array
    {
        $dateFormat = $groupBy === 'month' ? 'DATE_FORMAT(c.dateCreation, \'%Y-%m\')' : 'DATE(c.dateCreation)';

        return $this->createQueryBuilder('c')
            ->select("$dateFormat as period, COUNT(c.id) as orders, SUM(c.montantTotal) as revenue")
            ->where('c.dateCreation BETWEEN :start AND :end')
            ->andWhere('c.statut != :annulee')
            ->groupBy('period')
            ->orderBy('period', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes en attente de traitement
     */
    public function findPendingOrders(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut IN (:pendingStatuses)')
            ->setParameter('pendingStatuses', ['en_attente', 'en_cours'])
            ->orderBy('c.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes à livrer aujourd'hui
     */
    public function findTodayDeliveries(): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('c')
            ->where('DATE(c.dateLivraisonPrevue) = :today')
            ->andWhere('c.statut IN (:deliveryStatuses)')
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('deliveryStatuses', ['en_cours', 'en_preparation'])
            ->orderBy('c.dateLivraisonPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Évolution des commandes (pour graphique)
     */
    public function getOrdersEvolution(int $days = 30): array
    {
        $startDate = new \DateTime("-$days days");

        $results = $this->createQueryBuilder('c')
            ->select('DATE(c.dateCreation) as date, COUNT(c.id) as count')
            ->where('c.dateCreation >= :start')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('start', $startDate)
            ->getQuery()
            ->getResult();

        // Format pour Chart.js
        $dates = [];
        $counts = [];

        // Remplir toutes les dates même sans commandes
        for ($i = $days; $i >= 0; $i--) {
            $date = (new \DateTime("-$i days"))->format('Y-m-d');
            $dates[] = $date;

            $found = false;
            foreach ($results as $result) {
                if ($result['date']->format('Y-m-d') === $date) {
                    $counts[] = $result['count'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $counts[] = 0;
            }
        }

        return [
            'dates' => $dates,
            'counts' => $counts,
        ];
    }

    /**
     * Commandes annulées avec raison
     */
    public function findCancelledOrdersWithReason(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :annulee')
            ->andWhere('c.raisonAnnulation IS NOT NULL')
            ->setParameter('annulee', 'annulee')
            ->orderBy('c.dateAnnulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Temps moyen de traitement des commandes
     */
    public function getAverageProcessingTime(): ?string
    {
        $result = $this->createQueryBuilder('c')
            ->select('AVG(TIMESTAMPDIFF(MINUTE, c.dateCreation, c.dateLivraison)) as avg_minutes')
            ->where('c.statut = :livree')
            ->andWhere('c.dateLivraison IS NOT NULL')
            ->setParameter('livree', 'livree')
            ->getQuery()
            ->getSingleScalarResult();

        if ($result) {
            $hours = floor($result / 60);
            $minutes = $result % 60;

            if ($hours > 0) {
                return sprintf('%dh %02dmin', $hours, $minutes);
            }
            return sprintf('%dmin', $minutes);
        }

        return null;
    }
}