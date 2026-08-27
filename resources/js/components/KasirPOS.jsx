import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { CheckCircle, AlertTriangle, Printer, RefreshCw, ShoppingCart, Lock, Eye, DollarSign, Clock } from 'lucide-react';
import ThermalPrintModal from './ThermalPrintModal';

export default function KasirPOS({ user }) {
    const [orders, setOrders] = useState([]);
    const [activeTab, setActiveTab] = useState('PENDING_VERIFICATION'); // PENDING_VERIFICATION, UNPAID, PAID
    const [loading, setLoading] = useState(true);
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [cashReceivedInput, setCashReceivedInput] = useState('');
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
    const [confirmingPaid, setConfirmingPaid] = useState(false);

    // Print Modal State
    const [printModalOrder, setPrintModalOrder] = useState(null);
    const [failedJobs, setFailedJobs] = useState([]);

    // Manual Takeaway Modal State
    const [isTakeawayModalOpen, setIsTakeawayModalOpen] = useState(false);
    const [takeawayName, setTakeawayName] = useState('');
    const [takeawayMethod, setTakeawayMethod] = useState('CASH');
    const [takeawayItems, setTakeawayItems] = useState([]);
    const [takeawayPromoCode, setTakeawayPromoCode] = useState('');
    const [branchMenus, setBranchMenus] = useState([]);
    const [takeawaySearch, setTakeawaySearch] = useState('');
    const [takeawayCategory, setTakeawayCategory] = useState('ALL');

    useEffect(() => {
        fetchOrders();
        fetchFailedPrintJobs();
        if (user?.branch_id) {
            fetchBranchMenus(user.branch_id);
        }
        const interval = setInterval(() => {
            fetchOrders();
            fetchFailedPrintJobs();
        }, 5000);
        return () => clearInterval(interval);
    }, [activeTab, user]);

    const fetchOrders = () => {
        setLoading(true);
        axios.get('/api/orders', { params: { payment_status: activeTab } })
            .then(res => {
                setOrders(res.data.data || []);
                setLoading(false);
            })
            .catch(err => {
                console.error(err);
                setLoading(false);
            });
    };

    const fetchFailedPrintJobs = () => {
        axios.get('/api/print-jobs', { params: { status: 'FAILED' } })
            .then(res => {
                setFailedJobs(res.data.data || []);
            })
            .catch(() => {});
    };

    const fetchBranchMenus = (bId) => {
        axios.get(`/api/branches/${bId}/menus`)
            .then(res => setBranchMenus(res.data))
            .catch(() => {});
    };

    const openConfirmModal = (order) => {
        setSelectedOrder(order);
        setCashReceivedInput(order.payment?.cash_received || order.total_amount);
        setIsConfirmModalOpen(true);
    };

    const handleConfirmPaid = () => {
        if (!selectedOrder) return;
        setConfirmingPaid(true);

        const payload = {};
        if (selectedOrder.payment?.method === 'CASH') {
            payload.cash_received = parseFloat(cashReceivedInput);
        }

        axios.post(`/api/orders/${selectedOrder.id}/confirm-paid`, payload)
            .then(res => {
                setConfirmingPaid(false);
                setIsConfirmModalOpen(false);
                setSelectedOrder(null);
                alert('Pesanan berhasil dikonfirmasi LUNAS dan terkunci.');
                fetchOrders();
                fetchFailedPrintJobs();
                // Open Receipt Print Dialog
                setPrintModalOrder(res.data.order);
            })
            .catch(err => {
                setConfirmingPaid(false);
                alert(err.response?.data?.message || 'Gagal mengonfirmasi pembayaran.');
            });
    };

    const handleRetryPrint = (jobId) => {
        axios.post(`/api/print-jobs/${jobId}/retry`)
            .then(() => {
                alert('Job pencetakan berhasil di-retry.');
                fetchFailedPrintJobs();
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal meretry pencetakan.'));
    };

    const addTakeawayItem = (bm) => {
        const existing = takeawayItems.find(i => i.menu_id === bm.menu_id);
        if (existing) {
            setTakeawayItems(takeawayItems.map(i => i.menu_id === bm.menu_id ? { ...i, quantity: i.quantity + 1 } : i));
        } else {
            setTakeawayItems([...takeawayItems, {
                menu_id: bm.menu_id,
                name: bm.menu.name,
                price: bm.price,
                quantity: 1,
            }]);
        }
    };

    const handleCreateTakeaway = (e) => {
        e.preventDefault();
        if (!takeawayName.trim() || takeawayItems.length === 0) {
            alert('Silakan isi nama customer dan pilih minimal 1 menu.');
            return;
        }

        const payload = {
            customer_name: takeawayName,
            payment_method: takeawayMethod,
            cash_received: takeawayMethod === 'CASH' ? takeawayItems.reduce((s, i) => s + (i.price * i.quantity), 0) : null,
            items: takeawayItems.map(i => ({ menu_id: i.menu_id, quantity: i.quantity })),
            promo_codes: takeawayPromoCode.trim() ? [takeawayPromoCode.trim().toUpperCase()] : [],
        };

        axios.post('/api/orders/takeaway', payload)
            .then(res => {
                setIsTakeawayModalOpen(false);
                setTakeawayName('');
                setTakeawayItems([]);
                setTakeawayPromoCode('');
                alert('Pesanan Take Away berhasil dibuat.');
                fetchOrders();
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal membuat Take Away.'));
    };

    return (
        <div className="p-3 sm:p-6 bg-gray-100 min-h-screen max-w-full overflow-x-hidden">
            {/* Top Bar */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 sm:mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-200">
                <div>
                    <h1 className="text-lg sm:text-xl font-bold text-gray-900">Dashboard Kasir POS</h1>
                    <p className="text-xs text-gray-500">{user?.branch?.name || 'Cabang Kedai Lesehan'}</p>
                </div>
                <button
                    onClick={() => setIsTakeawayModalOpen(true)}
                    className="w-full sm:w-auto bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl flex items-center justify-center gap-2 shadow"
                >
                    <ShoppingCart className="h-4 w-4" />
                    + Pesanan Take Away Manual
                </button>
            </div>

            {/* Failed Print Alert Banner */}
            {failedJobs.length > 0 && (
                <div className="mb-4 sm:mb-6 bg-red-50 border border-red-200 p-4 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-6 w-6 text-red-600 animate-pulse flex-shrink-0" />
                        <div>
                            <h4 className="font-bold text-sm text-red-900">Peringatan Gagal Cetak ({failedJobs.length} Job)</h4>
                            <p className="text-xs text-red-700">Terdapat percetakan nota/tiket dapur yang mengalami kegagalan.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 w-full sm:w-auto">
                        {failedJobs.map(job => (
                            <button
                                key={job.id}
                                onClick={() => handleRetryPrint(job.id)}
                                className="bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-xl hover:bg-red-700"
                            >
                                Retry Print #{job.id}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Tabs (Scrollable on mobile) */}
            <div className="flex gap-2.5 mb-4 sm:mb-6 overflow-x-auto pb-1 no-scrollbar">
                {[
                    { id: 'PENDING_VERIFICATION', label: 'Verifikasi Pembayaran (QRIS)' },
                    { id: 'UNPAID', label: 'Belum Lunas (Cash/QRIS)' },
                    { id: 'PAID', label: 'Pesanan Lunas (PAID)' }
                ].map(tab => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className={`px-4 py-2.5 rounded-xl font-bold text-xs whitespace-nowrap transition-all ${
                            activeTab === tab.id
                                ? 'bg-brand-600 text-white shadow-md'
                                : 'bg-white text-gray-700 border border-gray-200'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Orders Table */}
            <div className="staff-table-scroll bg-white rounded-2xl shadow-sm border border-gray-200">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
                            <th className="p-4">No. Order</th>
                            <th className="p-4">Customer</th>
                            <th className="p-4">Tipe</th>
                            <th className="p-4">Meja</th>
                            <th className="p-4">Total</th>
                            <th className="p-4">Metode Bayar</th>
                            <th className="p-4">Status Pembayaran</th>
                            <th className="p-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-xs font-medium text-gray-700">
                        {orders.map(order => (
                            <tr key={order.id} className="hover:bg-gray-50/80">
                                <td className="p-4 font-bold text-gray-900">{order.order_number}</td>
                                <td className="p-4">{order.customer_name}</td>
                                <td className="p-4">
                                    <span className={`px-2 py-0.5 rounded-md font-bold text-[10px] ${
                                        order.order_type === 'DINE_IN' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'
                                    }`}>
                                        {order.order_type}
                                    </span>
                                </td>
                                <td className="p-4">{order.table ? order.table.table_number : '-'}</td>
                                <td className="p-4 font-bold text-brand-600">Rp {order.total_amount.toLocaleString('id-ID')}</td>
                                <td className="p-4">{order.payment?.method || '-'}</td>
                                <td className="p-4">
                                    <span className={`px-2.5 py-1 rounded-full font-bold text-[10px] ${
                                        order.payment_status === 'PAID'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : order.payment_status === 'PENDING_VERIFICATION'
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-red-100 text-red-700'
                                    }`}>
                                        {order.payment_status}
                                    </span>
                                </td>
                                <td className="p-4">
                                    {order.payment_status !== 'PAID' ? (
                                        user?.role === 'kasir' ? (
                                            <button
                                                onClick={() => openConfirmModal(order)}
                                                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 shadow"
                                            >
                                                <CheckCircle className="h-3.5 w-3.5" /> Konfirmasi Bayar
                                            </button>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 font-bold px-2 py-1 rounded-lg" title="Hanya Kasir yang berhak mengonfirmasi pembayaran">
                                                <Lock className="h-3 w-3" /> Kasir Only
                                            </span>
                                        )
                                    ) : (
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => setPrintModalOrder(order)}
                                                className="bg-brand-600 hover:bg-brand-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 shadow"
                                            >
                                                <Printer className="h-3.5 w-3.5" /> Cetak Nota
                                            </button>
                                            <span className="inline-flex items-center gap-1 text-[11px] text-gray-400 font-bold bg-gray-100 px-2 py-1 rounded-lg">
                                                <Lock className="h-3 w-3" /> Locked
                                            </span>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Manual Take Away Order Creation Modal */}
            {isTakeawayModalOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-2xl rounded-3xl p-6 shadow-2xl max-h-[90vh] flex flex-col">
                        <div className="flex justify-between items-center mb-4 border-b pb-2">
                            <div>
                                <h3 className="font-bold text-base text-gray-900">Buat Pesanan Take Away Manual (Kasir)</h3>
                                <p className="text-xs text-gray-500">Pilih menu dan masukkan data pemesan</p>
                            </div>
                            <button onClick={() => setIsTakeawayModalOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleCreateTakeaway} className="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
                            <div className="grid grid-cols-2 gap-3 bg-gray-50 p-3 rounded-2xl border">
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Nama Pemesan / Customer *</label>
                                    <input
                                        type="text"
                                        value={takeawayName}
                                        onChange={e => setTakeawayName(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                        placeholder="Masukkan nama pelanggan..."
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Metode Pembayaran *</label>
                                    <select
                                        value={takeawayMethod}
                                        onChange={e => setTakeawayMethod(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-bold text-brand-600 focus:ring-2 focus:ring-brand-500"
                                    >
                                        <option value="CASH">Tunai (Cash)</option>
                                        <option value="QRIS">QRIS / E-Wallet</option>
                                    </select>
                                </div>
                            </div>

                            {/* Menu Selector Grid with Category & Search Filters */}
                            <div>
                                <div className="flex justify-between items-center mb-2">
                                    <h4 className="font-bold text-gray-800 uppercase tracking-wider text-[11px]">Pilih Menu Kedai:</h4>
                                    <span className="text-[10px] text-gray-400">
                                        Menampilkan {branchMenus.filter(bm => {
                                            const matchesCat = takeawayCategory === 'ALL' || String(bm.menu?.category_id) === String(takeawayCategory);
                                            const matchesSearch = bm.menu?.name?.toLowerCase().includes(takeawaySearch.toLowerCase());
                                            return matchesCat && matchesSearch;
                                        }).length} menu
                                    </span>
                                </div>

                                {/* Filter Controls */}
                                <div className="space-y-2 mb-2">
                                    {/* Search Bar */}
                                    <input
                                        type="text"
                                        placeholder="🔍 Cari menu (misal: Ayam, Es, Sambal)..."
                                        value={takeawaySearch}
                                        onChange={e => setTakeawaySearch(e.target.value)}
                                        className="w-full p-2 border rounded-xl text-xs font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none"
                                    />

                                    {/* Category Filter Pills */}
                                    <div className="flex overflow-x-auto gap-1.5 pb-1 no-scrollbar text-[11px]">
                                        <button
                                            type="button"
                                            onClick={() => setTakeawayCategory('ALL')}
                                            className={`px-3 py-1 rounded-xl font-bold whitespace-nowrap border transition-all ${
                                                takeawayCategory === 'ALL'
                                                    ? 'bg-brand-600 text-white border-brand-600 shadow-sm'
                                                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                                            }`}
                                        >
                                            Semua Kategori
                                        </button>
                                        {Array.from(
                                            new Map(branchMenus.map(bm => [bm.menu?.category?.id, bm.menu?.category])).values()
                                        ).filter(Boolean).map(cat => (
                                            <button
                                                key={cat.id}
                                                type="button"
                                                onClick={() => setTakeawayCategory(cat.id)}
                                                className={`px-3 py-1 rounded-xl font-bold whitespace-nowrap border transition-all ${
                                                    String(takeawayCategory) === String(cat.id)
                                                        ? 'bg-brand-600 text-white border-brand-600 shadow-sm'
                                                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                                                }`}
                                            >
                                                {cat.name}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Filtered Grid */}
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-1 border rounded-2xl bg-gray-50/50">
                                    {branchMenus
                                        .filter(bm => {
                                            const matchesCat = takeawayCategory === 'ALL' || String(bm.menu?.category_id) === String(takeawayCategory);
                                            const matchesSearch = bm.menu?.name?.toLowerCase().includes(takeawaySearch.toLowerCase());
                                            return matchesCat && matchesSearch;
                                        })
                                        .map(bm => (
                                            <button
                                                key={bm.id}
                                                type="button"
                                                onClick={() => addTakeawayItem(bm)}
                                                className={`p-2.5 rounded-xl border text-left flex flex-col justify-between transition-all ${
                                                    bm.availability === 'sold_out' ? 'opacity-50 cursor-not-allowed bg-gray-100' : 'bg-white hover:border-brand-500 hover:shadow-sm'
                                                }`}
                                                disabled={bm.availability === 'sold_out'}
                                            >
                                                <span className="font-bold text-gray-900 truncate">{bm.menu?.name}</span>
                                                <div className="flex justify-between items-center mt-1">
                                                    <span className="text-brand-600 font-extrabold text-[11px]">Rp {bm.price?.toLocaleString('id-ID')}</span>
                                                    <span className="text-[10px] bg-brand-50 text-brand-700 font-bold px-1.5 py-0.5 rounded">+ Tambah</span>
                                                </div>
                                            </button>
                                        ))}
                                </div>
                            </div>

                            {/* Selected Items Summary */}
                            <div>
                                <h4 className="font-bold text-gray-800 uppercase tracking-wider mb-2 text-[11px]">Item Terpilih ({takeawayItems.length}):</h4>
                                {takeawayItems.length === 0 ? (
                                    <div className="text-center py-4 bg-gray-50 rounded-2xl text-gray-400">
                                        Belum ada menu yang dipilih. Klik menu diatas untuk menambahkan.
                                    </div>
                                ) : (
                                    <div className="space-y-2 border rounded-2xl p-3 bg-white">
                                        {takeawayItems.map(item => (
                                            <div key={item.menu_id} className="flex items-center justify-between border-b pb-2">
                                                <div>
                                                    <p className="font-bold text-gray-900">{item.name}</p>
                                                    <p className="text-[10px] text-gray-500">Rp {item.price?.toLocaleString('id-ID')} / porsi</p>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex items-center gap-2 bg-gray-100 px-2 py-1 rounded-xl">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (item.quantity > 1) {
                                                                    setTakeawayItems(takeawayItems.map(i => i.menu_id === item.menu_id ? { ...i, quantity: i.quantity - 1 } : i));
                                                                } else {
                                                                    setTakeawayItems(takeawayItems.filter(i => i.menu_id !== item.menu_id));
                                                                }
                                                            }}
                                                            className="font-bold text-gray-700 px-1"
                                                        >-</button>
                                                        <span className="font-bold">{item.quantity}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => setTakeawayItems(takeawayItems.map(i => i.menu_id === item.menu_id ? { ...i, quantity: i.quantity + 1 } : i))}
                                                            className="font-bold text-gray-700 px-1"
                                                        >+</button>
                                                    </div>
                                                    <span className="font-bold text-brand-600 w-20 text-right">
                                                        Rp {(item.price * item.quantity).toLocaleString('id-ID')}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}

                                        {/* Subtotal, Pajak & Total Summary */}
                                        <div className="border-t pt-2 mt-2 space-y-1 text-xs font-semibold text-gray-700">
                                            <div className="flex justify-between">
                                                <span>Subtotal Pesanan:</span>
                                                <span>Rp {takeawayItems.reduce((s, i) => s + (i.price * i.quantity), 0).toLocaleString('id-ID')}</span>
                                            </div>
                                            <div className="flex justify-between text-gray-600">
                                                <span>Pajak (10% PB1):</span>
                                                <span>Rp {Math.round(takeawayItems.reduce((s, i) => s + (i.price * i.quantity), 0) * 0.10).toLocaleString('id-ID')}</span>
                                            </div>
                                            <div className="flex justify-between items-center font-extrabold text-sm text-gray-900 border-t pt-1">
                                                <span>Estimasi Total:</span>
                                                <span className="text-brand-600">
                                                    Rp {(takeawayItems.reduce((s, i) => s + (i.price * i.quantity), 0) + Math.round(takeawayItems.reduce((s, i) => s + (i.price * i.quantity), 0) * 0.10)).toLocaleString('id-ID')}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-3 bg-purple-50 p-2.5 rounded-xl border border-purple-200">
                                            <label className="block text-[11px] font-bold text-purple-900 mb-1">Kode Promo / Voucher (Opsional)</label>
                                            <input
                                                type="text"
                                                placeholder="Contoh: DISKON10"
                                                value={takeawayPromoCode}
                                                onChange={e => setTakeawayPromoCode(e.target.value.toUpperCase())}
                                                className="w-full px-2.5 py-1.5 bg-white text-xs border border-purple-200 rounded-lg font-bold uppercase focus:ring-2 focus:ring-purple-500"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-2 pt-2 border-t">
                                <button
                                    type="button"
                                    onClick={() => setIsTakeawayModalOpen(false)}
                                    className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 rounded-2xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-2xl shadow-lg shadow-brand-600/30"
                                >
                                    Buat Pesanan Take Away
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Final Cashier Confirmation Modal (RULE 5) */}
            {isConfirmModalOpen && selectedOrder && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-lg rounded-3xl p-6 shadow-2xl">
                        <h2 className="font-bold text-lg text-gray-900 mb-4 border-b pb-2">Konfirmasi Pesanan</h2>

                        <div className="space-y-3 text-xs mb-4">
                            <div className="grid grid-cols-2 gap-2 bg-gray-50 p-3 rounded-2xl">
                                <div><span className="text-gray-500">Order:</span> <strong className="text-gray-900">{selectedOrder.order_number}</strong></div>
                                <div><span className="text-gray-500">Customer:</span> <strong className="text-gray-900">{selectedOrder.customer_name}</strong></div>
                                <div><span className="text-gray-500">Tipe:</span> <strong className="text-gray-900">{selectedOrder.order_type}</strong></div>
                                <div><span className="text-gray-500">Meja:</span> <strong className="text-gray-900">{selectedOrder.table ? selectedOrder.table.table_number : 'TAKE AWAY'}</strong></div>
                            </div>

                            {/* Items List */}
                            <div className="border rounded-2xl p-3 max-h-40 overflow-y-auto space-y-2">
                                {selectedOrder.order_items?.map(i => (
                                    <div key={i.id} className="flex justify-between border-b pb-1">
                                        <div>
                                            <p className="font-bold text-gray-800">{i.quantity}x {i.menu_name_snapshot}</p>
                                            {i.variants?.length > 0 && <p className="text-[10px] text-gray-500">Var: {i.variants.map(v=>v.name_snapshot).join(',')}</p>}
                                            {i.addons?.length > 0 && <p className="text-[10px] text-gray-500">Addon: {i.addons.map(a=>a.name_snapshot).join(',')}</p>}
                                        </div>
                                        <p className="font-bold text-gray-900">Rp {i.total_price.toLocaleString('id-ID')}</p>
                                    </div>
                                ))}
                            </div>

                            {/* Amounts */}
                            <div className="space-y-1 text-right bg-brand-50 p-3 rounded-2xl font-medium">
                                <p>Subtotal: Rp {selectedOrder.subtotal?.toLocaleString('id-ID')}</p>
                                {selectedOrder.discount_amount > 0 && (
                                    <p className="text-red-600">Diskon: -Rp {selectedOrder.discount_amount?.toLocaleString('id-ID')}</p>
                                )}
                                <p className="text-gray-700 font-semibold">Pajak (10% PB1): Rp {selectedOrder.tax_amount?.toLocaleString('id-ID')}</p>
                                <p className="text-base font-bold text-brand-700">Total Akhir: Rp {selectedOrder.total_amount?.toLocaleString('id-ID')}</p>
                            </div>

                            {/* QRIS Proof Viewer if applicable */}
                            {selectedOrder.payment?.method === 'QRIS' && selectedOrder.payment?.proof_image && (
                                <div className="p-3 bg-gray-50 rounded-2xl border">
                                    <p className="font-bold mb-1">Bukti Transfer QRIS:</p>
                                    <img src={`/storage/${selectedOrder.payment.proof_image}`} alt="Bukti Transfer" className="h-32 object-contain mx-auto rounded" />
                                </div>
                            )}

                            {/* Cash Input if Cash */}
                            {selectedOrder.payment?.method === 'CASH' && (
                                <div className="p-3 bg-orange-50 rounded-2xl border border-orange-200">
                                    <label className="block font-bold text-brand-900 mb-1">Uang Diterima (Cash Received)</label>
                                    <input
                                        type="number"
                                        value={cashReceivedInput}
                                        onChange={e => setCashReceivedInput(e.target.value)}
                                        className="w-full p-2 border border-orange-300 rounded-xl text-sm font-bold focus:ring-2 focus:ring-brand-500"
                                    />
                                    <div className="mt-2 text-right font-bold text-sm text-emerald-700">
                                        Kembalian: Rp {Math.max(0, (parseFloat(cashReceivedInput) || 0) - selectedOrder.total_amount).toLocaleString('id-ID')}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Buttons */}
                        <div className="flex gap-3 pt-2">
                            <button
                                onClick={() => setIsConfirmModalOpen(false)}
                                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 rounded-2xl text-xs"
                            >
                                Kembali
                            </button>
                            <button
                                onClick={handleConfirmPaid}
                                disabled={confirmingPaid}
                                className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-2xl text-xs shadow-lg shadow-emerald-600/30"
                            >
                                {confirmingPaid ? 'Memproses...' : 'Konfirmasi & Lunas'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Thermal Print Receipt Modal */}
            {printModalOrder && (
                <ThermalPrintModal order={printModalOrder} onClose={() => setPrintModalOrder(null)} />
            )}
        </div>
    );
}
