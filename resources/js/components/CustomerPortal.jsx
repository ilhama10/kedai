import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { ShoppingBag, Search, ChevronRight, CheckCircle, Clock, AlertTriangle, ArrowLeft, Upload, QrCode } from 'lucide-react';

export default function CustomerPortal() {
    const [branchId, setBranchId] = useState(1);
    const [tableInfo, setTableInfo] = useState(null);
    const [orderType, setOrderType] = useState('DINE_IN');
    const [menuData, setMenuData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeCategory, setActiveCategory] = useState('ALL');
    const [searchQuery, setSearchQuery] = useState('');
    const [branchTables, setBranchTables] = useState([]);
    const [qrLocked, setQrLocked] = useState(false); // true when table comes from QR scan

    // Modal & Selection State
    const [selectedMenu, setSelectedMenu] = useState(null);
    const [selectedVariants, setSelectedVariants] = useState([]);
    const [selectedAddons, setSelectedAddons] = useState([]);
    const [itemNotes, setItemNotes] = useState('');
    const [quantity, setQuantity] = useState(1);

    // Cart & Checkout State
    const [cart, setCart] = useState([]);
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [customerName, setCustomerName] = useState('');
    const [promoCodeInput, setPromoCodeInput] = useState('');
    const [appliedPromos, setAppliedPromos] = useState([]);
    
    // Order Created & Payment State
    const [createdOrder, setCreatedOrder] = useState(null);
    const [accessToken, setAccessToken] = useState(localStorage.getItem('kedai_customer_token') || null);
    const [orderStatus, setOrderStatus] = useState(null);
    
    // Payment Submission
    const [paymentMethod, setPaymentMethod] = useState('QRIS');
    const [verificationMethod, setVerificationMethod] = useState('upload_proof');
    const [proofFile, setProofFile] = useState(null);
    const [submittingPayment, setSubmittingPayment] = useState(false);
    const [paymentSuccessMsg, setPaymentSuccessMsg] = useState('');

    useEffect(() => {
        // Parse URL params for branch, table token or takeaway token
        const params = new URLSearchParams(window.location.search);
        const b = params.get('branch');
        const tableToken = params.get('token');
        const mode = params.get('mode');

        const effectiveBranch = b || 1;
        setBranchId(effectiveBranch);

        if (mode === 'takeaway') {
            setOrderType('TAKE_AWAY');
        }

        if (tableToken && !mode) {
            axios.get(`/api/public/tables/${tableToken}`)
                .then(res => {
                    setTableInfo(res.data.table);
                    setBranchId(res.data.branch.id);
                    setOrderType('DINE_IN');
                    setQrLocked(true);
                    fetchBranchMenu(res.data.branch.id);
                    fetchBranchTables(res.data.branch.id);
                })
                .catch(() => {});
        } else {
            fetchBranchMenu(effectiveBranch);
            fetchBranchTables(effectiveBranch);
        }
    }, []);

    const fetchBranchTables = (bId) => {
        axios.get(`/api/public/branches/${bId}/tables`)
            .then(res => {
                setBranchTables(res.data || []);
            })
            .catch(() => {
                setBranchTables([]);
            });
    };

    const handleOrderTypeChange = (type) => {
        if (qrLocked) return; // Cannot change when locked by QR scan
        setOrderType(type);
        if (type === 'TAKE_AWAY') {
            setTableInfo(null);
        }
    };

    const handleTableSelect = (tableId) => {
        if (qrLocked) return;
        const selected = branchTables.find(t => t.id === parseInt(tableId));
        setTableInfo(selected || null);
    };

    useEffect(() => {
        if (accessToken) {
            fetchOrderStatus(accessToken);
            const interval = setInterval(() => fetchOrderStatus(accessToken), 5000);
            return () => clearInterval(interval);
        }
    }, [accessToken]);

    const fetchBranchMenu = (bId) => {
        setLoading(true);
        axios.get(`/api/public/branches/${bId}/menu`)
            .then(res => {
                setMenuData(res.data);
                setLoading(false);
            })
            .catch(err => {
                console.error(err);
                setLoading(false);
            });
    };

    const fetchOrderStatus = (token) => {
        axios.get(`/api/public/orders/${token}/status`)
            .then(res => {
                setOrderStatus(res.data.order);
            })
            .catch(() => {});
    };

    const openMenuModal = (item) => {
        if (item.availability === 'sold_out') return;
        setSelectedMenu(item);
        setSelectedVariants([]);
        setSelectedAddons([]);
        setItemNotes('');
        setQuantity(1);
    };

    const toggleVariant = (vId) => {
        if (selectedVariants.includes(vId)) {
            setSelectedVariants(selectedVariants.filter(id => id !== vId));
        } else {
            setSelectedVariants([...selectedVariants, vId]);
        }
    };

    const toggleAddon = (aId) => {
        if (selectedAddons.includes(aId)) {
            setSelectedAddons(selectedAddons.filter(id => id !== aId));
        } else {
            setSelectedAddons([...selectedAddons, aId]);
        }
    };

    const calculateItemPrice = () => {
        if (!selectedMenu) return 0;
        let total = selectedMenu.price;

        selectedVariants.forEach(vId => {
            const v = selectedMenu.variants.find(x => x.id === vId);
            if (v) total += v.price;
        });

        selectedAddons.forEach(aId => {
            const a = selectedMenu.addons.find(x => x.id === aId);
            if (a) total += a.price;
        });

        return total * quantity;
    };

    const addToCart = () => {
        const cartItem = {
            id: Date.now(),
            menu_id: selectedMenu.menu_id,
            name: selectedMenu.name,
            unit_price: selectedMenu.price,
            quantity: quantity,
            variants: selectedMenu.variants.filter(v => selectedVariants.includes(v.id)),
            addons: selectedMenu.addons.filter(a => selectedAddons.includes(a.id)),
            notes: itemNotes,
            image_url: selectedMenu.image_url,
            total_price: calculateItemPrice(),
        };

        setCart([...cart, cartItem]);
        setSelectedMenu(null);
    };

    const removeFromCart = (id) => {
        setCart(cart.filter(item => item.id !== id));
    };

    const calculateCartSubtotal = () => {
        return cart.reduce((sum, item) => sum + item.total_price, 0);
    };

    const handleCheckout = () => {
        if (!customerName.trim()) {
            alert('Silakan masukkan nama Anda sebagai pemesan.');
            return;
        }

        if (cart.length === 0) {
            alert('Keranjang belanja Anda masih kosong.');
            return;
        }

        if (orderType === 'DINE_IN' && !tableInfo) {
            alert('Silakan pilih nomor meja untuk pesanan Dine In.');
            return;
        }

        const payload = {
            branch_id: branchId,
            order_type: orderType,
            table_id: tableInfo ? tableInfo.id : null,
            customer_name: customerName,
            items: cart.map(item => ({
                menu_id: item.menu_id,
                quantity: item.quantity,
                variant_ids: item.variants.map(v => v.id),
                addon_ids: item.addons.map(a => a.id),
                notes: item.notes,
            })),
            promo_codes: appliedPromos,
        };

        axios.post('/api/public/orders', payload)
            .then(res => {
                const token = res.data.access_token;
                localStorage.setItem('kedai_customer_token', token);
                setAccessToken(token);
                setCreatedOrder(res.data.order);
                setCart([]);
                setIsCartOpen(false);
                fetchOrderStatus(token);
            })
            .catch(err => {
                alert(err.response?.data?.message || 'Gagal membuat pesanan. Silakan coba lagi.');
            });
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        if (!accessToken) return;

        setSubmittingPayment(true);
        const formData = new FormData();
        formData.append('method', paymentMethod);
        if (paymentMethod === 'QRIS') {
            formData.append('verification_method', verificationMethod);
            if (verificationMethod === 'upload_proof' && proofFile) {
                formData.append('proof_image', proofFile);
            }
        }

        axios.post(`/api/public/orders/${accessToken}/payment`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
            .then(res => {
                setSubmittingPayment(false);
                setPaymentSuccessMsg('Bukti pembayaran berhasil dikirim!');
                fetchOrderStatus(accessToken);
            })
            .catch(err => {
                setSubmittingPayment(false);
                alert(err.response?.data?.message || 'Gagal mengirim pembayaran.');
            });
    };

    const handleDismissStatus = () => {
        localStorage.removeItem('kedai_customer_token');
        setAccessToken(null);
        setOrderStatus(null);
    };

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-brand-50">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-600 mx-auto mb-4"></div>
                    <p className="text-brand-900 font-medium">Memuat Menu Kedai Lesehan...</p>
                </div>
            </div>
        );
    }

    const filteredMenus = (menuData?.menus || []).filter(item => {
        const matchesCategory = activeCategory === 'ALL' || item.category_id === activeCategory;
        const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    return (
        <div className="min-h-screen bg-gray-50 pb-24 w-full max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 sm:py-6 overflow-x-hidden">
            {/* Header */}
            <header className="bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 text-white p-4 sm:p-5 rounded-3xl shadow-lg w-full max-w-full overflow-hidden">
                <div className="flex justify-between items-start sm:items-center gap-2 mb-3">
                    <div className="min-w-0">
                        <span className="text-[10px] sm:text-xs font-semibold uppercase tracking-wider bg-white/20 px-2.5 py-0.5 rounded-full text-brand-100 inline-block">
                            Kedai Lesehan Multi-Cabang
                        </span>
                        <h1 className="text-lg sm:text-2xl font-black mt-0.5 leading-tight truncate">{menuData?.branch?.name || 'Cabang Kedai'}</h1>
                    </div>
                    {tableInfo ? (
                        <div className="bg-white text-brand-700 px-3 sm:px-4 py-1.5 sm:py-2 rounded-2xl font-black text-xs sm:text-base shadow flex items-center gap-1 flex-shrink-0">
                            🍽️ MEJA {tableInfo.table_number}
                        </div>
                    ) : orderType === 'TAKE_AWAY' ? (
                        <div className="bg-amber-400 text-brand-900 px-3 py-1 rounded-2xl font-extrabold text-[11px] sm:text-xs shadow flex-shrink-0">
                            📦 TAKE AWAY
                        </div>
                    ) : (
                        <div className="bg-blue-400 text-white px-3 py-1 rounded-2xl font-extrabold text-[11px] sm:text-xs shadow flex-shrink-0">
                            🍽️ DINE IN
                        </div>
                    )}
                </div>

                {/* QR Auto-Detected Banner */}
                {qrLocked && tableInfo && (
                    <div className="bg-emerald-500/30 backdrop-blur-md border border-emerald-300/40 px-3 py-2 rounded-2xl text-[11px] sm:text-xs text-white font-semibold flex items-center justify-between mb-3 shadow-inner">
                        <div className="flex items-center gap-2 min-w-0">
                            <QrCode className="h-4 w-4 text-emerald-200 flex-shrink-0" />
                            <span className="truncate">Meja <strong>#{tableInfo.table_number}</strong> Terdeteksi Otomatis (QR)</span>
                        </div>
                        <span className="bg-emerald-400/30 text-[9px] uppercase font-bold px-2 py-0.5 rounded-full flex-shrink-0">Locked</span>
                    </div>
                )}

                {/* Order Type Selector & Table Selector */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 mb-3">
                    <div className="flex gap-2">
                        <button
                            onClick={() => handleOrderTypeChange('DINE_IN')}
                            disabled={qrLocked}
                            className={`flex-1 py-2 sm:py-2.5 px-2 rounded-xl text-[11px] sm:text-xs font-bold transition-all border whitespace-nowrap ${
                                orderType === 'DINE_IN'
                                    ? 'bg-white text-brand-700 border-white shadow-md'
                                    : 'bg-white/15 text-white/80 border-white/20 hover:bg-white/25'
                            } ${qrLocked ? 'opacity-60 cursor-not-allowed' : ''}`}
                        >
                            🍽️ Dine In<span className="hidden sm:inline"> (Makan di Tempat)</span>
                        </button>
                        <button
                            onClick={() => handleOrderTypeChange('TAKE_AWAY')}
                            disabled={qrLocked}
                            className={`flex-1 py-2 sm:py-2.5 px-2 rounded-xl text-[11px] sm:text-xs font-bold transition-all border whitespace-nowrap ${
                                orderType === 'TAKE_AWAY'
                                    ? 'bg-white text-brand-700 border-white shadow-md'
                                    : 'bg-white/15 text-white/80 border-white/20 hover:bg-white/25'
                            } ${qrLocked ? 'opacity-60 cursor-not-allowed' : ''}`}
                        >
                            📦 Take Away<span className="hidden sm:inline"> (Bawa Pulang)</span>
                        </button>
                    </div>

                    {/* Table Selector */}
                    {orderType === 'DINE_IN' && !qrLocked && (
                        <div>
                            <select
                                value={tableInfo?.id || ''}
                                onChange={e => handleTableSelect(e.target.value)}
                                className="w-full px-3.5 py-2.5 bg-white/15 text-white rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-white/40 border border-white/20 appearance-none"
                            >
                                <option value="" className="text-gray-900">-- Pilih Nomor Meja --</option>
                                {branchTables.map(t => (
                                    <option key={t.id} value={t.id} className="text-gray-900">
                                        Meja #{t.table_number} ({t.status})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* Search Bar */}
                <div className="relative mt-2">
                    <Search className="absolute left-3.5 top-3 text-brand-300 h-4 w-4" />
                    <input
                        type="text"
                        placeholder="Cari makanan & minuman favorit Anda..."
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-2.5 bg-white/15 text-white placeholder-brand-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-white/40"
                    />
                </div>
            </header>

            {/* Active Order Tracker Banner with DISMISS / CLOSE button */}
            {orderStatus && (
                <div className="my-4 p-4 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white rounded-2xl shadow-lg flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="bg-white/20 p-2.5 rounded-2xl">
                            <Clock className="h-6 w-6 text-white animate-pulse" />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-emerald-200">Status Pesanan #{orderStatus.order_number}</p>
                            <p className="font-extrabold text-base mt-0.5">{orderStatus.customer_status}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleDismissStatus}
                            className="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3 py-1.5 rounded-xl border border-white/30 transition-all flex items-center gap-1"
                            title="Tutup tracker & siap buat pesanan baru"
                        >
                            ✕ Tutup Tracker
                        </button>
                    </div>
                </div>
            )}

            {/* Categories Horizontal Filter */}
            <div className="flex overflow-x-auto gap-2 py-4 no-scrollbar">
                <button
                    onClick={() => setActiveCategory('ALL')}
                    className={`px-5 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition-all ${
                        activeCategory === 'ALL'
                            ? 'bg-brand-600 text-white shadow-md'
                            : 'bg-white text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50'
                    }`}
                >
                    Semua Menu
                </button>
                {menuData?.categories?.map(cat => (
                    <button
                        key={cat.id}
                        onClick={() => setActiveCategory(cat.id)}
                        className={`px-5 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition-all ${
                            activeCategory === cat.id
                                ? 'bg-brand-600 text-white shadow-md'
                                : 'bg-white text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        {cat.name}
                    </button>
                ))}
            </div>

            {/* Menu List Responsive Grid (1 col mobile, 2 col tablet, 3-4 col desktop) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {filteredMenus.map(item => (
                    <div
                        key={item.id}
                        onClick={() => openMenuModal(item)}
                        className={`bg-white p-3.5 rounded-2xl shadow-sm border border-gray-100 flex gap-3.5 items-center transition-all ${
                            item.availability === 'sold_out' ? 'opacity-60 grayscale cursor-not-allowed' : 'cursor-pointer hover:shadow-md'
                        }`}
                    >
                        <div className="w-20 h-20 rounded-xl bg-gray-100 overflow-hidden flex-shrink-0 relative">
                            {item.image_url ? (
                                <img src={item.image_url} alt={item.name} className="w-full h-full object-cover" />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-gray-400 text-xs font-medium">No Image</div>
                            )}
                            {item.availability === 'sold_out' && (
                                <div className="absolute inset-0 bg-black/60 flex items-center justify-center">
                                    <span className="text-white text-[10px] font-bold px-2 py-0.5 rounded bg-red-600">HABIS</span>
                                </div>
                            )}
                        </div>

                        <div className="flex-1 min-w-0">
                            {item.is_featured && (
                                <span className="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md inline-block mb-1">
                                    🔥 Rekomendasi
                                </span>
                            )}
                            <h3 className="font-bold text-gray-900 text-sm truncate">{item.name}</h3>
                            <p className="text-xs text-gray-500 line-clamp-2 mt-0.5">{item.description}</p>
                            <div className="flex justify-between items-center mt-2">
                                <span className="text-brand-600 font-extrabold text-sm">
                                    Rp {item.price.toLocaleString('id-ID')}
                                </span>
                                {item.availability !== 'sold_out' && (
                                    <button className="bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white px-3 py-1 rounded-xl text-xs font-bold transition-all">
                                        + Tambah
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Menu Selection Modal */}
            {selectedMenu && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
                    <div className="bg-white w-full max-w-md rounded-t-3xl sm:rounded-3xl max-h-[90vh] overflow-y-auto p-5 animate-in slide-in-from-bottom duration-200">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="font-bold text-lg text-gray-900">{selectedMenu.name}</h2>
                            <button onClick={() => setSelectedMenu(null)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <p className="text-xs text-gray-500 mb-4">{selectedMenu.description}</p>

                        {/* Variants */}
                        {selectedMenu.variants.length > 0 && (
                            <div className="mb-4">
                                <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Pilih Varian</h4>
                                <div className="space-y-2">
                                    {selectedMenu.variants.map(v => (
                                        <label key={v.id} className="flex items-center justify-between p-2.5 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer">
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedVariants.includes(v.id)}
                                                    onChange={() => toggleVariant(v.id)}
                                                    className="rounded text-brand-600 focus:ring-brand-500"
                                                />
                                                <span className="text-xs font-medium text-gray-800">{v.name}</span>
                                            </div>
                                            <span className="text-xs font-bold text-gray-600">
                                                {v.price > 0 ? `+Rp ${v.price.toLocaleString('id-ID')}` : 'Gratis'}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Addons */}
                        {selectedMenu.addons.length > 0 && (
                            <div className="mb-4">
                                <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Pilih Add-on (Ekstra)</h4>
                                <div className="space-y-2">
                                    {selectedMenu.addons.map(a => (
                                        <label key={a.id} className="flex items-center justify-between p-2.5 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer">
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedAddons.includes(a.id)}
                                                    onChange={() => toggleAddon(a.id)}
                                                    className="rounded text-brand-600 focus:ring-brand-500"
                                                />
                                                <span className="text-xs font-medium text-gray-800">{a.name}</span>
                                            </div>
                                            <span className="text-xs font-bold text-gray-600">+Rp {a.price.toLocaleString('id-ID')}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Notes */}
                        <div className="mb-4">
                            <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Catatan Khusus</h4>
                            <input
                                type="text"
                                placeholder="Contoh: Sambal dipisah, tidak pakai kubis..."
                                value={itemNotes}
                                onChange={e => setItemNotes(e.target.value)}
                                className="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500"
                            />
                        </div>

                        {/* Quantity & Add to Cart */}
                        <div className="flex items-center justify-between gap-4 pt-3 border-t border-gray-100">
                            <div className="flex items-center gap-3 bg-gray-100 rounded-xl px-3 py-1.5">
                                <button onClick={() => setQuantity(Math.max(1, quantity - 1))} className="font-bold text-gray-600">-</button>
                                <span className="font-bold text-sm">{quantity}</span>
                                <button onClick={() => setQuantity(quantity + 1)} className="font-bold text-gray-600">+</button>
                            </div>
                            <button
                                onClick={addToCart}
                                className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-2xl text-xs flex justify-between px-4 items-center shadow-lg shadow-brand-600/30"
                            >
                                <span>Tambah ke Pesanan</span>
                                <span>Rp {calculateItemPrice().toLocaleString('id-ID')}</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Floating Cart Button */}
            {cart.length > 0 && !isCartOpen && (
                <div className="fixed bottom-4 left-3 right-3 sm:left-1/2 sm:-translate-x-1/2 sm:w-full sm:max-w-md z-40">
                    <button
                        onClick={() => setIsCartOpen(true)}
                        className="w-full bg-gradient-to-r from-brand-600 to-brand-700 text-white p-3.5 sm:p-4 rounded-2xl shadow-2xl flex items-center justify-between animate-bounce"
                    >
                        <div className="flex items-center gap-2.5">
                            <div className="bg-white/20 px-2.5 py-1 rounded-xl text-xs font-bold">
                                {cart.reduce((sum, i) => sum + i.quantity, 0)} item
                            </div>
                            <span className="font-bold text-xs sm:text-sm">Lihat Keranjang</span>
                        </div>
                        <span className="font-extrabold text-xs sm:text-sm">
                            Rp {calculateCartSubtotal().toLocaleString('id-ID')}
                        </span>
                    </button>
                </div>
            )}

            {/* Checkout Cart Drawer */}
            {isCartOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
                    <div className="bg-white w-full max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[92vh] flex flex-col p-5 animate-in slide-in-from-bottom duration-200">
                        <div className="flex justify-between items-center mb-3">
                            <h2 className="font-bold text-lg text-gray-900">Ringkasan Pesanan</h2>
                            <button onClick={() => setIsCartOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        {/* Customer Name Input (Mandatory) */}
                        <div className="mb-4 bg-orange-50 p-3 rounded-2xl border border-orange-100">
                            <label className="block text-xs font-bold text-brand-900 mb-1">Nama Pemesan (Wajib)</label>
                            <input
                                type="text"
                                placeholder="Masukkan nama Anda..."
                                value={customerName}
                                onChange={e => setCustomerName(e.target.value)}
                                className="w-full px-3 py-2 bg-white text-xs border border-orange-200 rounded-xl font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none"
                            />
                        </div>

                        {/* Order Items List */}
                        <div className="flex-1 overflow-y-auto space-y-3 mb-4 pr-1">
                            {cart.map(item => (
                                <div key={item.id} className="flex justify-between items-start border-b border-gray-100 pb-3">
                                    <div>
                                        <h4 className="font-bold text-sm text-gray-900">{item.name}</h4>
                                        {item.variants.length > 0 && (
                                            <p className="text-[11px] text-gray-500">Varian: {item.variants.map(v => v.name).join(', ')}</p>
                                        )}
                                        {item.addons.length > 0 && (
                                            <p className="text-[11px] text-gray-500">Addon: {item.addons.map(a => a.name).join(', ')}</p>
                                        )}
                                        {item.notes && <p className="text-[11px] text-amber-600 italic">Catatan: {item.notes}</p>}
                                        <p className="text-xs font-bold text-brand-600 mt-1">
                                            {item.quantity} x Rp {item.unit_price.toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-sm text-gray-900">Rp {item.total_price.toLocaleString('id-ID')}</p>
                                        <button onClick={() => removeFromCart(item.id)} className="text-xs text-red-500 font-semibold mt-1">Hapus</button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Kode Promo / Voucher Section */}
                        <div className="mb-4 bg-purple-50 p-3 rounded-2xl border border-purple-100">
                            <label className="block text-xs font-bold text-purple-900 mb-1 flex justify-between items-center">
                                <span>Punya Kode Promo / Voucher?</span>
                                {appliedPromos.length > 0 && (
                                    <span className="text-[10px] bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full font-bold">
                                        {appliedPromos.length} Promo Terpasang
                                    </span>
                                )}
                            </label>
                            <div className="flex gap-2 mb-2">
                                <input
                                    type="text"
                                    placeholder="Masukkan kode promo (e.g. HEMAT10)"
                                    value={promoCodeInput}
                                    onChange={e => setPromoCodeInput(e.target.value.toUpperCase())}
                                    className="flex-1 px-3 py-2 bg-white text-xs border border-purple-200 rounded-xl font-bold uppercase focus:ring-2 focus:ring-purple-500 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (!promoCodeInput.trim()) return;
                                        const code = promoCodeInput.trim().toUpperCase();
                                        if (!appliedPromos.includes(code)) {
                                            setAppliedPromos([...appliedPromos, code]);
                                            setPromoCodeInput('');
                                        }
                                    }}
                                    className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition-all shadow"
                                >
                                    Gunakan
                                </button>
                            </div>

                            {/* Applied Promos Badges */}
                            {appliedPromos.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 mt-2">
                                    {appliedPromos.map(code => (
                                        <span key={code} className="inline-flex items-center gap-1 bg-purple-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg">
                                            🏷️ {code}
                                            <button
                                                type="button"
                                                onClick={() => setAppliedPromos(appliedPromos.filter(c => c !== code))}
                                                className="hover:text-purple-200 font-black ml-1"
                                            >
                                                ✕
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* Available Branch Promos Quick Select */}
                            {menuData?.promos && menuData.promos.length > 0 && (
                                <div className="mt-2 pt-2 border-t border-purple-100">
                                    <p className="text-[10px] text-purple-700 font-bold mb-1">Promo Cabang Tersedia (Klik untuk pakai):</p>
                                    <div className="flex flex-wrap gap-1">
                                        {menuData.promos.map(p => {
                                            const isSelected = appliedPromos.includes(p.code);
                                            return (
                                                <button
                                                    key={p.id}
                                                    type="button"
                                                    onClick={() => {
                                                        if (isSelected) {
                                                            setAppliedPromos(appliedPromos.filter(c => c !== p.code));
                                                        } else {
                                                            setAppliedPromos([...appliedPromos, p.code]);
                                                        }
                                                    }}
                                                    className={`text-[10px] font-bold px-2 py-0.5 rounded-md border transition-all ${
                                                        isSelected ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-purple-700 border-purple-200 hover:bg-purple-100'
                                                    }`}
                                                >
                                                    {p.code} ({p.discount_type === 'percentage' ? `${p.discount_value}% OFF` : `Potongan Rp ${p.discount_value?.toLocaleString('id-ID')}`})
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Checkout Action */}
                        <div className="border-t border-gray-100 pt-3">
                            <div className="space-y-1 text-xs font-semibold mb-3">
                                <div className="flex justify-between text-gray-600">
                                    <span>Subtotal Pesanan:</span>
                                    <span>Rp {calculateCartSubtotal().toLocaleString('id-ID')}</span>
                                </div>
                                {appliedPromos.length > 0 && (
                                    <div className="flex justify-between text-purple-600 font-bold">
                                        <span>Diskon Promo ({appliedPromos.join(', ')}):</span>
                                        <span>- Diproses di Server</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-gray-700">
                                    <span>Pajak (10% PB1):</span>
                                    <span>Rp {Math.round(calculateCartSubtotal() * 0.10).toLocaleString('id-ID')}</span>
                                </div>
                                <div className="flex justify-between text-sm font-bold text-gray-900 pt-1 border-t">
                                    <span>Estimasi Total:</span>
                                    <span className="text-brand-600">Rp {(calculateCartSubtotal() + Math.round(calculateCartSubtotal() * 0.10)).toLocaleString('id-ID')}</span>
                                </div>
                            </div>

                            <button
                                onClick={handleCheckout}
                                className="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-2xl text-sm shadow-xl shadow-brand-600/30"
                            >
                                Buat Pesanan Sekarang
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Created Order & Payment Modal */}
            {createdOrder && (
                <div className="fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-md rounded-3xl p-5 overflow-y-auto max-h-[90vh]">
                        <div className="text-center mb-4">
                            <div className="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-2">
                                <CheckCircle className="h-6 w-6" />
                            </div>
                            <h2 className="font-bold text-lg text-gray-900">Pesanan #{createdOrder.order_number}</h2>
                            <p className="text-xs text-gray-500">Status: <span className="font-bold text-amber-600">{orderStatus?.customer_status || 'Menunggu Pembayaran'}</span></p>
                        </div>

                        {/* Order Amount Calculation Breakdown */}
                        <div className="bg-gray-50 p-3 rounded-2xl border border-gray-200 text-xs font-medium space-y-1 mb-4">
                            <div className="flex justify-between text-gray-600">
                                <span>Subtotal:</span>
                                <span>Rp {createdOrder.subtotal?.toLocaleString('id-ID')}</span>
                            </div>
                            {createdOrder.discount_amount > 0 && (
                                <div className="flex justify-between text-purple-600 font-bold">
                                    <span>Diskon Promo:</span>
                                    <span>-Rp {createdOrder.discount_amount?.toLocaleString('id-ID')}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-gray-700 font-semibold">
                                <span>Pajak (10% PB1):</span>
                                <span>Rp {createdOrder.tax_amount?.toLocaleString('id-ID')}</span>
                            </div>
                            <div className="flex justify-between text-sm font-bold text-gray-900 pt-1 border-t">
                                <span>Total Pembayaran:</span>
                                <span className="text-brand-600">Rp {createdOrder.total_amount?.toLocaleString('id-ID')}</span>
                            </div>
                        </div>

                        {/* Payment Selection Form */}
                        <form onSubmit={handlePaymentSubmit} className="space-y-4">
                            <div className="bg-gray-50 p-3 rounded-2xl border border-gray-200">
                                <label className="block text-xs font-bold text-gray-700 mb-2">Pilih Metode Pembayaran</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('QRIS')}
                                        className={`py-2.5 px-3 rounded-xl text-xs font-bold border transition-all ${
                                            paymentMethod === 'QRIS' ? 'bg-brand-600 text-white border-brand-600 shadow' : 'bg-white text-gray-700 border-gray-200'
                                        }`}
                                    >
                                        QRIS / E-Wallet
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('CASH')}
                                        className={`py-2.5 px-3 rounded-xl text-xs font-bold border transition-all ${
                                            paymentMethod === 'CASH' ? 'bg-brand-600 text-white border-brand-600 shadow' : 'bg-white text-gray-700 border-gray-200'
                                        }`}
                                    >
                                        Tunai (Kasir)
                                    </button>
                                </div>
                            </div>

                            {paymentMethod === 'QRIS' && (
                                <div className="space-y-3 bg-brand-50/50 p-3 rounded-2xl border border-brand-100">
                                    <label className="block text-xs font-bold text-brand-900">Opsi Verifikasi QRIS</label>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setVerificationMethod('upload_proof')}
                                            className={`flex-1 py-2 px-2 rounded-xl text-[11px] font-bold border ${
                                                verificationMethod === 'upload_proof' ? 'bg-brand-600 text-white' : 'bg-white text-gray-700'
                                            }`}
                                        >
                                            Upload Bukti Bayar
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setVerificationMethod('show_to_cashier')}
                                            className={`flex-1 py-2 px-2 rounded-xl text-[11px] font-bold border ${
                                                verificationMethod === 'show_to_cashier' ? 'bg-brand-600 text-white' : 'bg-white text-gray-700'
                                            }`}
                                        >
                                            Tunjukkan ke Kasir
                                        </button>
                                    </div>

                                    {verificationMethod === 'upload_proof' && (
                                        <div className="mt-2">
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={e => setProofFile(e.target.files[0])}
                                                className="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-600 file:text-white"
                                            />
                                        </div>
                                    )}
                                </div>
                            )}

                            {paymentSuccessMsg && (
                                <div className="p-3 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl text-center">
                                    {paymentSuccessMsg}
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={submittingPayment}
                                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-2xl text-xs shadow-lg shadow-emerald-600/30"
                            >
                                {submittingPayment ? 'Mengirim...' : 'Kirim Konfirmasi Pembayaran'}
                            </button>
                        </form>

                        <button
                            onClick={() => setCreatedOrder(null)}
                            className="w-full mt-3 text-xs text-gray-500 font-bold py-2"
                        >
                            Tutup & Pantau Status Pesanan
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
