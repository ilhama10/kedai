import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Clock, CheckCircle2, Play, Check, Printer, Lock } from 'lucide-react';

export default function KitchenDashboard({ user }) {
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchKitchenOrders();
        const interval = setInterval(fetchKitchenOrders, 4000);
        return () => clearInterval(interval);
    }, []);

    const fetchKitchenOrders = () => {
        axios.get('/api/kitchen/orders')
            .then(res => {
                setOrders(res.data || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    };

    const handleUpdateStatus = (orderId, nextStatus) => {
        // Strict Role Permission Rule: Only Dapur role can mutate status
        if (user?.role !== 'dapur') {
            alert('Akses Ditolak: Hanya petugas Dapur yang berhak mengunggah/mengubah status dapur.');
            return;
        }

        axios.patch(`/api/kitchen/orders/${orderId}/status`, { kitchen_status: nextStatus })
            .then(() => {
                fetchKitchenOrders();
            })
            .catch(err => {
                alert(err.response?.data?.message || 'Gagal mengubah status dapur.');
            });
    };

    const handleReprintTicket = (orderId) => {
        axios.post(`/api/orders/${orderId}/reprint-kitchen`)
            .then(res => {
                alert('Tiket dapur berhasil di-reprint (dengan marker *** REPRINT ***).');
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal mereprint tiket dapur.'));
    };

    const getOrdersByStatus = (status) => orders.filter(o => o.kitchen_status === status);

    return (
        <div className="p-3 sm:p-6 bg-slate-900 min-h-screen text-white max-w-full overflow-x-hidden">
            {/* Header */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 sm:mb-6 bg-slate-800 p-4 rounded-2xl border border-slate-700">
                <div>
                    <h1 className="text-xl sm:text-2xl font-black tracking-wide text-brand-400">KITCHEN DISPLAY SYSTEM (KDS)</h1>
                    <p className="text-xs text-slate-400">{user?.branch?.name || 'Dapur Cabang Kedai'}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                    {user?.role !== 'dapur' && (
                        <span className="bg-amber-500/20 text-amber-300 text-xs font-bold px-3 py-1.5 rounded-xl border border-amber-500/30 flex items-center gap-1">
                            <Lock className="h-3.5 w-3.5" /> Read-Only View ({user?.role})
                        </span>
                    )}
                    <button
                        onClick={fetchKitchenOrders}
                        className="bg-slate-700 hover:bg-slate-600 px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2"
                    >
                        <Clock className="h-4 w-4" /> Refresh Status
                    </button>
                </div>
            </div>

            {/* Kitchen Kanban Columns (Responsive 1 col mobile, 3 col desktop) */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                {/* 1. WAITING */}
                <div className="bg-slate-800/80 p-4 rounded-2xl border border-slate-700 flex flex-col h-[82vh]">
                    <h2 className="font-bold text-sm text-amber-400 uppercase tracking-wider mb-3 flex items-center justify-between">
                        <span>Menunggu (WAITING)</span>
                        <span className="bg-amber-400/20 px-2.5 py-0.5 rounded-full text-xs">{getOrdersByStatus('WAITING').length}</span>
                    </h2>
                    <div className="flex-1 overflow-y-auto space-y-4 pr-1">
                        {getOrdersByStatus('WAITING').map(order => (
                            <KitchenCard
                                key={order.id}
                                order={order}
                                user={user}
                                nextStatus="PREPARING"
                                nextLabel="Mulai Masak"
                                icon={Play}
                                onUpdate={handleUpdateStatus}
                                onReprint={handleReprintTicket}
                            />
                        ))}
                    </div>
                </div>

                {/* 2. PREPARING */}
                <div className="bg-slate-800/80 p-4 rounded-2xl border border-slate-700 flex flex-col h-[82vh]">
                    <h2 className="font-bold text-sm text-blue-400 uppercase tracking-wider mb-3 flex items-center justify-between">
                        <span>Sedang Dimasak (PREPARING)</span>
                        <span className="bg-blue-400/20 px-2.5 py-0.5 rounded-full text-xs">{getOrdersByStatus('PREPARING').length}</span>
                    </h2>
                    <div className="flex-1 overflow-y-auto space-y-4 pr-1">
                        {getOrdersByStatus('PREPARING').map(order => (
                            <KitchenCard
                                key={order.id}
                                order={order}
                                user={user}
                                nextStatus="READY"
                                nextLabel="Tandai Siap"
                                icon={Check}
                                onUpdate={handleUpdateStatus}
                                onReprint={handleReprintTicket}
                            />
                        ))}
                    </div>
                </div>

                {/* 3. READY */}
                <div className="bg-slate-800/80 p-4 rounded-2xl border border-slate-700 flex flex-col h-[82vh]">
                    <h2 className="font-bold text-sm text-emerald-400 uppercase tracking-wider mb-3 flex items-center justify-between">
                        <span>Siap Diantar (READY)</span>
                        <span className="bg-emerald-400/20 px-2.5 py-0.5 rounded-full text-xs">{getOrdersByStatus('READY').length}</span>
                    </h2>
                    <div className="flex-1 overflow-y-auto space-y-4 pr-1">
                        {getOrdersByStatus('READY').map(order => (
                            <KitchenCard
                                key={order.id}
                                order={order}
                                user={user}
                                nextStatus="COMPLETED"
                                nextLabel="Selesai"
                                icon={CheckCircle2}
                                onUpdate={handleUpdateStatus}
                                onReprint={handleReprintTicket}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function KitchenCard({ order, user, nextStatus, nextLabel, icon: Icon, onUpdate, onReprint }) {
    return (
        <div className="bg-slate-900 p-4 rounded-2xl border border-slate-700 space-y-3 shadow-lg">
            <div className="flex justify-between items-start border-b border-slate-800 pb-2">
                <div>
                    <h3 className="font-bold text-base text-white">#{order.order_number}</h3>
                    <p className="text-xs text-slate-400 font-semibold">{order.customer_name} ({order.order_type})</p>
                </div>
                <div className="text-right">
                    <span className="bg-brand-500/20 text-brand-300 text-xs font-bold px-2.5 py-1 rounded-xl">
                        {order.table ? order.table.table_number : 'TAKE AWAY'}
                    </span>
                    <p className="text-[10px] text-slate-500 mt-1">{new Date(order.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</p>
                </div>
            </div>

            {/* Menu Items */}
            <div className="space-y-2 text-xs">
                {order.order_items?.map(item => (
                    <div key={item.id} className="bg-slate-800/60 p-2.5 rounded-xl border border-slate-700/50">
                        <div className="flex justify-between font-bold text-sm text-slate-200">
                            <span>{item.quantity}x {item.menu_name_snapshot}</span>
                        </div>
                        {item.variants?.length > 0 && (
                            <p className="text-[11px] text-amber-300 font-medium mt-0.5">Varian: {item.variants.map(v => v.name_snapshot).join(', ')}</p>
                        )}
                        {item.addons?.length > 0 && (
                            <p className="text-[11px] text-teal-300 font-medium mt-0.5">Addon: {item.addons.map(a => a.name_snapshot).join(', ')}</p>
                        )}
                        {item.notes && (
                            <p className="text-[11px] text-rose-300 italic font-semibold mt-0.5">Catatan: {item.notes}</p>
                        )}
                    </div>
                ))}
            </div>

            {order.notes && (
                <div className="bg-rose-950/40 p-2 rounded-xl border border-rose-800/40 text-rose-300 text-xs font-semibold">
                    Catatan Order: {order.notes}
                </div>
            )}

            {/* Actions */}
            <div className="flex gap-2 pt-1">
                <button
                    onClick={() => onReprint(order.id)}
                    className="p-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold"
                    title="Reprint Tiket Dapur"
                >
                    <Printer className="h-4 w-4" />
                </button>
                {user?.role === 'dapur' ? (
                    <button
                        onClick={() => onUpdate(order.id, nextStatus)}
                        className="flex-1 bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-1.5 shadow"
                    >
                        <Icon className="h-4 w-4" /> {nextLabel}
                    </button>
                ) : (
                    <div className="flex-1 bg-slate-800 text-slate-500 text-xs font-bold py-2.5 rounded-xl text-center cursor-not-allowed">
                        Read-Only (Dapur Only)
                    </div>
                )}
            </div>
        </div>
    );
}
