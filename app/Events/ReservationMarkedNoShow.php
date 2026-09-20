<?php
namespace App\Events;
use App\Models\Reservation;
class ReservationMarkedNoShow { public function __construct(public Reservation $reservation) {} }
