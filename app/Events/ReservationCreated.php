<?php
namespace App\Events;
use App\Models\Reservation;
class ReservationCreated { public function __construct(public Reservation $reservation) {} }
