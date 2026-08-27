<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\AuditLog;
use App\Models\Payment;

class ReportController extends Controller
{
    private function checkBranchAccess(Request $request, ?int $branchId)
    {
        $user = $request->user();
        if ($user->isOwner()) {
            return;
        }
        if ($branchId && (string)$user->branch_id !== (string)$branchId) {
            abort(response()->json(['message' => 'Forbidden: Cannot access reports for another branch.'], 403));
        }
    }

    public function getSalesReport(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? $request->branch_id : $user->branch_id;
        $this->checkBranchAccess($request, $branchId);

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfDay()->toDateTimeString();

        $query = Order::where('payment_status', 'PAID')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalRevenue = (float)$query->sum('total_amount');
        $totalOrders = $query->count();
        $avgBasket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;

        // Calculate Historical COGS
        $orderIds = $query->pluck('id')->toArray();
        $historicalCogs = (float)OrderItem::whereIn('order_id', $orderIds)->sum('cost_amount_snapshot');
        $grossProfit = $totalRevenue - $historicalCogs;

        // Payment Method Breakdown
        $paymentsBreakdown = Payment::select('method', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('order_id', $orderIds)
            ->groupBy('method')
            ->get();

        // Order Type Breakdown
        $orderTypeBreakdown = Order::select('order_type', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total_amount) as total_revenue'))
            ->whereIn('id', $orderIds)
            ->groupBy('order_type')
            ->get();

        // Top Selling Menus
        $bestSellers = OrderItem::select('menu_name_snapshot', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_sales'))
            ->whereIn('order_id', $orderIds)
            ->groupBy('menu_name_snapshot')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_orders' => $totalOrders,
                'average_basket' => round($avgBasket, 2),
                'historical_cogs' => round($historicalCogs, 2),
                'gross_profit' => round($grossProfit, 2),
            ],
            'payment_breakdown' => $paymentsBreakdown,
            'order_type_breakdown' => $orderTypeBreakdown,
            'best_sellers' => $bestSellers,
        ]);
    }

    public function getInventoryReport(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? $request->branch_id : $user->branch_id;
        $this->checkBranchAccess($request, $branchId);

        $query = StockMovement::with(['inventory', 'user']);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(30);
        return response()->json($movements);
    }

    public function getAuditReport(Request $request)
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Only Owner can view audit logs.'], 403);
        }

        $query = AuditLog::with(['user', 'branch']);
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json($logs);
    }
}
