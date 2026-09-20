<?php
namespace App\Events;
use App\Models\Reservation;
class GuestCheckedIn { public function __construct(public Reservation $reservation) {} }
