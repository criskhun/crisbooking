<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function clients(Request $request): View
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $bookings = Booking::query()
            ->with(['client:id,name,email,phone,city,nationality,profile_completed_at', 'unit:id,host_id,name,category'])
            ->where('status', 'confirmed')
            ->when(! $request->user()->is_admin, fn ($query) => $query->whereHas('unit', fn ($units) => $units->where('host_id', $request->user()->id)))
            ->latest('end_at')
            ->get();

        $clients = $bookings->groupBy('client_id')->map(function ($clientBookings) {
            $client = $clientBookings->first()->client;
            $client->setAttribute('successful_bookings_count', $clientBookings->count());
            $client->setAttribute('completed_bookings_count', $clientBookings->where('end_at', '<=', now())->count());
            $client->setAttribute('confirmed_sales_total', $clientBookings->sum(fn ($booking) => $booking->revenueAmount()));
            $client->setRelation('successfulBookings', $clientBookings->values());

            return $client;
        })->sortBy('name')->values();

        return view('workspace.clients', compact('clients'));
    }
}
