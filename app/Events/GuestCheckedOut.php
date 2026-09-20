<?php
namespace App\Events;
use App\Models\Reservation;
class GuestCheckedOut { public function __construct(public Reservation $reservation) {} }
