<?php

namespace App\Services\Ecl;

use RuntimeException;

/**
 * An ECL run refused whole, before any figure is written, with the reason
 * (a missing input, scenario set or engine table). Nothing is estimated in
 * its place: the screen shows the reason and the console fails with it.
 */
class EclRunRefused extends RuntimeException
{
}
