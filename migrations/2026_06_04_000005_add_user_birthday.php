<?php

use Flarum\Database\Migration;

/*
 * Opt-in birthday for member milestones — stored as a privacy-preserving "MM-DD"
 * string only (no year is ever stored), so celebrating birthdays can never leak
 * a member's age.
 */
return Migration::addColumns('users', [
    'cal_birthday' => ['string', 'length' => 5, 'nullable' => true],
]);
