<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Services\ChannelSales;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Online orders and counter sales, side by side.
 */
class SalesReportController extends Controller
{
    public function __invoke(Request $request, ChannelSales $sales): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;

        $online = $sales->summary(SalesChannel::Online, $days);
        $counter = $sales->summary(SalesChannel::Counter, $days);

        return view('admin.reports.channels', [
            'days' => $days,
            'online' => $online,
            'counter' => $counter,
            'totalRevenue' => $online['revenue'] + $counter['revenue'],
            'totalOrders' => $online['orders'] + $counter['orders'],
            'daily' => $sales->dailyRevenue($days),
            'topOnline' => $sales->topProducts(SalesChannel::Online, $days),
            'topCounter' => $sales->topProducts(SalesChannel::Counter, $days),
            'deliveries' => $sales->onlineDeliveries($days),
            'payments' => $sales->counterPayments($days),
        ]);
    }
}
