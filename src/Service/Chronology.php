<?php

declare(strict_types=1);

namespace App\Service;

class Chronology
{
    public function error(object $entry): ?string
    {
        $certification = method_exists($entry, 'getIssueDate');
        if (!$certification && !method_exists($entry, 'getStartDate')) {
            return null;
        }
        $start = $certification ? $entry->getIssueDate() : $entry->getStartDate();
        $end = $certification ? $entry->getExpirationDate() : $entry->getEndDate();
        if ($start && $end && $end < $start) {
            return 'La date de fin doit être postérieure ou égale à la date de début.';
        }
        if (method_exists($entry, 'getIsCurrent') && $entry->getIsCurrent() === 1 && $end !== null) {
            return 'Un poste actuel ne peut pas avoir de date de fin.';
        }

        return null;
    }
}
