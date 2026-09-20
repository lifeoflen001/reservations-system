<?php
namespace App\Events;
use App\Models\Reservation;
class ReservationCancelled { public function __construct(public Reservation $reservation) {} }
