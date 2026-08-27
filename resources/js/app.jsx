import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';
import '../css/app.css';
import CustomerPortal from './components/CustomerPortal';
import KasirPOS from './components/KasirPOS';
import KitchenDashboard from './components/KitchenDashboard';
import AdminCabangPortal from './components/AdminCabangPortal';
import OwnerPortal from './components/OwnerPortal';
import { LogIn, LogOut, UserCheck, Shield, ShoppingBag, UtensilsCrossed, Monitor, LayoutDashboard, Menu as MenuIcon, X } from 'lucide-react';

export default function App() {
    const [currentView, setCurrentView] = useState('customer'); // customer, pos, kitchen, admin, owner
    const [user, setUser] = useState(null);
    const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
    const [emailInput, setEmailInput] = useState('kasir1@kedai.com');
    const [passwordInput, setPasswordInput] = useState('password123');
    const [loginError, setLoginError] = useState('');
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);

    useEffect(() => {
        const token = localStorage.getItem('kedai_auth_token');
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            axios.get('/api/me')
                .then(res => setUser(res.data.user))
                .catch(() => {
                    localStorage.removeItem('kedai_auth_token');
                    delete axios.defaults.headers.common['Authorization'];
                });
        }

        // Determine view from URL path
        const path = window.location.pathname;
        if (path === '/pos') setCurrentView('pos');
        else if (path === '/kitchen') setCurrentView('kitchen');
        else if (path === '/admin') setCurrentView('admin');
        else if (path === '/owner') setCurrentView('owner');
        else setCurrentView('customer');
    }, []);

    const handleLogin = (e) => {
        e.preventDefault();
        setLoginError('');

        axios.post('/api/login', { email: emailInput, password: passwordInput })
            .then(res => {
                const token = res.data.access_token;
                localStorage.setItem('kedai_auth_token', token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
                setUser(res.data.user);
                setIsLoginModalOpen(false);

                // Redirect view based on user role
                const role = res.data.user.role;
                if (role === 'kasir') setCurrentView('pos');
                else if (role === 'dapur') setCurrentView('kitchen');
                else if (role === 'admin_cabang') setCurrentView('admin');
                else if (role === 'owner') setCurrentView('owner');
            })
            .catch(err => {
                setLoginError(err.response?.data?.message || 'Login gagal.');
            });
    };

    const handleLogout = () => {
        axios.post('/api/logout')
            .finally(() => {
                localStorage.removeItem('kedai_auth_token');
                delete axios.defaults.headers.common['Authorization'];
                setUser(null);
                setCurrentView('customer');
            });
    };

    return (
        <div className="min-h-screen bg-gray-100 font-sans w-full max-w-full overflow-x-hidden">
            {/* If view is Customer Portal, display clean standalone layout */}
            {currentView === 'customer' ? (
                <div>
                    {/* Top Customer Header with Staff Switcher */}
                    <header className="bg-white border-b border-gray-200 px-4 py-2.5 flex justify-between items-center sticky top-0 z-30 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="bg-brand-600 text-white font-black px-2.5 py-1 rounded-xl text-sm tracking-wider">
                                KEDAI
                            </div>
                            <div>
                                <h2 className="font-extrabold text-xs text-gray-900 leading-tight">Kedai Lesehan POS</h2>
                                <p className="text-[10px] text-gray-500 hidden sm:block">Customer Self-Service Menu</p>
                            </div>
                        </div>

                        <div>
                            {user ? (
                                <button
                                    onClick={() => {
                                        if (user.role === 'kasir') setCurrentView('pos');
                                        else if (user.role === 'dapur') setCurrentView('kitchen');
                                        else if (user.role === 'admin_cabang') setCurrentView('admin');
                                        else setCurrentView('owner');
                                    }}
                                    className="bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold px-3 py-1.5 rounded-xl flex items-center gap-1.5 shadow"
                                >
                                    <LayoutDashboard className="h-3.5 w-3.5" /> Kembali ke Portal Staff ({user.role})
                                </button>
                            ) : (
                                <button
                                    onClick={() => setIsLoginModalOpen(true)}
                                    className="bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold px-3 py-2 rounded-xl flex items-center gap-1.5 shadow"
                                >
                                    <LogIn className="h-4 w-4" /> Login Staff
                                </button>
                            )}
                        </div>
                    </header>

                    <main>
                        <CustomerPortal />
                    </main>
                </div>
            ) : (
                /* STAFF WORKSPACE WITH RESPONSIVE LEFT SIDEBAR LAYOUT */
                <div className="flex flex-col md:flex-row h-screen overflow-hidden bg-gray-100">
                    {/* Mobile Top Navigation Header (Visible only on Mobile & Tablet < md) */}
                    <header className="md:hidden bg-slate-900 text-white px-4 py-3 flex items-center justify-between z-30 shadow-md border-b border-slate-800">
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => setIsMobileSidebarOpen(!isMobileSidebarOpen)}
                                className="p-2 bg-slate-800 text-white rounded-xl hover:bg-slate-700 focus:outline-none"
                                title="Toggle Menu Navigation"
                            >
                                {isMobileSidebarOpen ? <X className="h-5 w-5" /> : <MenuIcon className="h-5 w-5" />}
                            </button>
                            <div className="flex items-center gap-2">
                                <div className="bg-brand-600 text-white font-black px-2 py-0.5 rounded-lg text-xs tracking-wider">
                                    KEDAI
                                </div>
                                <span className="font-bold text-xs text-white uppercase truncate max-w-[140px]">
                                    {currentView} ({user?.branch?.code || 'MULTI'})
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <span className="text-[10px] bg-brand-500/20 text-brand-400 px-2 py-0.5 rounded font-bold uppercase">
                                {user?.role}
                            </span>
                            <button
                                onClick={handleLogout}
                                className="p-1.5 bg-red-500/10 text-red-400 rounded-lg hover:bg-red-500/20"
                                title="Logout"
                            >
                                <LogOut className="h-4 w-4" />
                            </button>
                        </div>
                    </header>

                    {/* Mobile Sidebar Overlay Backdrop */}
                    {isMobileSidebarOpen && (
                        <div
                            onClick={() => setIsMobileSidebarOpen(false)}
                            className="fixed inset-0 bg-black/70 backdrop-blur-xs z-40 md:hidden transition-opacity"
                        ></div>
                    )}

                    {/* Responsive Left Sidebar Navigation */}
                    <aside className={`fixed md:static inset-y-0 left-0 w-64 bg-slate-900 text-slate-100 flex flex-col justify-between border-r border-slate-800 shadow-2xl z-50 transform transition-transform duration-300 ${
                        isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
                    }`}>
                        {/* Sidebar Header */}
                        <div>
                            <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="bg-brand-600 text-white font-black px-2.5 py-1.5 rounded-xl text-base tracking-wider shadow-lg">
                                        KEDAI
                                    </div>
                                    <div>
                                        <h2 className="font-black text-xs tracking-wide text-white">STAFF PORTAL</h2>
                                        <span className="text-[9px] bg-brand-500/20 text-brand-400 border border-brand-500/30 px-1.5 py-0.5 rounded font-mono uppercase font-bold">
                                            {user?.branch ? user.branch.code : 'Kedai Multi'}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setIsMobileSidebarOpen(false)}
                                    className="md:hidden text-slate-400 hover:text-white p-1"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Staff Navigation Links */}
                            <nav className="p-3 space-y-1.5 text-xs font-bold">
                                <div className="px-3 py-2 text-[10px] text-slate-400 uppercase tracking-wider font-extrabold">
                                    Navigasi Staff
                                </div>

                                {(user?.role === 'owner' || user?.role === 'admin_cabang' || user?.role === 'kasir') && (
                                    <button
                                        onClick={() => { setCurrentView('pos'); setIsMobileSidebarOpen(false); }}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                                            currentView === 'pos'
                                                ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30'
                                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        }`}
                                    >
                                        <Monitor className="h-4 w-4" /> Kasir POS
                                    </button>
                                )}

                                {(user?.role === 'owner' || user?.role === 'admin_cabang' || user?.role === 'dapur') && (
                                    <button
                                        onClick={() => { setCurrentView('kitchen'); setIsMobileSidebarOpen(false); }}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                                            currentView === 'kitchen'
                                                ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30'
                                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        }`}
                                    >
                                        <UtensilsCrossed className="h-4 w-4" /> Kitchen KDS (Dapur)
                                    </button>
                                )}

                                {(user?.role === 'owner' || user?.role === 'admin_cabang') && (
                                    <button
                                        onClick={() => { setCurrentView('admin'); setIsMobileSidebarOpen(false); }}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                                            currentView === 'admin'
                                                ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30'
                                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        }`}
                                    >
                                        <LayoutDashboard className="h-4 w-4" /> Admin Cabang
                                    </button>
                                )}

                                {user?.role === 'owner' && (
                                    <button
                                        onClick={() => { setCurrentView('owner'); setIsMobileSidebarOpen(false); }}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                                            currentView === 'owner'
                                                ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/30'
                                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                        }`}
                                    >
                                        <Shield className="h-4 w-4" /> Owner Portal
                                    </button>
                                )}

                                <div className="pt-2 px-3 py-1 text-[10px] text-slate-400 uppercase tracking-wider font-extrabold">
                                    Prinjau Mode
                                </div>
                                <button
                                    onClick={() => { setCurrentView('customer'); setIsMobileSidebarOpen(false); }}
                                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all"
                                >
                                    <ShoppingBag className="h-4 w-4" /> Mode Customer QR
                                </button>
                            </nav>
                        </div>

                        {/* Sidebar Footer: Active User & Logout */}
                        <div className="p-3 border-t border-slate-800 bg-slate-950/50">
                            {user ? (
                                <div className="flex items-center justify-between gap-2 p-2 bg-slate-800/60 rounded-xl border border-slate-700/50">
                                    <div className="min-w-0">
                                        <p className="text-xs font-bold text-white truncate">{user.name}</p>
                                        <p className="text-[10px] text-brand-400 uppercase font-semibold">{user.role}</p>
                                    </div>
                                    <button
                                        onClick={handleLogout}
                                        className="p-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-lg transition-all"
                                        title="Logout Staff"
                                    >
                                        <LogOut className="h-4 w-4" />
                                    </button>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setIsLoginModalOpen(true)}
                                    className="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 rounded-xl text-xs flex items-center justify-center gap-2"
                                >
                                    <LogIn className="h-4 w-4" /> Login Staff
                                </button>
                            )}
                        </div>
                    </aside>

                    {/* Main Content View (POS, Kitchen, Admin, Owner) */}
                    <main className="flex-1 min-w-0 overflow-y-auto bg-gray-50 h-full">
                        {currentView === 'pos' && <KasirPOS user={user} />}
                        {currentView === 'kitchen' && <KitchenDashboard user={user} />}
                        {currentView === 'admin' && <AdminCabangPortal user={user} />}
                        {currentView === 'owner' && <OwnerPortal user={user} />}
                    </main>
                </div>
            )}

            {/* Login Modal */}
            {isLoginModalOpen && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white w-full max-w-sm rounded-3xl p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-bold text-lg text-gray-900">Login Staff Kedai</h3>
                            <button onClick={() => setIsLoginModalOpen(false)} className="text-gray-400 font-bold p-1">✕</button>
                        </div>

                        {loginError && (
                            <div className="mb-4 p-3 bg-red-50 text-red-700 text-xs font-bold rounded-xl">
                                {loginError}
                            </div>
                        )}

                        <form onSubmit={handleLogin} className="space-y-3 text-xs">
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Email Staff</label>
                                <input
                                    type="email"
                                    value={emailInput}
                                    onChange={e => setEmailInput(e.target.value)}
                                    className="w-full p-2.5 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-bold text-gray-700 mb-1">Password</label>
                                <input
                                    type="password"
                                    value={passwordInput}
                                    onChange={e => setPasswordInput(e.target.value)}
                                    className="w-full p-2.5 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-brand-500 focus:outline-none"
                                />
                            </div>

                            <div className="pt-2">
                                <p className="text-[10px] text-gray-400 mb-2">Akun Seed Demo:</p>
                                <div className="grid grid-cols-2 gap-1 text-[10px] text-gray-600 bg-gray-50 p-2 rounded-xl font-mono">
                                    <span>Owner: owner@kedai.com</span>
                                    <span>Kasir: kasir1@kedai.com</span>
                                    <span>Admin: admin1@kedai.com</span>
                                    <span>Dapur: dapur1@kedai.com</span>
                                </div>
                            </div>

                            <button
                                type="submit"
                                className="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-2xl text-xs mt-3 shadow-lg shadow-brand-600/30"
                            >
                                Masuk ke Sistem
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// Bootstrap React — mount App to DOM
const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <App />
        </React.StrictMode>
    );
}
