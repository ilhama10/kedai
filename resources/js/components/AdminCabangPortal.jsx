import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { QrCode, RefreshCw, Printer, Edit, Trash2, Plus, Tag, Layers, DollarSign, Image, Lock, AlertCircle, Check } from 'lucide-react';

export default function AdminCabangPortal({ user }) {
    const [activeTab, setActiveTab] = useState('menu'); // menu, promos, tables, inventory
    const [branchMenus, setBranchMenus] = useState([]);
    const [tables, setTables] = useState([]);
    const [inventory, setInventory] = useState([]);
    const [promos, setPromos] = useState([]);
    const [loading, setLoading] = useState(true);

    // Addons State
    const [addons, setAddons] = useState([]);
    const [newMenuSelectedAddons, setNewMenuSelectedAddons] = useState([]);
    const [editMenuSelectedAddons, setEditMenuSelectedAddons] = useState([]);

    const [isAddAddonOpen, setIsAddAddonOpen] = useState(false);
    const [newAddonName, setNewAddonName] = useState('');
    const [newAddonPrice, setNewAddonPrice] = useState('');
    const [submittingAddon, setSubmittingAddon] = useState(false);

    // Add Menu & Category Modal State
    const [categories, setCategories] = useState([]);
    const [isAddMenuOpen, setIsAddMenuOpen] = useState(false);
    const [newMenuSku, setNewMenuSku] = useState('');
    const [newMenuName, setNewMenuName] = useState('');
    const [newMenuCategory, setNewMenuCategory] = useState('');
    const [newMenuPrice, setNewMenuPrice] = useState('');
    const [newMenuDesc, setNewMenuDesc] = useState('');
    const [newMenuImageFile, setNewMenuImageFile] = useState(null);
    const [submittingMenu, setSubmittingMenu] = useState(false);

    const [isAddCategoryOpen, setIsAddCategoryOpen] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const [newCategoryDesc, setNewCategoryDesc] = useState('');
    const [submittingCategory, setSubmittingCategory] = useState(false);

    // Edit Menu Modal State
    const [editingMenu, setEditingMenu] = useState(null);
    const [editPrice, setEditPrice] = useState('');
    const [editAvailability, setEditAvailability] = useState('available');
    const [editStatus, setEditStatus] = useState('active');
    const [editImageFile, setEditImageFile] = useState(null);
    const [submittingEdit, setSubmittingEdit] = useState(false);

    // Print QR Modal State
    const [printingTable, setPrintingTable] = useState(null);
    const [selectedTableIds, setSelectedTableIds] = useState([]);
    const [printingTablesList, setPrintingTablesList] = useState([]);

    // Add Table Form State
    const [isAddTableOpen, setIsAddTableOpen] = useState(false);
    const [newTableNumber, setNewTableNumber] = useState('');
    const [submittingTable, setSubmittingTable] = useState(false);

    // Add Promo Form State
    const [isAddPromoOpen, setIsAddPromoOpen] = useState(false);
    const [promoCode, setPromoCode] = useState('');
    const [promoName, setPromoName] = useState('');
    const [promoType, setPromoType] = useState('PERCENTAGE');
    const [discountValue, setDiscountValue] = useState('');
    const [minOrder, setMinOrder] = useState('0');
    const [maxDiscount, setMaxDiscount] = useState('');
    const [submittingPromo, setSubmittingPromo] = useState(false);

    const branchId = user?.branch_id || 1;

    const toggleSelectTable = (tblId) => {
        if (selectedTableIds.includes(tblId)) {
            setSelectedTableIds(selectedTableIds.filter(id => id !== tblId));
        } else {
            setSelectedTableIds([...selectedTableIds, tblId]);
        }
    };

    const handleSelectAllTables = () => {
        if (selectedTableIds.length === tables.length) {
            setSelectedTableIds([]);
        } else {
            setSelectedTableIds(tables.map(t => t.id));
        }
    };

    const handlePrintSelectedTables = () => {
        if (selectedTableIds.length === 0) {
            alert('Silakan pilih minimal 1 meja untuk dicetak.');
            return;
        }
        const selected = tables.filter(t => selectedTableIds.includes(t.id));
        setPrintingTablesList(selected);
    };

    const handlePrintAllTables = () => {
        if (tables.length === 0) {
            alert('Belum ada data meja di cabang ini.');
            return;
        }
        setPrintingTablesList(tables);
    };

    const handleAddTable = (e) => {
        e.preventDefault();
        if (!newTableNumber.trim()) return;
        setSubmittingTable(true);
        axios.post(`/api/branches/${branchId}/tables`, { table_number: newTableNumber.trim() })
            .then(() => {
                setSubmittingTable(false);
                setIsAddTableOpen(false);
                setNewTableNumber('');
                alert('Meja baru berhasil ditambahkan.');
                fetchTables();
            })
            .catch(err => {
                setSubmittingTable(false);
                alert(err.response?.data?.message || 'Gagal menambahkan meja.');
            });
    };

    const fetchCategories = () => {
        axios.get('/api/categories')
            .then(res => {
                setCategories(res.data || []);
                if (res.data && res.data.length > 0 && !newMenuCategory) {
                    setNewMenuCategory(res.data[0].id);
                }
            })
            .catch(() => {});
    };

    const handleAddCategory = (e) => {
        e.preventDefault();
        if (!newCategoryName.trim()) return;
        setSubmittingCategory(true);
        axios.post('/api/categories', { name: newCategoryName.trim(), description: newCategoryDesc })
            .then(() => {
                setSubmittingCategory(false);
                setIsAddCategoryOpen(false);
                setNewCategoryName('');
                setNewCategoryDesc('');
                alert('Kategori baru berhasil ditambahkan.');
                fetchCategories();
            })
            .catch(err => {
                setSubmittingCategory(false);
                alert(err.response?.data?.message || 'Gagal menambahkan kategori.');
            });
    };

    const fetchAddons = () => {
        axios.get('/api/addons')
            .then(res => setAddons(res.data || []))
            .catch(() => {});
    };

    const handleAddAddon = (e) => {
        e.preventDefault();
        if (!newAddonName.trim() || !newAddonPrice) {
            alert('Silakan isi nama dan harga Add-On.');
            return;
        }

        setSubmittingAddon(true);
        axios.post('/api/addons', { name: newAddonName.trim(), price: parseFloat(newAddonPrice) })
            .then(() => {
                setSubmittingAddon(false);
                setIsAddAddonOpen(false);
                setNewAddonName('');
                setNewAddonPrice('');
                alert('Add-On baru berhasil ditambahkan.');
                fetchAddons();
            })
            .catch(err => {
                setSubmittingAddon(false);
                alert(err.response?.data?.message || 'Gagal menambahkan Add-On.');
            });
    };

    const toggleNewMenuAddon = (addonId) => {
        if (newMenuSelectedAddons.includes(addonId)) {
            setNewMenuSelectedAddons(newMenuSelectedAddons.filter(id => id !== addonId));
        } else {
            setNewMenuSelectedAddons([...newMenuSelectedAddons, addonId]);
        }
    };

    const toggleEditMenuAddon = (addonId) => {
        if (editMenuSelectedAddons.includes(addonId)) {
            setEditMenuSelectedAddons(editMenuSelectedAddons.filter(id => id !== addonId));
        } else {
            setEditMenuSelectedAddons([...editMenuSelectedAddons, addonId]);
        }
    };

    const handleAddMenu = (e) => {
        e.preventDefault();
        if (!newMenuName.trim() || !newMenuPrice || !newMenuCategory) {
            alert('Silakan lengkapi nama, harga, dan kategori menu.');
            return;
        }

        setSubmittingMenu(true);
        const formData = new FormData();
        formData.append('sku', newMenuSku.trim() || `MNU-${Date.now()}`);
        formData.append('name', newMenuName.trim());
        formData.append('category_id', newMenuCategory);
        formData.append('base_price', newMenuPrice);
        formData.append('description', newMenuDesc);
        if (newMenuImageFile) {
            formData.append('master_image', newMenuImageFile);
        }

        newMenuSelectedAddons.forEach(id => {
            formData.append('addon_ids[]', id);
        });

        axios.post('/api/menus', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
            .then(() => {
                setSubmittingMenu(false);
                setIsAddMenuOpen(false);
                setNewMenuSku('');
                setNewMenuName('');
                setNewMenuPrice('');
                setNewMenuDesc('');
                setNewMenuImageFile(null);
                setNewMenuSelectedAddons([]);
                alert('Menu baru berhasil ditambahkan dan diaktifkan di cabang ini!');
                fetchBranchMenus();
            })
            .catch(err => {
                setSubmittingMenu(false);
                alert(err.response?.data?.message || 'Gagal menambahkan menu baru.');
            });
    };

    useEffect(() => {
        fetchCategories();
        fetchAddons();
        if (activeTab === 'menu') fetchBranchMenus();
        if (activeTab === 'promos') fetchPromos();
        if (activeTab === 'tables') fetchTables();
        if (activeTab === 'inventory') fetchInventory();
    }, [activeTab]);

    const fetchBranchMenus = () => {
        setLoading(true);
        axios.get(`/api/branches/${branchId}/menus`)
            .then(res => { setBranchMenus(res.data); setLoading(false); })
            .catch(() => setLoading(false));
    };

    const fetchPromos = () => {
        setLoading(true);
        axios.get(`/api/promos`, { params: { branch_id: branchId } })
            .then(res => { setPromos(res.data); setLoading(false); })
            .catch(() => setLoading(false));
    };

    const fetchTables = () => {
        setLoading(true);
        axios.get(`/api/branches/${branchId}/tables`)
            .then(res => { setTables(res.data); setLoading(false); })
            .catch(() => setLoading(false));
    };

    const fetchInventory = () => {
        setLoading(true);
        axios.get(`/api/reports/inventory`, { params: { branch_id: branchId } })
            .then(res => { setInventory(res.data.data || []); setLoading(false); })
            .catch(() => setLoading(false));
    };

    const toggleAvailability = (bmId, currentAvailability) => {
        const nextAvail = currentAvailability === 'available' ? 'sold_out' : 'available';
        axios.patch(`/api/branch-menus/${bmId}/availability`, { availability: nextAvail })
            .then(() => fetchBranchMenus())
            .catch(err => alert(err.response?.data?.message || 'Gagal mengubah status availability.'));
    };

    const openEditModal = (bm) => {
        setEditingMenu(bm);
        setEditPrice(bm.price);
        setEditAvailability(bm.availability);
        setEditStatus(bm.status);
        setEditImageFile(null);
        setEditMenuSelectedAddons(bm.menu?.addons?.map(a => a.id) || []);
    };

    const handleSaveEditMenu = (e) => {
        e.preventDefault();
        if (!editingMenu) return;
        setSubmittingEdit(true);

        const formData = new FormData();
        formData.append('price', editPrice);
        formData.append('availability', editAvailability);
        formData.append('status', editStatus);
        if (editImageFile) {
            formData.append('branch_image', editImageFile);
        }

        // Save BranchMenu update
        const updateBranchMenuPromise = axios.post(`/api/branches/${branchId}/menus/${editingMenu.menu_id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Also update Master Menu addons mapping if editing Menu
        const masterMenuData = new FormData();
        masterMenuData.append('category_id', editingMenu.menu?.category_id);
        masterMenuData.append('name', editingMenu.menu?.name);
        masterMenuData.append('base_price', editingMenu.menu?.base_price || editPrice);
        editMenuSelectedAddons.forEach(id => {
            masterMenuData.append('addon_ids[]', id);
        });

        const updateMasterMenuAddonsPromise = axios.post(`/api/menus/${editingMenu.menu_id}`, masterMenuData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Promise.all([updateBranchMenuPromise, updateMasterMenuAddonsPromise])
            .then(() => {
                setSubmittingEdit(false);
                setEditingMenu(null);
                alert('Menu & Add-ons cabang berhasil diperbarui.');
                fetchBranchMenus();
            })
            .catch(err => {
                setSubmittingEdit(false);
                alert(err.response?.data?.message || 'Gagal memperbarui menu.');
            });
    };

    const handleDeleteMenu = (bmId) => {
        if (!confirm('Apakah Anda yakin ingin menghapus menu cabang ini?')) return;
        axios.delete(`/api/branch-menus/${bmId}`)
            .then(() => {
                alert('Menu cabang berhasil dihapus.');
                fetchBranchMenus();
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal menghapus menu.'));
    };

    const handleRegenerateQr = (tableId) => {
        if (!confirm('Apakah Anda yakin ingin memperbarui QR token meja ini? Token lama akan TIDAK BERLAKU lagi.')) return;
        axios.post(`/api/branches/${branchId}/tables/${tableId}/regenerate-qr`)
            .then(res => {
                alert(res.data.message);
                fetchTables();
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal meregenerate QR.'));
    };

    const handleAddPromo = (e) => {
        e.preventDefault();
        if (!promoCode.trim() || !promoName.trim() || !discountValue) {
            alert('Silakan lengkapi data promo.');
            return;
        }

        setSubmittingPromo(true);
        axios.post('/api/promos', {
            code: promoCode,
            name: promoName,
            type: promoType,
            discount_value: parseFloat(discountValue),
            min_order_amount: parseFloat(minOrder) || 0,
            max_discount_amount: maxDiscount ? parseFloat(maxDiscount) : null,
            branch_id: branchId,
            is_stackable: true,
        })
            .then(() => {
                setSubmittingPromo(false);
                setIsAddPromoOpen(false);
                setPromoCode('');
                setPromoName('');
                setDiscountValue('');
                alert('Promo berhasil ditambahkan.');
                fetchPromos();
            })
            .catch(err => {
                setSubmittingPromo(false);
                alert(err.response?.data?.message || 'Gagal menambahkan promo.');
            });
    };

    const handleDeletePromo = (id) => {
        if (!confirm('Apakah Anda yakin ingin menghapus promo ini?')) return;
        axios.delete(`/api/promos/${id}`)
            .then(() => {
                alert('Promo berhasil dihapus.');
                fetchPromos();
            })
            .catch(err => alert(err.response?.data?.message || 'Gagal menghapus promo.'));
    };

    return (
        <div className="p-3 sm:p-6 bg-gray-50 min-h-screen max-w-full overflow-x-hidden">
            {/* Header */}
            <div className="flex justify-between items-center mb-4 sm:mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-200">
                <div>
                    <h1 className="text-lg sm:text-xl font-bold text-gray-900">Portal Admin Cabang</h1>
                    <p className="text-xs text-gray-500">{user?.branch?.name || 'Pengelolaan Cabang'}</p>
                </div>
            </div>

            {/* Navigation Tabs (Scrollable on mobile) */}
            <div className="flex gap-2.5 mb-4 sm:mb-6 overflow-x-auto pb-1 no-scrollbar">
                {[
                    { id: 'menu', label: 'Kelola Menu Cabang' },
                    { id: 'promos', label: 'Kelola Promo Cabang' },
                    { id: 'tables', label: 'Manajemen Meja & Print QR' },
                    { id: 'inventory', label: 'Stok & Pergerakan Barang' },
                ].map(t => (
                    <button
                        key={t.id}
                        onClick={() => setActiveTab(t.id)}
                        className={`px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all ${
                            activeTab === t.id ? 'bg-brand-600 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Content: Menu Cabang */}
            {activeTab === 'menu' && (
                <div className="space-y-4">
                    {/* Top Action Bar */}
                    <div className="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div>
                            <h3 className="font-extrabold text-sm text-gray-900">Pengelolaan Menu Cabang</h3>
                            <p className="text-xs text-gray-500">Kelola ketersediaan, harga cabang, serta buat menu & kategori baru</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <button
                                onClick={() => setIsAddAddonOpen(true)}
                                className="bg-amber-100 hover:bg-amber-200 text-amber-800 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 border border-amber-300 transition-all"
                            >
                                <Plus className="h-4 w-4 text-amber-600" />
                                Tambah Add-On
                            </button>
                            <button
                                onClick={() => setIsAddCategoryOpen(true)}
                                className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 border border-slate-300 transition-all"
                            >
                                <Plus className="h-4 w-4 text-slate-500" />
                                Tambah Kategori
                            </button>
                            <button
                                onClick={() => setIsAddMenuOpen(true)}
                                className="bg-brand-600 hover:bg-brand-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow transition-all"
                            >
                                <Plus className="h-4 w-4" />
                                Tambah Menu Baru
                            </button>
                        </div>
                    </div>

                    <div className="staff-table-scroll bg-white rounded-2xl shadow-sm border border-gray-200">
                        <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="bg-gray-50 border-b text-gray-600 font-bold uppercase">
                                <th className="p-4">Foto Cabang</th>
                                <th className="p-4">Nama Master Menu</th>
                                <th className="p-4">Harga Cabang</th>
                                <th className="p-4">Add-Ons Aktif</th>
                                <th className="p-4">Ketersediaan</th>
                                <th className="p-4">Status</th>
                                <th className="p-4 text-center">Aksi (Edit / Hapus)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 font-medium">
                            {branchMenus.map(bm => (
                                <tr key={bm.id} className="hover:bg-gray-50">
                                    <td className="p-4">
                                        <div className="w-12 h-12 rounded-xl bg-gray-100 overflow-hidden border">
                                            {bm.effective_image ? (
                                                <img src={bm.effective_image} alt="" className="w-full h-full object-cover" />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center text-[10px] text-gray-400">No Image</div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-4 font-bold text-gray-900">{bm.menu?.name}</td>
                                    <td className="p-4 font-bold text-brand-600">Rp {bm.price?.toLocaleString('id-ID')}</td>
                                    <td className="p-4">
                                        {bm.menu?.addons && bm.menu.addons.length > 0 ? (
                                            <div className="flex flex-wrap gap-1">
                                                {bm.menu.addons.map(a => (
                                                    <span key={a.id} className="bg-amber-50 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-md border border-amber-200">
                                                        +{a.name} (Rp {a.price?.toLocaleString('id-ID')})
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-gray-400 text-[10px] italic">Tanpa Add-On</span>
                                        )}
                                    </td>
                                    <td className="p-4">
                                        <button
                                            onClick={() => toggleAvailability(bm.id, bm.availability)}
                                            className={`px-3 py-1 rounded-full font-bold text-[10px] ${
                                                bm.availability === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                                            }`}
                                        >
                                            {bm.availability === 'available' ? 'TERSEDIA' : 'SOLD OUT'}
                                        </button>
                                    </td>
                                    <td className="p-4">
                                        <span className={`px-2 py-0.5 rounded font-bold text-[10px] ${bm.status === 'active' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {bm.status?.toUpperCase()}
                                        </span>
                                    </td>
                                    <td className="p-4 text-center">
                                        <div className="flex justify-center items-center gap-2">
                                            <button
                                                onClick={() => openEditModal(bm)}
                                                className="bg-brand-50 hover:bg-brand-100 text-brand-700 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 border border-brand-200"
                                            >
                                                <Edit className="h-3.5 w-3.5" /> Edit
                                            </button>
                                            <button
                                                onClick={() => handleDeleteMenu(bm.id)}
                                                className="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 border border-red-200"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" /> Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        )}

            {/* Content: Promo Management */}
            {activeTab === 'promos' && (
                <div className="space-y-4">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-4 rounded-2xl shadow-sm border">
                        <div>
                            <h3 className="font-bold text-sm text-gray-900">Daftar Promo Aktif Cabang</h3>
                            <p className="text-xs text-gray-500">Kelola diskon & promo khusus cabang ini</p>
                        </div>
                        <button
                            onClick={() => setIsAddPromoOpen(true)}
                            className="bg-brand-600 hover:bg-brand-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow"
                        >
                            <Plus className="h-4 w-4" /> Tambah Promo Baru
                        </button>
                    </div>

                    <div className="staff-table-scroll bg-white rounded-2xl shadow-sm border border-gray-200">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="bg-gray-50 border-b text-gray-600 font-bold uppercase">
                                    <th className="p-4">Kode Promo</th>
                                    <th className="p-4">Nama Promo</th>
                                    <th className="p-4">Tipe Diskon</th>
                                    <th className="p-4">Nilai Diskon</th>
                                    <th className="p-4">Min. Belanja</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 font-medium">
                                {promos.map(p => (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="p-4 font-mono font-bold text-brand-600">{p.code}</td>
                                        <td className="p-4 font-bold text-gray-900">{p.name}</td>
                                        <td className="p-4">{p.type}</td>
                                        <td className="p-4 font-bold text-emerald-600">
                                            {p.type === 'PERCENTAGE' ? `${p.discount_value}%` : `Rp ${p.discount_value?.toLocaleString('id-ID')}`}
                                        </td>
                                        <td className="p-4">Rp {p.min_order_amount?.toLocaleString('id-ID')}</td>
                                        <td className="p-4">
                                            <span className={`px-2 py-0.5 rounded font-bold text-[10px] ${p.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                                {p.status?.toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="p-4 text-center">
                                            <button
                                                onClick={() => handleDeletePromo(p.id)}
                                                className="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 border border-red-200 mx-auto"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" /> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Content: Tables & Print QR */}
            {activeTab === 'tables' && (
                <div className="space-y-4">
                    {/* Control Action Bar */}
                    <div className="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                        <div>
                            <h3 className="font-extrabold text-sm text-gray-900 flex items-center gap-2">
                                <QrCode className="h-4 w-4 text-brand-600" />
                                Manajemen Meja & Cetak QR Code
                            </h3>
                            <p className="text-xs text-gray-500">
                                Pilih beberapa meja atau cetak seluruh QR meja cabang sekaligus dalam 1 lembar dokumen print.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
                            <button
                                onClick={handleSelectAllTables}
                                className={`px-3 py-2 rounded-xl text-xs font-bold border transition-all ${
                                    selectedTableIds.length > 0 && selectedTableIds.length === tables.length
                                        ? 'bg-brand-50 text-brand-700 border-brand-300'
                                        : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'
                                }`}
                            >
                                {selectedTableIds.length === tables.length ? '✓ Batal Pilih Semua' : `Pilih Semua (${tables.length})`}
                            </button>

                            <button
                                onClick={handlePrintSelectedTables}
                                disabled={selectedTableIds.length === 0}
                                className={`px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition-all ${
                                    selectedTableIds.length > 0
                                        ? 'bg-brand-600 hover:bg-brand-700 text-white cursor-pointer'
                                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                }`}
                            >
                                <Printer className="h-3.5 w-3.5" />
                                Cetak Terpilih ({selectedTableIds.length} Meja)
                            </button>

                            <button
                                onClick={handlePrintAllTables}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow"
                            >
                                <Printer className="h-3.5 w-3.5" />
                                Cetak Semua QR Meja
                            </button>

                            <button
                                onClick={() => setIsAddTableOpen(true)}
                                className="bg-slate-900 hover:bg-slate-800 text-white font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1 shadow"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Tambah Meja
                            </button>
                        </div>
                    </div>

                    {/* Table Cards Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {tables.map(tbl => {
                            const isSelected = selectedTableIds.includes(tbl.id);
                            return (
                                <div
                                    key={tbl.id}
                                    className={`bg-white p-5 rounded-2xl border transition-all shadow-sm flex flex-col justify-between ${
                                        isSelected ? 'border-brand-500 ring-2 ring-brand-500/20 bg-brand-50/20' : 'border-gray-200'
                                    }`}
                                >
                                    <div>
                                        <div className="flex justify-between items-center mb-2">
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={isSelected}
                                                    onChange={() => toggleSelectTable(tbl.id)}
                                                    className="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                                                />
                                                <h3 className="font-extrabold text-lg text-gray-900">MEJA {tbl.table_number}</h3>
                                            </label>
                                            <span className="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded-md uppercase">
                                                {tbl.status}
                                            </span>
                                        </div>
                                        <p className="text-[10px] text-gray-400 font-mono break-all mb-4">Token: {tbl.qr_code_token}</p>
                                    </div>
                                    <div className="flex gap-2 pt-2 border-t border-gray-100">
                                        <button
                                            onClick={() => setPrintingTablesList([tbl])}
                                            className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 rounded-xl text-xs flex items-center justify-center gap-1.5 shadow"
                                        >
                                            <Printer className="h-3.5 w-3.5" /> Print QR
                                        </button>
                                        <button
                                            onClick={() => handleRegenerateQr(tbl.id)}
                                            className="bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold px-3 py-2 rounded-xl text-xs flex items-center justify-center gap-1 border border-amber-200"
                                            title="Regenerate QR Token"
                                        >
                                            <RefreshCw className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Content: Inventory Stock Movement */}
            {activeTab === 'inventory' && (
                <div className="staff-table-scroll bg-white rounded-2xl shadow-sm border border-gray-200">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="bg-gray-50 border-b text-gray-600 font-bold uppercase">
                                <th className="p-4">Waktu</th>
                                <th className="p-4">Bahan Baku</th>
                                <th className="p-4">Tipe Pergerakan</th>
                                <th className="p-4">Perubahan Stok</th>
                                <th className="p-4">Stok Akhir</th>
                                <th className="p-4">Catatan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 font-medium">
                            {inventory.map(m => (
                                <tr key={m.id}>
                                    <td className="p-4">{new Date(m.created_at).toLocaleString('id-ID')}</td>
                                    <td className="p-4 font-bold text-gray-900">{m.inventory?.item_name}</td>
                                    <td className="p-4 font-bold text-blue-600">{m.type}</td>
                                    <td className={`p-4 font-bold ${m.quantity_change < 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                                        {m.quantity_change > 0 ? `+${m.quantity_change}` : m.quantity_change} {m.inventory?.unit}
                                    </td>
                                    <td className="p-4 font-bold">{m.stock_after} {m.inventory?.unit}</td>
                                    <td className="p-4 text-gray-500">{m.notes}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Edit Branch Menu Modal */}
            {editingMenu && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-bold text-base text-gray-900">Edit Menu Cabang — {editingMenu.menu?.name}</h3>
                            <button onClick={() => setEditingMenu(null)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleSaveEditMenu} className="space-y-4 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Harga Cabang (Rp)</label>
                                <input
                                    type="number"
                                    value={editPrice}
                                    onChange={e => setEditPrice(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold text-brand-600 text-sm focus:ring-2 focus:ring-brand-500"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Ketersediaan</label>
                                    <select
                                        value={editAvailability}
                                        onChange={e => setEditAvailability(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    >
                                        <option value="available">Tersedia</option>
                                        <option value="sold_out">Sold Out (Habis)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Status Menu</label>
                                    <select
                                        value={editStatus}
                                        onChange={e => setEditStatus(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    >
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Nonaktif</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Setting Add-Ons yang Berlaku untuk Menu Ini</label>
                                <div className="bg-gray-50 p-2.5 rounded-xl border max-h-36 overflow-y-auto grid grid-cols-1 gap-1.5">
                                    {addons.map(a => (
                                        <label key={a.id} className="flex items-center gap-2 p-1.5 bg-white rounded-lg border border-gray-200 text-xs font-bold text-gray-800 cursor-pointer hover:bg-amber-50/50">
                                            <input
                                                type="checkbox"
                                                checked={editMenuSelectedAddons.includes(a.id)}
                                                onChange={() => toggleEditMenuAddon(a.id)}
                                                className="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                                            />
                                            <span className="flex-1">{a.name}</span>
                                            <span className="text-emerald-600 font-semibold text-[11px]">+Rp {a.price?.toLocaleString('id-ID')}</span>
                                        </label>
                                    ))}
                                    {addons.length === 0 && (
                                        <p className="text-[10px] text-gray-400 text-center py-2">Belum ada Add-On. Tambahkan Add-On terlebih dahulu.</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Foto Khusus Cabang (Opsional)</label>
                                {(editingMenu.effective_image || editImageFile) && (
                                    <div className="mb-2 flex items-center gap-3 bg-gray-50 p-2 rounded-xl border">
                                        <div className="w-12 h-12 rounded-lg overflow-hidden bg-gray-200 border flex-shrink-0">
                                            <img
                                                src={editImageFile ? URL.createObjectURL(editImageFile) : editingMenu.effective_image}
                                                alt="Preview"
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <div className="text-[10px] text-gray-500">
                                            <p className="font-bold text-gray-800">{editImageFile ? 'Gambar Baru Dipilih' : 'Gambar Aktif Saat Ini'}</p>
                                            <p>{editImageFile ? editImageFile.name : 'Tersimpan di Server'}</p>
                                        </div>
                                    </div>
                                )}
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={e => setEditImageFile(e.target.files[0])}
                                    className="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-600 file:text-white cursor-pointer"
                                />
                            </div>

                            <div className="flex gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setEditingMenu(null)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingEdit}
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingEdit ? 'Simpan...' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Add Promo Modal */}
            {isAddPromoOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-bold text-base text-gray-900">Tambah Promo Cabang Baru</h3>
                            <button onClick={() => setIsAddPromoOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleAddPromo} className="space-y-3 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Kode Promo (Misal: DISKON10)</label>
                                <input
                                    type="text"
                                    value={promoCode}
                                    onChange={e => setPromoCode(e.target.value.toUpperCase())}
                                    className="w-full p-2.5 border rounded-xl font-mono uppercase font-bold focus:ring-2 focus:ring-brand-500"
                                    placeholder="LESEHAN10"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Nama Promo</label>
                                <input
                                    type="text"
                                    value={promoName}
                                    onChange={e => setPromoName(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    placeholder="Diskon Akhir Pekan 10%"
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Tipe Diskon</label>
                                    <select
                                        value={promoType}
                                        onChange={e => setPromoType(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    >
                                        <option value="PERCENTAGE">Persentase (%)</option>
                                        <option value="FIXED_AMOUNT">Potongan Tetap (Rp)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Nilai Diskon</label>
                                    <input
                                        type="number"
                                        value={discountValue}
                                        onChange={e => setDiscountValue(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-bold focus:ring-2 focus:ring-brand-500"
                                        placeholder={promoType === 'PERCENTAGE' ? '10' : '10000'}
                                        required
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Min. Belanja (Rp)</label>
                                    <input
                                        type="number"
                                        value={minOrder}
                                        onChange={e => setMinOrder(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                        placeholder="50000"
                                    />
                                </div>
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Maks. Diskon (Rp)</label>
                                    <input
                                        type="number"
                                        value={maxDiscount}
                                        onChange={e => setMaxDiscount(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                        placeholder="20000"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setIsAddPromoOpen(false)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingPromo}
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingPromo ? 'Simpan...' : 'Tambah Promo'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Tambah Extra Add-On Baru */}
            {isAddAddonOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 className="font-bold text-base text-gray-900">Tambah Extra Add-On Baru</h3>
                            <button onClick={() => setIsAddAddonOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleAddAddon} className="space-y-3 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Nama Extra Add-On *</label>
                                <input
                                    type="text"
                                    value={newAddonName}
                                    onChange={e => setNewAddonName(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold focus:ring-2 focus:ring-brand-500"
                                    placeholder="Contoh: Telur Ceplok, Extra Sambal, Keju Parut"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Harga Tambahan (Rp) *</label>
                                <input
                                    type="number"
                                    value={newAddonPrice}
                                    onChange={e => setNewAddonPrice(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold text-emerald-600 text-sm focus:ring-2 focus:ring-brand-500"
                                    placeholder="5000"
                                    required
                                />
                            </div>

                            <div className="flex gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setIsAddAddonOpen(false)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingAddon}
                                    className="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingAddon ? 'Simpan...' : 'Simpan Add-On'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Tambah Kategori Baru */}
            {isAddCategoryOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 className="font-bold text-base text-gray-900">Tambah Kategori Menu Baru</h3>
                            <button onClick={() => setIsAddCategoryOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleAddCategory} className="space-y-3 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Nama Kategori *</label>
                                <input
                                    type="text"
                                    value={newCategoryName}
                                    onChange={e => setNewCategoryName(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold focus:ring-2 focus:ring-brand-500"
                                    placeholder="Contoh: Makanan Berat, Minuman Segar, Cemilan"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Deskripsi Kategori (Opsional)</label>
                                <textarea
                                    value={newCategoryDesc}
                                    onChange={e => setNewCategoryDesc(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    rows="2"
                                    placeholder="Keterangan singkat kategori..."
                                />
                            </div>

                            <div className="flex gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setIsAddCategoryOpen(false)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingCategory}
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingCategory ? 'Simpan...' : 'Tambah Kategori'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Tambah Menu Baru */}
            {isAddMenuOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
                    <div className="bg-white w-full max-w-lg rounded-3xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 className="font-bold text-base text-gray-900">Tambah Master Menu Baru</h3>
                            <button onClick={() => setIsAddMenuOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleAddMenu} className="space-y-3.5 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">SKU Code (Opsional)</label>
                                    <input
                                        type="text"
                                        value={newMenuSku}
                                        onChange={e => setNewMenuSku(e.target.value.toUpperCase())}
                                        className="w-full p-2.5 border rounded-xl font-mono uppercase font-bold focus:ring-2 focus:ring-brand-500"
                                        placeholder="MNU-001"
                                    />
                                </div>
                                <div>
                                    <label className="block font-bold text-gray-700 mb-1">Kategori Menu *</label>
                                    <select
                                        value={newMenuCategory}
                                        onChange={e => setNewMenuCategory(e.target.value)}
                                        className="w-full p-2.5 border rounded-xl font-bold text-brand-700 focus:ring-2 focus:ring-brand-500"
                                        required
                                    >
                                        <option value="">-- Pilih Kategori --</option>
                                        {categories.map(c => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Nama Menu *</label>
                                <input
                                    type="text"
                                    value={newMenuName}
                                    onChange={e => setNewMenuName(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold focus:ring-2 focus:ring-brand-500"
                                    placeholder="Contoh: Ayam Bakar Madu Lesehan"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Harga Base / Standar (Rp) *</label>
                                <input
                                    type="number"
                                    value={newMenuPrice}
                                    onChange={e => setNewMenuPrice(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold text-brand-600 text-sm focus:ring-2 focus:ring-brand-500"
                                    placeholder="25000"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Setting Add-Ons yang Berlaku untuk Menu Ini (Opsional)</label>
                                <div className="bg-gray-50 p-2.5 rounded-xl border max-h-36 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                    {addons.map(a => (
                                        <label key={a.id} className="flex items-center gap-2 p-1.5 bg-white rounded-lg border border-gray-200 text-xs font-bold text-gray-800 cursor-pointer hover:bg-amber-50/50">
                                            <input
                                                type="checkbox"
                                                checked={newMenuSelectedAddons.includes(a.id)}
                                                onChange={() => toggleNewMenuAddon(a.id)}
                                                className="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                                            />
                                            <span className="flex-1 truncate">{a.name}</span>
                                            <span className="text-emerald-600 font-semibold text-[10px]">+Rp {a.price?.toLocaleString('id-ID')}</span>
                                        </label>
                                    ))}
                                    {addons.length === 0 && (
                                        <p className="text-[10px] text-gray-400 col-span-2 text-center py-2">Belum ada Add-On tersimpan. Klik "+ Tambah Add-On" untuk membuat baru.</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Foto Menu (Opsional)</label>
                                {newMenuImageFile && (
                                    <div className="mb-2 flex items-center gap-3 bg-gray-50 p-2 rounded-xl border">
                                        <div className="w-12 h-12 rounded-lg overflow-hidden bg-gray-200 border flex-shrink-0">
                                            <img
                                                src={URL.createObjectURL(newMenuImageFile)}
                                                alt="Preview"
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <p className="text-[10px] text-gray-600 font-bold">{newMenuImageFile.name}</p>
                                    </div>
                                )}
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={e => setNewMenuImageFile(e.target.files[0])}
                                    className="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-600 file:text-white cursor-pointer"
                                />
                            </div>

                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Deskripsi Menu (Opsional)</label>
                                <textarea
                                    value={newMenuDesc}
                                    onChange={e => setNewMenuDesc(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-medium focus:ring-2 focus:ring-brand-500"
                                    rows="2"
                                    placeholder="Komposisi rasa, rekomendasi penyajian..."
                                />
                            </div>

                            <div className="flex gap-2 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setIsAddMenuOpen(false)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingMenu}
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingMenu ? 'Simpan...' : 'Simpan & Aktifkan Menu'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Tambah Meja Baru */}
            {isAddTableOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-sm rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-bold text-base text-gray-900">Tambah Meja Cabang Baru</h3>
                            <button onClick={() => setIsAddTableOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        <form onSubmit={handleAddTable} className="space-y-4 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Nomor / Nama Meja *</label>
                                <input
                                    type="text"
                                    value={newTableNumber}
                                    onChange={e => setNewTableNumber(e.target.value)}
                                    className="w-full p-2.5 border rounded-xl font-bold text-gray-900 text-sm focus:ring-2 focus:ring-brand-500"
                                    placeholder="Contoh: 01, 02A, VIP-1"
                                    required
                                />
                            </div>

                            <div className="flex gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setIsAddTableOpen(false)}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={submittingTable}
                                    className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl shadow"
                                >
                                    {submittingTable ? 'Simpan...' : 'Simpan Meja'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Print Table QR Cards Modal (Single or Multi / Bulk Print) */}
            {printingTablesList.length > 0 && (
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
                    <div className="bg-white w-full max-w-4xl rounded-3xl p-6 shadow-2xl flex flex-col items-center max-h-[92vh]">
                        <div className="flex justify-between items-center w-full mb-4 pb-3 border-b">
                            <div>
                                <h3 className="font-extrabold text-base text-gray-900">
                                    Cetak Kartu QR Code Meja ({printingTablesList.length} Meja)
                                </h3>
                                <p className="text-xs text-gray-500">
                                    Siap cetak. Klik tombol Print untuk mencetak seluruh kartu QR meja terpilih sekaligus.
                                </p>
                            </div>
                            <button
                                onClick={() => setPrintingTablesList([])}
                                className="text-gray-400 hover:text-gray-600 font-black p-1 text-lg"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Scrollable Printable Cards Grid */}
                        <div className="flex-1 overflow-y-auto w-full p-2">
                            <div id="printable-qr-grid" className="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                                {printingTablesList.map(tbl => (
                                    <div
                                        key={tbl.id}
                                        className="p-5 bg-amber-50/60 border-2 border-brand-300 rounded-3xl space-y-3 w-full text-center shadow-sm"
                                    >
                                        <div className="border-b-2 border-brand-500 pb-2">
                                            <h2 className="text-lg font-black text-brand-800 tracking-wider uppercase">KEDAI LESEHAN</h2>
                                            <p className="text-xs text-gray-600 font-bold">{user?.branch?.name || 'Cabang Kedai'}</p>
                                        </div>

                                        <div className="bg-brand-600 text-white py-1.5 rounded-2xl">
                                            <h1 className="text-2xl font-black tracking-widest">MEJA #{tbl.table_number}</h1>
                                        </div>

                                        {/* QR Code Container */}
                                        <div className="bg-white p-3 rounded-2xl border border-gray-200 inline-block shadow-inner">
                                            <img
                                                src={`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(`${window.location.origin}/order?token=${tbl.qr_code_token}`)}`}
                                                alt={`QR Meja ${tbl.table_number}`}
                                                className="w-36 h-36 mx-auto"
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <p className="text-xs font-bold text-gray-800">Scan QR Code ini untuk Memesan</p>
                                            <p className="text-[10px] text-brand-600 font-semibold">Selamat Menikmati Hidangan Kami</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Action buttons */}
                        <div className="flex gap-3 w-full mt-4 pt-3 border-t">
                            <button
                                onClick={() => setPrintingTablesList([])}
                                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 rounded-2xl text-xs"
                            >
                                Batal / Tutup
                            </button>
                            <button
                                onClick={() => window.print()}
                                className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-brand-600/30"
                            >
                                <Printer className="h-4 w-4" /> Print {printingTablesList.length} Kartu QR Meja
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
