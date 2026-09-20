<?php
namespace App\Events;
use App\Models\Reservation;
class ReservationUpdated { public function __construct(public Reservation $reservation) {} }
