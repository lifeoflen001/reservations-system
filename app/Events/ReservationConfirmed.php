<?php
namespace App\Events;
use App\Models\Reservation;
class ReservationConfirmed { public function __construct(public Reservation $reservation) {} }
