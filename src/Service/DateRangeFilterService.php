<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

class DateRangeFilterService
{
    public function getFilterFromRequest(Request $request, string $default = '7d'): string
    {
        if ($request->isMethod('GET')) {
            return $request->query->get('filter', $default);
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            return $data['filter'] ?? $default;
        }

        return $default;
    }

    public function getDateRange(string $filter): array
    {
        $now = new \DateTimeImmutable('now');

        switch ($filter) {
            case '1m': 
                $start = $now->modify('-1 month');
                break;

            case 'all':
                return [];

            default:
                $start = $now->modify('-7 days');
                break;
        }

        return [
            'start' => $start,
            'end' => $now,
        ];
    }

    public function applyDateRangeFilter(QueryBuilder $qb, string $alias, string $field, string $filter): QueryBuilder
    {
        $dates = $this->getDateRange($filter);

        if (empty($dates)) {
            return $qb;
        }

        return $qb->andWhere("$alias.$field BETWEEN :start AND :end")
                  ->setParameter('start', $dates['start'])
                  ->setParameter('end', $dates['end']);
    }
}
