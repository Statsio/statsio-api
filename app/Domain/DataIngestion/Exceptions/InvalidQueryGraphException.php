<?php

namespace App\Domain\DataIngestion\Exceptions;

/**
 * Levée quand le graphe de sources/jointures d'un bloc Studio est incohérent :
 * jointure référençant une source inconnue, source non rattachée à la source
 * primaire par une chaîne de jointures (graphe disjoint), ou cycle.
 * Surfacée en 422 par DatasetController.
 */
class InvalidQueryGraphException extends \RuntimeException {}
